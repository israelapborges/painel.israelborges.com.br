<?php
// api/queue_compress_file.php
// Substitua o arquivo existente por este.
// Cria um arquivo compactado a partir de um arquivo/diretório sob /home
// - aceita JSON body ou form params (aceita aliases: path|source|src|source_path)
// - aceita archive name aliases: dest|archive|archive_name|dest_archive
// - normaliza/canonicaliza com fallback sudo realpath
// - usa wrapper /usr/local/bin/safe_compress.sh via sudo -n quando possível
// - fallback PHP ZipArchive para zip quando wrapper não disponível e PHP pode ler fonte
// - logs em /tmp/queue_compress_file_debug.log

require '../config/session_guard.php';

// --- configurações ---
$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/queue_compress_file_debug.log';

// --- helpers ---
function log_debug($obj) {
    global $LOGFILE;
    $s = is_string($obj) ? $obj : json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents($LOGFILE, '['.date('c').'] ' . $s . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function reply_json_and_exit($arr, $code = 200) {
    while (ob_get_length()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// --- parse raw JSON body into $_REQUEST (non destructive) ---
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

// --- initial debug log ---
$logEntry = [
    'time' => date('c'),
    'whoami_cli' => trim((string)@shell_exec('whoami 2>/dev/null')),
    'php_euid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
    'php_effective_user' => (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) ? @posix_getpwuid(posix_geteuid())['name'] : null,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    'request' => $_REQUEST,
    'raw_input_first_4096' => substr($raw, 0, 4096),
];
log_debug($logEntry);

// --- validate safe root ---
if (SAFE_ROOT_PATH === false) {
    log_debug('safe_root_invalid');
    reply_json_and_exit(['success'=>false,'error'=>'Raiz segura inválida no servidor.'], 500);
}
$safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);
$safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;

// --- read params (support many aliases) ---
$srcCandidate = $_REQUEST['path'] ?? $_REQUEST['source'] ?? $_REQUEST['src'] ?? $_REQUEST['source_path'] ?? $_REQUEST['src_path'] ?? null;
$destCandidate = $_REQUEST['dest'] ?? $_REQUEST['archive'] ?? $_REQUEST['dest_archive'] ?? $_REQUEST['archive_name'] ?? $_REQUEST['archiveName'] ?? null;
$formatParam = strtolower(trim((string)($_REQUEST['format'] ?? $_REQUEST['type'] ?? 'zip')));
$force = (isset($_REQUEST['force']) && ($_REQUEST['force'] === '1' || $_REQUEST['force'] === 'true' || $_REQUEST['force'] === 'yes' || $_REQUEST['force'] === true));

// --- validations ---
if (!is_string($srcCandidate) || trim($srcCandidate) === '') {
    reply_json_and_exit(['success'=>false,'error'=>'Parâmetro "path" (origem) é obrigatório.'], 400);
}
$srcCandidate = str_replace("\0", '', trim($srcCandidate));
$srcCandidate = preg_replace('#/{2,}#', '/', $srcCandidate);
if ($srcCandidate === '') reply_json_and_exit(['success'=>false,'error'=>'Parâmetro "path" inválido.'], 400);
if ($srcCandidate[0] !== '/') $srcCandidate = '/' . ltrim($srcCandidate, '/');

$allowedFormats = ['zip','tar.gz','tar.bz2','tar.xz','tar'];
$format = in_array($formatParam, $allowedFormats, true) ? $formatParam : 'zip';

// --- canonicalize source (realpath, fallback sudo realpath) ---
$fullSrc = (strpos($srcCandidate, $safeRoot) === 0 || strpos($srcCandidate, '/home/') === 0) ? $srcCandidate : $safeRoot . '/' . ltrim($srcCandidate, '/');
$fullSrc = preg_replace('#/{2,}#','/',$fullSrc);

$canonicalSrc = @realpath($fullSrc);
$exists_only_via_sudo = false;
if ($canonicalSrc === false || $canonicalSrc === null) {
    $sudoReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullSrc) . ' 2>/dev/null'));
    if ($sudoReal !== '') $canonicalSrc = explode("\n",$sudoReal,2)[0];
}
if ($canonicalSrc === false || $canonicalSrc === null || $canonicalSrc === '') {
    // check existence via sudo
    $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($fullSrc) . ' && echo yes || echo no 2>/dev/null'));
    if ($existsViaSudo === 'yes') {
        log_debug(['action'=>'src_exists_only_via_sudo','fullSrc'=>$fullSrc]);
        $exists_only_via_sudo = true;
        $canonicalSrc = $fullSrc;
    } else {
        log_debug(['action'=>'src_notfound','fullSrc'=>$fullSrc,'existsViaSudo'=>$existsViaSudo]);
        reply_json_and_exit(['success'=>false,'error'=>'Arquivo ou diretório de origem não encontrado.'], 404);
    }
}

