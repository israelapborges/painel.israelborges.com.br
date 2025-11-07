<?php
// api/queue_extract_file.php
// Versão com detecção de formato + teste de integridade ZIP apenas quando
// o processo PHP pode ler o ficheiro. Caso contrário, pula o teste e usa o wrapper.
// Logs em /tmp/queue_extract_file_debug.log

require '../config/session_guard.php';

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));

function log_debug($obj) {
    $s = is_string($obj) ? $obj : json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents('/tmp/queue_extract_file_debug.log', '['.date('c').'] ' . $s . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function reply_json_and_exit($arr, $code = 200) {
    while (ob_get_length()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/* parse JSON body (if present) to populate $_REQUEST */
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

/* initial debug entry */
$logEntry = [
    'time' => date('c'),
    'whoami_cli' => trim((string)@shell_exec('whoami 2>/dev/null')),
    'php_euid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
    'php_effective_user' => (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) ? @posix_getpwuid(posix_geteuid())['name'] : null,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    'request' => $_REQUEST,
    'raw_input_first_2048' => substr($raw, 0, 2048),
];
log_debug($logEntry);

/* validate safe root */
if (SAFE_ROOT_PATH === false) {
    log_debug('safe_root_invalid');
    reply_json_and_exit(['success'=>false,'error'=>'Raiz segura inválida no servidor.'], 500);
}
$safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);
$safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;

/* read params */
$srcCandidate = $_REQUEST['path'] ?? $_REQUEST['source_path'] ?? $_REQUEST['src'] ?? null;
$destCandidate = $_REQUEST['dest'] ?? $_REQUEST['destination'] ?? $_REQUEST['target'] ?? null;
$formatParam = strtolower($_REQUEST['format'] ?? ($_REQUEST['type'] ?? ''));

/* required source */
if (!is_string($srcCandidate) || trim($srcCandidate) === '') {
    reply_json_and_exit(['success'=>false,'error'=>'Parâmetro "path" (origem) é obrigatório.'], 400);
}
$srcCandidate = str_replace("\0", '', trim($srcCandidate));
$srcCandidate = preg_replace('#/{2,}#', '/', $srcCandidate);
if ($srcCandidate === '') reply_json_and_exit(['success'=>false,'error'=>'Parâmetro "path" inválido.'], 400);
if ($srcCandidate[0] !== '/') $srcCandidate = '/' . ltrim($srcCandidate, '/');

/* default dest if not provided */
if (!is_string($destCandidate) || trim($destCandidate) === '') {
    $destCandidate = dirname($srcCandidate) . '/' . pathinfo($srcCandidate, PATHINFO_FILENAME) . '_extracted';
}
$destCandidate = str_replace("\0", '', trim($destCandidate));
$destCandidate = preg_replace('#/{2,}#', '/', $destCandidate);
if ($destCandidate === '') reply_json_and_exit(['success'=>false,'error'=>'Parâmetro "dest" inválido.'], 400);
if ($destCandidate[0] !== '/') $destCandidate = '/' . ltrim($destCandidate, '/');

/* build full fs paths (accept absolute under /home or relative under SAFE_ROOT) */
$fullSrc = (strpos($srcCandidate, $safeRoot) === 0 || strpos($srcCandidate, '/home/') === 0) ? $srcCandidate : $safeRoot . '/' . ltrim($srcCandidate, '/');
$fullSrc = preg_replace('#/{2,}#','/',$fullSrc);

$fullDest = (strpos($destCandidate, $safeRoot) === 0 || strpos($destCandidate, '/home/') === 0) ? $destCandidate : $safeRoot . '/' . ltrim($destCandidate, '/');
$fullDest = preg_replace('#/{2,}#','/',$fullDest);

/* canonicalize source via realpath, fallback to sudo realpath */
$canonicalSrc = @realpath($fullSrc);
$exists_only_via_sudo = false;
if ($canonicalSrc === false || $canonicalSrc === null) {
    $sudoReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullSrc) . ' 2>/dev/null'));
    if ($sudoReal !== '') $canonicalSrc = explode("\n",$sudoReal,2)[0];
}
if ($canonicalSrc === false || $canonicalSrc === null || $canonicalSrc === '') {
    // check existence via sudo test -e
    $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($fullSrc) . ' && echo yes || echo no 2>/dev/null'));
    if ($existsViaSudo === 'yes') {
        log_debug(['action'=>'src_exists_only_via_sudo','fullSrc'=>$fullSrc]);
        $exists_only_via_sudo = true;
        $canonicalSrc = $fullSrc;
    } else {
        log_debug(['action'=>'src_notfound','fullSrc'=>$fullSrc,'existsViaSudo'=>$existsViaSudo]);
        reply_json_and_exit(['success'=>false,'error'=>'Acesso negado: Caminho de origem inválido. Arquivo não encontrado.'], 404);
    }
}

