<?php
// api/queue_copy.php
// Copia arquivo/dir dentro da jaula /home usando wrapper sudo /usr/local/bin/safe_copy.sh
// Saída JSON apropriada e logs em /tmp/queue_copy_debug.log

require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

define('SAFE_ROOT_PATH', realpath('/home'));
$LOGFILE = '/tmp/queue_copy_debug.log';

function log_debug($obj) {
    global $LOGFILE;
    $s = is_string($obj) ? $obj : json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents($LOGFILE, '['.date('c').'] '.$s.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function json_error($msg, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

try {
    if (SAFE_ROOT_PATH === false) {
        throw new Exception('Raiz segura inválida no servidor.');
    }

    // --- aceitar JSON body e popular $_REQUEST/$_POST ---
    $raw = @file_get_contents('php://input') ?: '';
    $maybeJson = null;
    if ($raw !== '') {
        $dec = @json_decode($raw, true);
        if (is_array($dec)) $maybeJson = $dec;
    }
    if (is_array($maybeJson)) {
        foreach ($maybeJson as $k => $v) {
            if (!isset($_REQUEST[$k])) $_REQUEST[$k] = $v;
            if (!isset($_POST[$k])) $_POST[$k] = $v;
        }
    }

    // debug inicial
    $dbg = [
        'time' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'get' => $_GET,
        'post' => $_POST,
        'request' => $_REQUEST,
        'raw_first' => substr($raw, 0, 4096)
    ];
    log_debug($dbg);

    // aliases para fonte e destino
    $srcParam = $_REQUEST['source_path'] ?? $_REQUEST['src'] ?? $_REQUEST['path'] ?? $_REQUEST['file'] ?? null;
    $dstParam = $_REQUEST['dest'] ?? $_REQUEST['dst'] ?? $_REQUEST['dest_dir'] ?? $_REQUEST['destDir'] ?? $_REQUEST['destination'] ?? null;
    $recursiveFlag = (isset($_REQUEST['recursive']) && in_array(strtolower((string)$_REQUEST['recursive']), ['1','true','yes'])) ? true : false;

    if (!is_string($srcParam) || trim($srcParam) === '') {
        json_error('Parâmetro "source_path" (origem) é obrigatório.', 400);
    }
    if (!is_string($dstParam) || trim($dstParam) === '') {
        json_error('Parâmetro "dst" / "dest" / "dest_dir" (destino) é obrigatório.', 400);
    }

    // sanitize strings
    $srcParam = str_replace("\0", '', trim($srcParam));
    $srcParam = preg_replace('#/{2,}#','/',$srcParam);
    if ($srcParam === '') json_error('Parâmetro de origem inválido.', 400);
    if ($srcParam[0] !== '/') $srcParam = '/' . ltrim($srcParam, '/');

    $dstParam = str_replace("\0", '', trim($dstParam));
    $dstParam = preg_replace('#/{2,}#','/',$dstParam);
    if ($dstParam === '') json_error('Parâmetro de destino inválido.', 400);
    // allow destParam with leading slash or without
    if ($dstParam[0] !== '/') $dstParam = '/' . ltrim($dstParam, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);

    // montar full paths (aceita relativo dentro de /home)
    if (strpos($srcParam, $safeRoot) === 0 || strpos($srcParam, '/home/') === 0) {
        $fullSrc = $srcParam;
    } else {
        $fullSrc = $safeRoot . '/' . ltrim($srcParam, '/');
    }
    $fullSrc = preg_replace('#/{2,}#','/',$fullSrc);

    if (strpos($dstParam, $safeRoot) === 0 || strpos($dstParam, '/home/') === 0) {
        $fullDstCandidate = $dstParam;
    } else {
        $fullDstCandidate = $safeRoot . '/' . ltrim($dstParam, '/');
    }
    $fullDstCandidate = preg_replace('#/{2,}#','/',$fullDstCandidate);

    log_debug(['action'=>'candidates','fullSrc'=>$fullSrc,'fullDstCandidate'=>$fullDstCandidate,'recursive'=>$recursiveFlag]);

    // canonicalize source
    $canonicalSrc = @realpath($fullSrc);
    if ($canonicalSrc === false || $canonicalSrc === null) {
        // tentar sudo realpath
        $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullSrc) . ' 2>/dev/null'));
        if ($sudoOut !== '') {
            $canonicalSrc = explode("\n", $sudoOut, 2)[0];
        }
    }

    if ($canonicalSrc === false || $canonicalSrc === null || $canonicalSrc === '') {
        json_error('Arquivo de origem não encontrado ou inacessível: ' . $fullSrc, 404);
    }

    // reforça jaula para origem
    $canonicalSrcSlash = rtrim($canonicalSrc, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
    if (strpos($canonicalSrcSlash, $safeRootSlash) !== 0) {
        json_error('Acesso negado: origem fora da raiz permitida.', 403);
    }

    // verificar existência e tipo da origem (se é dir ou file)
    $srcIsDir = false;
    if (@is_dir($canonicalSrc)) {
        $srcIsDir = true;
    } elseif (!@is_file($canonicalSrc)) {
        // se nem dir nem file: tentar com sudo test -e
        $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($canonicalSrc) . ' && echo yes || echo no'));
        if ($existsViaSudo !== 'yes') {
            json_error('Arquivo de origem não encontrado: ' . $canonicalSrc, 404);
        } else {
            // se existe via sudo, descobrir se é dir
            $isDirViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -d ' . escapeshellarg($canonicalSrc) . ' && echo yes || echo no'));
            if ($isDirViaSudo === 'yes') $srcIsDir = true;
        }
    }

    // --- Resolver destino final (dstTarget) ---
    // Se fullDstCandidate existe e é diretório -> dstTarget = dstDir + basename(src)
    $canonicalDst = @realpath($fullDstCandidate);
    $dstResolvedIsDir = false;
    if ($canonicalDst !== false && $canonicalDst !== null && $canonicalDst !== '') {
        // existe
        if (@is_dir($canonicalDst)) {
            $dstResolvedIsDir = true;
            $dstParent = $canonicalDst;
            $dstTarget = rtrim($canonicalDst, DIRECTORY_SEPARATOR) . '/' . basename($canonicalSrc);
        } else {
            // existe como arquivo -> treat as file path
            $dstTarget = $canonicalDst;
            $dstParent = dirname($canonicalDst);
        }
    } else {
        // não existe: se dstParam termina com '/' ou user passou dest_dir param -> tratar como diretório a ser criado
        if (substr($fullDstCandidate, -1) === '/' || isset($_REQUEST['dest_dir']) || isset($_REQUEST['destDir'])) {
            // dst parent = parent of candidate (may be same as candidate if user intends to create)
            $dstParent = rtrim($fullDstCandidate, '/');
            // se parent empty -> safeRoot
            if ($dstParent === '') $dstParent = $safeRoot;
            // destino efetivo = candidate/basename(src)
            $dstTarget = rtrim($fullDstCandidate, '/') . '/' . basename($canonicalSrc);
            $dstResolvedIsDir = true;
        } else {
            // treat candidate as file path (maybe parent doesn't exist yet)
            $dstTarget = $fullDstCandidate;
            $dstParent = dirname($fullDstCandidate);
        }
    }

    // canonicalize parent (create if necessary)
    $canonicalDstParent = @realpath($dstParent);
    if ($canonicalDstParent === false || $canonicalDstParent === null || $canonicalDstParent === '') {
        // tentar criar via PHP se possível
        $mkOk = @mkdir($dstParent, 0755, true);
        if ($mkOk) {
            $canonicalDstParent = @realpath($dstParent);
        } else {
            // fallback sudo mkdir -p
            exec('/usr/bin/sudo -n /bin/mkdir -p ' . escapeshellarg($dstParent) . ' 2>&1', $mkOut, $mkCode);
            $mkOutStr = implode("\n", $mkOut);
            log_debug(['action'=>'sudo_mkdir_dstParent','cmd'=>'mkdir -p','dstParent'=>$dstParent,'exit'=>$mkCode,'out_preview'=>substr($mkOutStr,0,400)]);
            if ($mkCode === 0) {
                $sudoReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($dstParent) . ' 2>/dev/null'));
                if ($sudoReal !== '') $canonicalDstParent = explode("\n",$sudoReal,2)[0];
            }
        }
    }

    if ($canonicalDstParent === false || $canonicalDstParent === null || $canonicalDstParent === '') {
        json_error('Falha ao preparar o diretório de destino: ' . $dstParent, 500);
    }

    // reforçar jaula para destino
    $canonicalDstParentSlash = rtrim($canonicalDstParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($canonicalDstParentSlash, $safeRootSlash) !== 0) {
        json_error('Acesso negado: destino fora da raiz permitida.', 403);
    }

    // recompor dstTarget absoluto final (se foi relativo): garantir que está sob canonicalDstParent
    // se dstTarget começou com safeRoot já, manter. Caso contrário, join parent + basename if needed
    if (strpos($dstTarget, $safeRoot) !== 0 && strpos($dstTarget, '/home/') !== 0) {
        $dstTarget = rtrim($canonicalDstParent, DIRECTORY_SEPARATOR) . '/' . ltrim($dstTarget, '/');
    }
    $dstTarget = preg_replace('#/{2,}#','/',$dstTarget);

    log_debug(['action'=>'resolved','canonicalSrc'=>$canonicalSrc,'srcIsDir'=>$srcIsDir,'dstParent'=>$canonicalDstParent,'dstTarget'=>$dstTarget,'dstResolvedIsDir'=>$dstResolvedIsDir]);

    // se origem é diretório e não pediu recursivo -> erro
    if ($srcIsDir && !$recursiveFlag) {
        json_error('Origem é um diretório. Para copiar recursivamente use recursive=1.', 400);
    }