// ensure source under safe root
$canonicalSrcSlash = rtrim($canonicalSrc, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($canonicalSrcSlash, $safeRootSlash) !== 0) {
    log_debug(['action'=>'src_outside_root','canonicalSrc'=>$canonicalSrc,'safeRoot'=>$safeRoot]);
    reply_json_and_exit(['success'=>false,'error'=>'Acesso negado: origem fora da raiz permitida.'], 403);
}

// --- prepare destination archive path ---
if (is_string($destCandidate) && trim($destCandidate) !== '') {
    $destCandidate = str_replace("\0", '', trim($destCandidate));
    $destCandidate = preg_replace('#/{2,}#', '/', $destCandidate);
    if ($destCandidate[0] !== '/') {
        $baseDir = dirname($canonicalSrc);
        $destCandidate = $baseDir . '/' . ltrim($destCandidate, '/');
    }
    $fullDest = $destCandidate;
} else {
    $base = pathinfo($canonicalSrc, PATHINFO_FILENAME);
    $parent = dirname($canonicalSrc);
    $extMap = ['zip'=>'zip','tar.gz'=>'tar.gz','tar.bz2'=>'tar.bz2','tar.xz'=>'tar.xz','tar'=>'tar'];
    $ext = $extMap[$format] ?? 'zip';
    $fullDest = $parent . '/' . $base . '.' . $ext;
}
$fullDest = preg_replace('#/{2,}#','/',$fullDest);

// If destCandidate provided without extension, append extension
if (!empty($destCandidate)) {
    $basename = basename($fullDest);
    $hasDot = (strpos($basename, '.') !== false);
    $extMap = ['zip'=>'zip','tar.gz'=>'tar.gz','tar.bz2'=>'tar.bz2','tar.xz'=>'tar.xz','tar'=>'tar'];
    $wantedExt = $extMap[$format] ?? 'zip';
    if (!$hasDot) {
        $fullDest = rtrim(dirname($fullDest), '/') . '/' . $basename . '.' . $wantedExt;
    }
}