/* enforce source inside SAFE_ROOT */
$canonicalSrcSlash = rtrim($canonicalSrc, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($canonicalSrcSlash, $safeRootSlash) !== 0) {
    log_debug(['action'=>'src_outside_root','canonicalSrc'=>$canonicalSrc,'safeRoot'=>$safeRoot]);
    reply_json_and_exit(['success'=>false,'error'=>'Acesso negado: origem fora da raiz permitida.'], 403);
}

/* destination normalization and safety */
$fullDestNormalized = preg_replace('#/{2,}#','/',$fullDest);
$destAbs = $fullDestNormalized;
if (strpos($destAbs, $safeRoot) !== 0 && strpos($destAbs, '/home/') !== 0) {
    $destAbs = $safeRoot . '/' . ltrim($destAbs, '/');
}
$destAbs = preg_replace('#/{2,}#','/',$destAbs);
$destAbsSlash = rtrim($destAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($destAbsSlash, $safeRootSlash) !== 0) {
    log_debug(['action'=>'dest_outside_root','dest'=>$destAbs,'safeRoot'=>$safeRoot]);
    reply_json_and_exit(['success'=>false,'error'=>'Acesso negado: destino fora da raiz permitida.'], 403);
}
/* create dest parent if possible (non-privileged); wrapper will create if not */
$parent = dirname($destAbs);
if (!is_dir($parent)) {
    @mkdir($parent, 0775, true);
}

/* Try PHP ZipArchive extraction if possible */
$phpTried = false;
if (!$exists_only_via_sudo && is_file($canonicalSrc) && is_readable($canonicalSrc) && class_exists('ZipArchive')) {
    $formatLower = $formatParam !== '' ? $formatParam : '';
    if ($formatLower === '' || $formatLower === 'zip') {
        $phpTried = true;
        log_debug(['action'=>'attempt_php_zip','src'=>$canonicalSrc,'dest'=>$destAbs]);
        $zip = new ZipArchive();
        $res = $zip->open($canonicalSrc);
        if ($res === true || $res === ZipArchive::ER_OK) {
            if (!is_dir($destAbs)) {
                if (!@mkdir($destAbs, 0775, true) && !is_dir($destAbs)) {
                    log_debug(['action'=>'mkdir_failed','dest'=>$destAbs,'err'=>'mkdir returned false']);
                    $phpExtractFailed = true;
                } else {
                    $phpExtractFailed = false;
                }
            } else {
                $phpExtractFailed = false;
            }
            if (!$phpExtractFailed) {
                $ok = $zip->extractTo($destAbs);
                $zip->close();
                if ($ok) {
                    $rel = substr($destAbs, strlen($safeRoot));
                    if ($rel === '') $rel = '/';
                    $rel = str_replace(DIRECTORY_SEPARATOR,'/',$rel);
                    log_debug(['action'=>'php_zip_success','dest'=>$destAbs]);
                    reply_json_and_exit(['success'=>true,'path'=>$rel,'message'=>'Extraído com sucesso via ZipArchive.']);
                } else {
                    log_debug(['action'=>'php_zip_extract_failed','src'=>$canonicalSrc,'dest'=>$destAbs]);
                }
            }
        } else {
            log_debug(['action'=>'php_zip_open_failed','src'=>$canonicalSrc,'code'=>$res]);
        }
    }
}

/* DETECTION: mime/type -> format; test ZIP integrity only if PHP can read the file */
$detectedMime = trim(@shell_exec('/usr/bin/file --mime-type -b ' . escapeshellarg($canonicalSrc) . ' 2>/dev/null'));
if ($detectedMime === '') $detectedMime = 'unknown';

$mimeMap = [
    'application/zip' => 'zip',
    'application/x-zip' => 'zip',
    'application/x-gzip' => 'tar.gz',
    'application/gzip' => 'tar.gz',
    'application/x-tar' => 'tar',
    'application/x-xz' => 'tar',
    'application/x-bzip2' => 'tar',
    'application/octet-stream' => null,
];