// checar wrapper — permitir execução via sudo mesmo que PHP não veja o bit exec
$wrapper = '/usr/local/bin/safe_copy.sh';
if (!file_exists($wrapper)) {
    json_error('Wrapper de cópia não encontrado em ' . $wrapper . '. Contate o administrador.', 500);
}
// se não é executável pelo PHP, registrar aviso e seguir em frente (sudo pode executar)
if (!is_executable($wrapper)) {
    log_debug(['warning'=>'wrapper_not_executable_by_php','wrapper'=>$wrapper,'note'=>'will attempt via sudo anyway']);
}


    // montar comando seguro: wrapper aceita --recursive como terceiro arg
    $recArg = $srcIsDir ? '--recursive' : '';
    $cmd = '/usr/bin/sudo -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonicalSrc) . ' ' . escapeshellarg($dstTarget);
    if ($recArg !== '') $cmd .= ' ' . escapeshellarg($recArg);
    $cmd .= ' 2>&1';

    log_debug(['action'=>'wrapper_exec','cmd'=>$cmd]);

    // executar e capturar saída e código
    exec($cmd, $outLines, $exitCode);
    $raw = implode("\n", $outLines);
    $rawTrim = trim($raw);
    log_debug(['action'=>'wrapper_finished','exit'=>$exitCode,'stdout_preview'=>substr($rawTrim,0,800)]);

    if ($exitCode !== 0) {
        // interpretar saída do wrapper
        $first = explode("\n", $rawTrim, 2)[0] ?? $rawTrim;
        // mapear códigos
        $map = [
            'missing' => 'Parâmetro ausente no wrapper',
            'dst_missing' => 'Parâmetro destino ausente no wrapper',
            'notfound' => 'Arquivo não encontrado (wrapper)',
            'forbidden' => 'Fora da raiz permitida (wrapper)',
            'src_is_dir' => 'Origem é diretório (wrapper) — use recursive',
            'rsync_missing' => 'Rsync ausente no servidor (wrapper)',
            'copy_failed' => 'Falha ao copiar (wrapper)',
            'mkdir_failed' => 'Falha ao criar diretório destino (wrapper)',
            'compress_failed' => 'Falha no compress/untar (wrapper)'
        ];
        if (strpos($first, 'ERR|') === 0) {
            $code = explode('|', $first, 2)[1] ?? 'unknown';
            $msg = $map[$code] ?? ('Erro do wrapper: ' . $code);
            // também anexar preview da saída para diagnósticos
            json_error($msg . ' — saída: ' . substr($rawTrim,0,800), 500);
        } else {
            json_error('Erro desconhecido do wrapper. Saída: ' . substr($rawTrim,0,800), 500);
        }
    }

    // wrapper exit 0 -> esperar OK|/path
    $first = explode("\n", $rawTrim, 2)[0] ?? $rawTrim;
    if (strpos($first, 'OK|') === 0) {
        $parts = explode('|', $first, 2);
        $finalPath = $parts[1] ?? $dstTarget;
        // normalizar relativo para frontend (remover safeRoot prefix)
        $rel = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($finalPath, strlen($safeRoot))), '/');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'path' => $rel, 'message' => 'Cópia concluída: ' . $finalPath], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    } else {
        json_error('Resposta inesperada do wrapper: ' . substr($rawTrim,0,800), 500);
    }

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=> $e->getTraceAsString()]);
    json_error('Erro interno: ' . $e->getMessage(), 500);
}