// canonicalize dest parent
$destParent = dirname($fullDest);
if (!is_dir($destParent)) {
    @mkdir($destParent, 0775, true);
}
$destParentReal = @realpath($destParent);
if ($destParentReal === false || $destParentReal === null) {
    $sudoRealParent = trim(@shell_exec('/usr/bin/sudo /bin/realpath -m -- ' . escapeshellarg($destParent) . ' 2>/dev/null'));
    $destParentReal = ($sudoRealParent !== '') ? $sudoRealParent : $destParent;
}
$canonicalDest = rtrim($destParentReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($fullDest);
$canonicalDest = preg_replace('#/{2,}#','/',$canonicalDest);

// ensure dest under safe root
$canonicalDestSlash = rtrim($canonicalDest, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($canonicalDestSlash, $safeRootSlash) !== 0) {
    log_debug(['action'=>'dest_outside_root','dest'=>$canonicalDest,'safeRoot'=>$safeRoot]);
    reply_json_and_exit(['success'=>false,'error'=>'Acesso negado: destino fora da raiz permitida.'], 403);
}

// if exists and not force => error
if (file_exists($canonicalDest) && !$force) {
    reply_json_and_exit(['success'=>false,'error'=>'Arquivo de destino já existe. Use force=1 para sobrescrever.'], 409);
}

// --- attempt wrapper via sudo ---
$wrapper = '/usr/local/bin/safe_compress.sh';
$sudoBin = '/usr/bin/sudo';

// check sudo -n availability
$sudoAvailable = false;
$checkCmd = $sudoBin . ' -n /usr/bin/true';
$checkRc = 1;
if (function_exists('proc_open')) {
    $desc = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $p = @proc_open($checkCmd, $desc, $pipesCheck);
    if (is_resource($p)) {
        @fclose($pipesCheck[0] ?? null);
        if (is_resource($pipesCheck[1] ?? null)) @fclose($pipesCheck[1]);
        if (is_resource($pipesCheck[2] ?? null)) @fclose($pipesCheck[2]);
        $checkRc = proc_close($p);
    } else {
        $out = @shell_exec($checkCmd . ' 2>&1');
        $checkRc = ($out === null) ? 1 : 0;
    }
    $sudoAvailable = ($checkRc === 0);
} else {
    $out = @shell_exec($checkCmd . ' 2>&1');
    $sudoAvailable = ($out !== null && $out === '');
}

// check wrapper executable via sudo
$wrapperOk = false;
if ($sudoAvailable) {
    $wrapperCheckCmd = $sudoBin . ' -n /usr/bin/test -x ' . escapeshellarg($wrapper) . ' && echo OK || echo NO 2>/dev/null';
    $wrapperCheckOut = trim(@shell_exec($wrapperCheckCmd));
    if ($wrapperCheckOut === 'OK') $wrapperOk = true;
}

// run wrapper if available
if ($sudoAvailable && $wrapperOk) {
    $cmd = $sudoBin . ' -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonicalSrc) . ' ' . escapeshellarg($canonicalDest) . ' ' . escapeshellarg($format) . ' 2>&1';
    log_debug(['action'=>'wrapper_exec','cmd'=>$cmd,'src'=>$canonicalSrc,'dest'=>$canonicalDest,'format'=>$format]);

    $descs = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $proc = @proc_open($cmd, $descs, $pipes);
    if (is_resource($proc)) {
        @fclose($pipes[0]);
        $out = '';
        $stderr = '';
        // read stdout non-blocking style until EOF
        while (!feof($pipes[1])) {
            $out .= fgets($pipes[1], 8192);
        }
        @fclose($pipes[1]);
        if (is_resource($pipes[2])) {
            while (!feof($pipes[2])) {
                $stderr .= fgets($pipes[2], 8192);
            }
            @fclose($pipes[2]);
        }
        $exit = proc_close($proc);
        log_debug(['action'=>'wrapper_finished','exit'=>$exit,'stdout_preview'=>substr($out,0,1200),'stderr_preview'=>substr($stderr,0,1200)]);

        // ----- Robust processing of wrapper output -----
        $archivePath = null;
        // 1) look for OK|<path> anywhere in stdout
        if (preg_match('/^OK\|(.+)$/m', $out, $m)) {
            $archivePath = trim($m[1]);
            log_debug(['action'=>'wrapper_detected_ok_line', 'archivePath'=>$archivePath]);
        } else {
            // 2) if exit 0, try to confirm archive existence (php-visible)
            if ($exit === 0) {
                if (file_exists($canonicalDest)) {
                    $archivePath = $canonicalDest;
                    log_debug(['action'=>'wrapper_exit0_file_exists','archivePath'=>$archivePath]);
                } else {
                    // 3) fallback try sudo realpath on canonicalDest (maybe perms hide it)
                    $sudoCheck = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($canonicalDest) . ' 2>/dev/null'));
                    if ($sudoCheck !== '') {
                        $archivePath = $sudoCheck;
                        log_debug(['action'=>'wrapper_exit0_sudo_realpath','archivePath'=>$archivePath]);
                    }
                }
            }
        }

        if ($archivePath !== null) {
            $archivePath = rtrim($archivePath, DIRECTORY_SEPARATOR);
            $rel = (strpos($archivePath, $safeRoot) === 0) ? substr($archivePath, strlen($safeRoot)) : $archivePath;
            if ($rel === '') $rel = '/';
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            reply_json_and_exit(['success'=>true,'path'=>$rel,'message'=>'Arquivo criado com sucesso (wrapper).']);
        }

        // If not success yet, try extract ERR|code from stdout or stderr
        $errCode = '';
        if (preg_match('/ERR\|([^\s]+)/', $out, $m)) {
            $errCode = $m[1];
        } elseif (preg_match('/ERR\|([^\s]+)/', $stderr, $m)) {
            $errCode = $m[1];
        } else {
            // maybe first line begins with ERR|
            $firstLine = explode("\n", trim($out), 2)[0] ?? '';
            if (stripos($firstLine, 'ERR|') === 0) {
                $errCode = explode('|', $firstLine, 2)[1] ?? 'unknown';
            }
        }

        $map = [
            'missing' => 'Parâmetro ausente',
            'notfound' => 'Fonte não encontrada',
            'forbidden' => 'Fora da raiz permitida',
            'no_tool' => 'Ferramenta de compressão ausente',
            'compress_failed' => 'Falha ao criar arquivo',
            'tmp_failed' => 'Falha ao criar temporário'
        ];

        if ($errCode !== '') {
            $msg = $map[$errCode] ?? ('Erro do wrapper: ' . $errCode);
            log_debug(['action'=>'wrapper_error','code'=>$errCode,'msg'=>$msg,'stdout'=>$out,'stderr'=>$stderr,'exit'=>$exit]);
            reply_json_and_exit(['success'=>false,'error'=>$msg,'detail'=>substr($out ?: $stderr,0,2000)], 500);
        }

        // fallback generic error with stdout/stderr
        log_debug(['action'=>'wrapper_unexpected_output','exit'=>$exit,'stdout'=>$out,'stderr'=>$stderr]);
        $msg = 'Resposta inesperada do wrapper. Saída: ' . substr($out ?: $stderr, 0, 2000);
        reply_json_and_exit(['success'=>false,'error'=>$msg], 500);
    } else {
        log_debug(['action'=>'proc_open_wrapper_failed','cmd'=>$cmd]);
        // fall through to fallback
    }
}