if ($formatParam !== '') {
    $format = $formatParam;
} elseif (isset($mimeMap[$detectedMime]) && $mimeMap[$detectedMime] !== null) {
    $format = $mimeMap[$detectedMime];
} else {
    $ext = strtolower(pathinfo($canonicalSrc, PATHINFO_EXTENSION));
    $extMap = ['zip'=>'zip','tgz'=>'tar.gz','tar.gz'=>'tar.gz','tar'=>'tar','gz'=>'tar.gz','bz2'=>'tar','xz'=>'tar'];
    if (isset($extMap[$ext])) {
        $format = $extMap[$ext];
    } else {
        log_debug(['action'=>'mime_unknown','mime'=>$detectedMime,'ext'=>$ext,'src'=>$canonicalSrc]);
        reply_json_and_exit(['success'=>false,'error'=>'Arquivo não parece ser um arquivo compactado suportado (mime='.$detectedMime.' ext='.$ext.').'], 400);
    }
}

/* If zip, run quick integrity test only when PHP can read the file.
   If the file is only readable via sudo/root, SKIP the local test and let wrapper handle it. */
if ($format === 'zip') {
    if (!$exists_only_via_sudo && is_readable($canonicalSrc)) {
        $testCmd = '/usr/bin/unzip -t ' . escapeshellarg($canonicalSrc) . ' 2>&1';
        $testOutput = '';
        $testExit = null;

        if (function_exists('exec')) {
            $lines = [];
            @exec($testCmd, $lines, $testExit);
            $testOutput = implode("\n", $lines);
        } elseif (function_exists('shell_exec')) {
            $testOutput = trim(@shell_exec($testCmd));
            $testExit = null;
        } elseif (function_exists('proc_open')) {
            $descs = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
            $proc = @proc_open($testCmd, $descs, $pipes);
            if (is_resource($proc)) {
                fclose($pipes[0]);
                $tout = stream_get_contents($pipes[1]);
                $terr = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                $testOutput = trim($tout . "\n" . $terr);
                $testExit = proc_close($proc);
            }
        }

        log_debug(['action'=>'zip_test_output','src'=>$canonicalSrc,'testExit'=>$testExit,'testOutput_preview'=>substr($testOutput,0,1200)]);
        if ($testExit !== null && $testExit !== 0) {
            $short = substr($testOutput,0,1200);
            log_debug(['action'=>'zip_test_failed','src'=>$canonicalSrc,'testExit'=>$testExit,'testOutput'=>substr($testOutput,0,1200)]);
            reply_json_and_exit(['success'=>false,'error'=>'Arquivo ZIP inválido ou corrompido. unzip output: '.($short === '' ? '(sem saída do unzip)' : $short)], 400);
        }
    } else {
        // SKIP zip test: arquivo não legível pelo PHP ou existe apenas via sudo.
        log_debug(['action'=>'zip_test_skipped','reason'=> $exists_only_via_sudo ? 'exists_only_via_sudo' : 'not_readable_by_php','src'=>$canonicalSrc]);
        // proceed to wrapper (which runs as root)
    }
}

/* If here, either PHP Zip extraction failed or we must use wrapper */
log_debug(['action'=>'attempt_wrapper','src'=>$canonicalSrc,'dest'=>$destAbs,'exists_only_via_sudo'=>$exists_only_via_sudo,'format'=>$format]);

$wrapper = '/usr/local/bin/safe_extract.sh';
$sudoBin = '/usr/bin/sudo';

/* ensure proc_open available */
if (!function_exists('proc_open')) {
    log_debug('proc_open_disabled');
    reply_json_and_exit(['success'=>false,'error'=>'Operação privilegiada requerida mas proc_open não disponível no PHP-FPM.'], 500);
}

/* quick sudo -n check */
$checkCmd = $sudoBin . ' -n /usr/bin/true';
$desc = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
$procCheck = @proc_open($checkCmd, $desc, $pipesCheck);
$checkRc = 1; $checkErr = '';
if (is_resource($procCheck)) {
    fclose($pipesCheck[0]);
    stream_set_blocking($pipesCheck[2], false);
    $checkErr = stream_get_contents($pipesCheck[2]);
    fclose($pipesCheck[1]); fclose($pipesCheck[2]);
    $checkRc = proc_close($procCheck);
} else {
    log_debug('proc_open_check_failed');
}
if ($checkRc !== 0) {
    log_debug(['action'=>'sudo_check_failed','rc'=>$checkRc,'stderr'=>trim($checkErr)]);
    reply_json_and_exit(['success'=>false,'error'=>'Execução privilegiada (sudo -n) não disponível para o processo. Verifique sudoers para o usuário do pool.','detail'=>trim($checkErr)], 403);
}

