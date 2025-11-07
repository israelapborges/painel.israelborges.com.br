<?php
// api/save_file_content.php
// Salva/atualiza o conteúdo de um arquivo sob /home de forma segura.
// - aceita path via POST/GET ou JSON body (path, file, src, source_path, old_path)
// - aceita content (texto) ou content_base64 (string base64) no body
// - canonicaliza diretório pai com realpath + sudo fallback
// - tenta escrever com PHP; se falhar, usa fallback sudo install via tmp file
// - log em /tmp/save_file_content_debug.log

require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/save_file_content_debug.log';

function log_debug($o) {
    global $LOGFILE;
    $s = is_string($o) ? $o : json_encode($o, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents($LOGFILE, '['.date('c').'] '.$s.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function json_error_and_exit($msg, $code = 400) {
    while (ob_get_length()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

try {
    if (SAFE_ROOT_PATH === false) {
        throw new Exception('Raiz segura inválida no servidor.');
    }

    // ler raw body JSON se houver e popular $_POST/$_REQUEST
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

    // tiny debug header
    $dbg = [
        'time' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'get' => $_GET,
        'post' => $_POST,
        'request' => $_REQUEST,
        'files_keys' => (!empty($_FILES) ? array_keys($_FILES) : []),
        'raw_input_first_4096' => substr($raw, 0, 4096)
    ];
    log_debug($dbg);

    // obter path (vários aliases)
    $candidate = $_POST['path'] ?? $_REQUEST['path'] ?? $_POST['file'] ?? $_REQUEST['file'] ?? $_POST['src'] ?? $_REQUEST['src'] ?? $_POST['source_path'] ?? $_REQUEST['source_path'] ?? $_POST['old_path'] ?? null;

    if (!is_string($candidate) || trim($candidate) === '') {
        json_error_and_exit('Parâmetro "path" necessário (POST).', 400);
    }

    // obter conteúdo: content OR content_base64 OR multipart file 'file'
    $content = null;
    if (isset($_POST['content_base64']) && is_string($_POST['content_base64'])) {
        $content = base64_decode($_POST['content_base64'], true);
        if ($content === false) json_error_and_exit('Parâmetro content_base64 inválido.');
    } elseif (isset($_POST['content']) && is_string($_POST['content'])) {
        $content = (string) $_POST['content'];
    } elseif (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $content = @file_get_contents($_FILES['file']['tmp_name']);
        if ($content === false) json_error_and_exit('Falha ao ler arquivo enviado.');
    } else {
        json_error_and_exit('Parâmetro "content" (ou content_base64 / multipart file) é obrigatório.', 400);
    }

    // sanitizar e normalizar candidate
    $candidate = str_replace("\0", '', trim($candidate));
    $candidate = preg_replace('#/{2,}#','/',$candidate);
    if ($candidate === '') json_error_and_exit('Parâmetro "path" inválido.', 400);
    if ($candidate[0] !== '/') $candidate = '/' . ltrim($candidate, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);

    // aceitar absolute under /home ou relative under safeRoot
    if (strpos($candidate, $safeRoot) === 0 || strpos($candidate, '/home/') === 0) {
        $fullPath = $candidate;
    } else {
        $fullPath = $safeRoot . '/' . ltrim($candidate, '/');
    }
    $fullPath = preg_replace('#/{2,}#','/',$fullPath);

    // diretório pai
    $parent = rtrim(dirname($fullPath), '/');
    if ($parent === '') $parent = $safeRoot;

    // tentar canonicalizar o diretório pai com realpath (pode falhar se não existir)
    $canonicalParent = @realpath($parent);
    if ($canonicalParent === false || $canonicalParent === null) {
        // tentar sudo realpath
        $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($parent) . ' 2>/dev/null'));
        if ($sudoOut !== '') {
            $canonicalParent = explode("\n", $sudoOut, 2)[0];
            log_debug(['action'=>'sudo_realpath_parent','parent'=>$parent,'canonicalParent'=>$canonicalParent]);
        }
    }

    // se parent não existe, tentar criar
    if ($canonicalParent === false || $canonicalParent === null || $canonicalParent === '') {
        // tentar criar com PHP
        $mkdirOk = @mkdir($parent, 0755, true);
        if ($mkdirOk) {
            $canonicalParent = @realpath($parent);
        } else {
            // fallback: sudo mkdir -p
            $cmd = '/usr/bin/sudo -n /bin/mkdir -p ' . escapeshellarg($parent) . ' 2>&1';
            $out = trim(@shell_exec($cmd));
            log_debug(['action'=>'sudo_mkdir_attempt','cmd'=>$cmd,'out'=>$out]);
            // tentar realpath via sudo
            $sudoOut2 = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($parent) . ' 2>/dev/null'));
            if ($sudoOut2 !== '') {
                $canonicalParent = explode("\n", $sudoOut2, 2)[0];
            } else {
                // tentar realpath local (talvez criado)
                $canonicalParent = @realpath($parent);
            }
        }
    }

    if ($canonicalParent === false || $canonicalParent === null || $canonicalParent === '') {
        log_debug(['action'=>'parent_canonical_failed','parent'=>$parent]);
        json_error_and_exit('Falha ao preparar diretório destino. Contate o administrador.', 500);
    }

    // reforçar jaula: canonicalParent deve estar dentro de SAFE_ROOT
    $canonicalParentSlash = rtrim($canonicalParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
    if (strpos($canonicalParentSlash, $safeRootSlash) !== 0) {
        log_debug(['action'=>'attempt_outside_root','canonicalParent'=>$canonicalParent]);
        json_error_and_exit('Acesso negado: fora da raiz permitida.', 403);
    }

    // montar caminho final canônico (note: o arquivo pode não existir ainda)
    $canonicalPath = $canonicalParent . '/' . basename($fullPath);
    $canonicalPath = preg_replace('#/{2,}#','/',$canonicalPath);

    // metadados: modo preferencial se já existir
    $existingMode = null;
    if (file_exists($canonicalPath)) {
        $perms = @fileperms($canonicalPath);
        if ($perms !== false) {
            $existingMode = substr(sprintf('%o', $perms), -4);
        }
    } else {
        // se não existir, tentar inferir permissões do diretório pai
        $permsParent = @fileperms($canonicalParent);
        if ($permsParent !== false) {
            $existingMode = '0644';
        } else {
            $existingMode = '0644';
        }
    }

    // tentativa 1: escrever com PHP (file_put_contents + LOCK_EX)
    $wrote = false;
    $writeErr = null;
    // usar @ para evitar warnings expostos
    $bytes = @file_put_contents($canonicalPath, $content, LOCK_EX);
    if ($bytes !== false) {
        $wrote = true;
        // tentar ajustar permissão (apenas se falhar o comportamento desejado)
        @chmod($canonicalPath, intval($existingMode, 8));
    } else {
        $writeErr = error_get_last();
        log_debug(['action'=>'php_write_failed','path'=>$canonicalPath,'err'=>$writeErr]);
    }

    // tentativa 2: fallback via sudo install (escrever tmp -> install)
    if (!$wrote) {
        // criar tmp file local
        $tmp = tempnam(sys_get_temp_dir(), 'save_');
        if ($tmp === false) {
            log_debug(['action'=>'tmp_creation_failed']);
            json_error_and_exit('Falha interna: não foi possível criar arquivo temporário.', 500);
        }
        $ok = @file_put_contents($tmp, $content);
        if ($ok === false) {
            @unlink($tmp);
            log_debug(['action'=>'tmp_write_failed','tmp'=>$tmp]);
            json_error_and_exit('Falha interna: não foi possível gravar arquivo temporário.', 500);
        }

        // permissões desejadas (usar existingMode)
        $mode = $existingMode ?? '0644';
        // usar install para mover com modo (evita prompt)
        $installCmd = '/usr/bin/sudo -n /usr/bin/install -m ' . escapeshellarg($mode) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($canonicalPath) . ' 2>&1';
        log_debug(['action'=>'sudo_install_cmd','cmd'=>$installCmd]);
        exec($installCmd, $outLines, $exitCode);
        $out = implode("\n", $outLines);
        if ($exitCode !== 0) {
            // tentar mv como fallback
            $mvCmd = '/usr/bin/sudo -n /bin/mv ' . escapeshellarg($tmp) . ' ' . escapeshellarg($canonicalPath) . ' 2>&1';
            exec($mvCmd, $mvOut, $mvCode);
            $mvOutStr = implode("\n", $mvOut);
            if ($mvCode !== 0) {
                // remover tmp e reportar erro
                @unlink($tmp);
                log_debug(['action'=>'sudo_install_failed','install_out'=>$out,'install_code'=>$exitCode,'mv_out'=>$mvOutStr,'mv_code'=>$mvCode]);
                json_error_and_exit('Falha ao gravar arquivo com privilégios elevados. Saída: ' . substr($out . "\n" . $mvOutStr, 0, 800), 500);
            } else {
                // successo via mv
                $wrote = true;
            }
        } else {
            // sucesso via install
            $wrote = true;
        }

        // se foi escrito com sucesso via sudo, ajustar dono para o do diretório pai (best-effort)
        if ($wrote) {
            $parentOwner = @fileowner($canonicalParent);
            $parentGroup = @filegroup($canonicalParent);
            if ($parentOwner !== false && $parentGroup !== false) {
                $chownCmd = '/usr/bin/sudo -n /usr/bin/chown ' . intval($parentOwner) . ':' . intval($parentGroup) . ' ' . escapeshellarg($canonicalPath) . ' 2>/dev/null';
                @shell_exec($chownCmd);
            }
        }
    }

    if (!$wrote) {
        json_error_and_exit('Falha ao gravar o arquivo. Verifique permissões e sudoers.', 500);
    }

    // limpar caches e respondendo
    clearstatcache(true, $canonicalPath);
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($canonicalPath, strlen($safeRoot)));
    $res = [
        'success' => true,
        'path' => $relative,
        'message' => 'Arquivo salvo com sucesso.',
        'bytes' => @filesize($canonicalPath)
    ];
    // log success
    log_debug(['action'=>'file_saved','path'=>$canonicalPath,'relative'=>$relative,'bytes'=>@$res['bytes']]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    json_error_and_exit('Erro interno: ' . $e->getMessage(), 500);
}