// --- fallback: try PHP ZipArchive (only for zip) ---
$canPhpZip = ($format === 'zip' && class_exists('ZipArchive') && (is_readable($canonicalSrc) || !$exists_only_via_sudo));
if ($canPhpZip) {
    log_debug(['action'=>'php_zip_fallback_start','src'=>$canonicalSrc,'dest'=>$canonicalDest]);
    $zip = new ZipArchive();
    $tmpArchive = $canonicalDest . '.tmp-' . getmypid();
    $openRes = $zip->open($tmpArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openRes !== true) {
        log_debug(['action'=>'php_zip_open_failed','code'=>$openRes]);
    } else {
        $addOk = true;
        $srcIsDir = is_dir($canonicalSrc);
        $srcBase = basename($canonicalSrc);
        if ($srcIsDir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($canonicalSrc, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $filePath = $file->getPathname();
                // local name should preserve subdirs relative to canonicalSrc parent
                $localName = substr($filePath, strlen(dirname($canonicalSrc)) + 1);
                if (!$zip->addFile($filePath, $localName)) {
                    $addOk = false;
                    log_debug(['action'=>'php_zip_add_failed','file'=>$filePath]);
                    break;
                }
            }
        } else {
            if (!$zip->addFile($canonicalSrc, $srcBase)) {
                $addOk = false;
                log_debug(['action'=>'php_zip_addfile_failed','file'=>$canonicalSrc]);
            }
        }
        $zip->close();
        if ($addOk && file_exists($tmpArchive)) {
            if ($force && file_exists($canonicalDest)) @unlink($canonicalDest);
            if (!@rename($tmpArchive, $canonicalDest)) {
                if (!@copy($tmpArchive, $canonicalDest)) {
                    @unlink($tmpArchive);
                    log_debug(['action'=>'php_zip_rename_failed','tmp'=>$tmpArchive,'dest'=>$canonicalDest]);
                    reply_json_and_exit(['success'=>false,'error'=>'Falha ao mover arquivo gerado pelo PHP ZipArchive.'], 500);
                } else {
                    @unlink($tmpArchive);
                }
            }
            // try set owner to source owner (best-effort)
            if (function_exists('posix_getpwuid')) {
                $uid = @fileowner($canonicalSrc);
                $gid = @filegroup($canonicalSrc);
                if ($uid !== false && $gid !== false) {
                    @chown($canonicalDest, $uid);
                    @chgrp($canonicalDest, $gid);
                }
            }
            $rel = substr($canonicalDest, strlen($safeRoot));
            if ($rel === '') $rel = '/';
            $rel = str_replace(DIRECTORY_SEPARATOR,'/',$rel);
            log_debug(['action'=>'php_zip_success','archive'=>$canonicalDest]);
            reply_json_and_exit(['success'=>true,'path'=>$rel,'message'=>'Arquivo criado com sucesso via ZipArchive (PHP fallback).']);
        } else {
            @unlink($tmpArchive);
            log_debug(['action'=>'php_zip_failed','addOk'=>$addOk]);
        }
    }
}

// --- no method available ---
log_debug(['action'=>'no_method_available','wrapperOk'=>$wrapperOk ?? false,'sudoAvailable'=>$sudoAvailable ?? false,'canPhpZip'=>$canPhpZip ?? false]);
reply_json_and_exit(['success'=>false,'error'=>'Não foi possível criar o arquivo: wrapper privilegiado indisponível e fallback não aplicável. Verifique sudoers e disponibilize /usr/local/bin/safe_compress.sh.'], 500);