/* verify wrapper executable by root (via sudo test -x) */
$wrapperCheckCmd = $sudoBin . ' -n /usr/bin/test -x ' . escapeshellarg($wrapper) . ' && echo OK || echo NO 2>/dev/null';
$wrapperCheckOut = trim(@shell_exec($wrapperCheckCmd));
if ($wrapperCheckOut !== 'OK') {
    log_debug(['action'=>'wrapper_missing_or_not_exec_via_sudo','wrapper'=>$wrapper,'check'=>$wrapperCheckCmd,'out'=>$wrapperCheckOut]);
    reply_json_and_exit(['success'=>false,'error'=>'Wrapper de extração ausente ou não executável pelo root, ou execução via sudo não permitida.'], 500);
}

/* Build and execute wrapper command: wrapper <archive> <dest> <format> */
$cmd = $sudoBin . ' -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonicalSrc) . ' ' . escapeshellarg($destAbs) . ' ' . escapeshellarg($format) . ' 2>&1';
log_debug(['action'=>'wrapper_exec','cmd'=>$cmd]);

$descs = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
$proc = @proc_open($cmd, $descs, $pipes);
if (!is_resource($proc)) {
    log_debug(['action'=>'proc_open_wrapper_failed','cmd'=>$cmd]);
    reply_json_and_exit(['success'=>false,'error'=>'Falha ao iniciar processo privilegiado (proc_open). Verifique permissões e sudoers.'], 500);
}

/* read all stdout/stderr (combined due to 2>&1 in cmd) */
$out = '';
while (!feof($pipes[1])) {
    $out .= fgets($pipes[1], 8192);
}
fclose($pipes[1]);

$stderr = '';
if (is_resource($pipes[2])) {
    while (!feof($pipes[2])) {
        $stderr .= fgets($pipes[2], 8192);
    }
    fclose($pipes[2]);
}

$exit = proc_close($proc);
$out = trim($out);
$stderr = trim($stderr);

log_debug(['action'=>'wrapper_finished','exit'=>$exit,'stdout_preview'=>substr($out,0,1024),'stderr_preview'=>substr($stderr,0,1024)]);

/* parse wrapper output expected: OK|<dest>  or ERR|code */
$firstLine = explode("\n",$out,2)[0] ?? $out;
if (stripos($firstLine, 'OK|') === 0) {
    $parts = explode('|',$firstLine,2);
    $realDest = $parts[1] ?? $destAbs;
    $realRel = substr($realDest, strlen($safeRoot));
    if ($realRel === '') $realRel = '/';
    $realRel = str_replace(DIRECTORY_SEPARATOR,'/',$realRel);
    reply_json_and_exit(['success'=>true,'path'=>$realRel,'message'=>'Extraído com sucesso (wrapper).']);
} else {
    $errCode = '';
    if (stripos($firstLine, 'ERR|') === 0) {
        $errCode = explode('|',$firstLine,2)[1] ?? 'unknown';
    }
    $map = [
        'missing' => 'Parâmetro alvo ausente',
        'notfound' => 'Arquivo de origem não encontrado',
        'forbidden' => 'Fora da raiz permitida',
        'extract_failed' => 'Falha ao extrair o arquivo',
        'notreadable' => 'Arquivo de origem não legível',
        'tmp_failed' => 'Falha ao criar temporário',
        'no_tool' => 'Ferramenta necessária ausente (zip/tar)',
        'extract_empty' => 'Arquivo extraído vazio'
    ];
    $msg = $map[$errCode] ?? ('Erro do wrapper: ' . ($errCode ?: $out));
    log_debug(['action'=>'wrapper_error','code'=>$errCode,'msg'=>$msg,'stdout'=>$out,'stderr'=>$stderr,'exit'=>$exit]);
    reply_json_and_exit(['success'=>false,'error'=>$msg,'detail'=>$out ?: $stderr], 500);
}
