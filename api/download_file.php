<?php
// api/download_file.php - versão robusta e segura (2025-11-05)
// Regras:
// - aceita path via GET/POST/JSON body (aliases: path,file,src,source_path)
// - prioriza leitura direta (fopen/readfile) quando possível
// - se não legível, tenta wrapper (/usr/local/bin/safe_download.sh) via sudo -n
// - registra debug em /tmp/download_file_debug.log
// - só envia headers após validações (permitir devolver JSON de erro)

/** início configuração básica **/
require '../config/session_guard.php';

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));

function log_debug($obj) {
    $s = is_string($obj) ? $obj : json_encode($obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents('/tmp/download_file_debug.log', '['.date('c').'] ' . $s . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function reply_json_and_exit($arr, $code = 400) {
    while (ob_get_length()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/** PREP: ler corpo JSON para popular $_REQUEST */
$rawInput = @file_get_contents('php://input') ?: '';
$maybeJson = null;
if ($rawInput !== '') {
    $decoded = @json_decode($rawInput, true);
    if (is_array($decoded)) $maybeJson = $decoded;
}
if (is_array($maybeJson)) {
    foreach ($maybeJson as $k => $v) {
        if (!isset($_REQUEST[$k])) $_REQUEST[$k] = $v;
        if (!isset($_POST[$k])) $_POST[$k] = $v;
    }
}

/** DEBUG: registro request inicial */
$logEntry = [
    'time' => date('c'),
    'whoami_cli' => trim((string)@shell_exec('whoami 2>/dev/null')),
    'php_euid' => function_exists('posix_geteuid') ? posix_geteuid() : null,
    'php_effective_user' => (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) ? @posix_getpwuid(posix_geteuid())['name'] : null,
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    'get' => $_GET,
    'post' => $_POST,
    'maybeJson_keys' => is_array($maybeJson) ? array_keys($maybeJson) : null,
    'raw_input_first_2048' => substr($rawInput, 0, 2048),
];
log_debug($logEntry);

/** obter candidate path (aliases) */
$candidate = $_REQUEST['path'] ?? $_REQUEST['file'] ?? $_REQUEST['src'] ?? $_REQUEST['source_path'] ?? $_REQUEST['old_path'] ?? ($_GET['path'] ?? null);
if (!is_string($candidate) || trim($candidate) === '') {
    reply_json_and_exit(['success' => false, 'error' => 'Parâmetro "path" obrigatório (aliases: path,file,src,source_path).'], 400);
}

/** normaliza candidate */
$candidate = str_replace("\0", '', trim($candidate));
$candidate = preg_replace('#/{2,}#', '/', $candidate);
if ($candidate === '') reply_json_and_exit(['success' => false, 'error' => 'Parâmetro "path" inválido.'], 400);
if ($candidate[0] !== '/') $candidate = '/' . ltrim($candidate, '/');

/** monta fullPath aceitando relativos sob SAFE_ROOT_PATH */
if (strpos($candidate, SAFE_ROOT_PATH) === 0 || strpos($candidate, '/home/') === 0) {
    $fullPath = $candidate;
} else {
    $fullPath = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR) . '/' . ltrim($candidate, '/');
}
$fullPath = preg_replace('#/{2,}#', '/', $fullPath);

/** canonicaliza com realpath, fallback sudo realpath */
$canonical = @realpath($fullPath);
if ($canonical === false || $canonical === null) {
    $sudoReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullPath) . ' 2>/dev/null'));
    if ($sudoReal !== '') $canonical = explode("\n", $sudoReal, 2)[0];
}
if ($canonical === false || $canonical === null || $canonical === '') {
    $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($fullPath) . ' && echo yes || echo no'));
    log_debug(['action'=>'canonical_fail', 'fullPath'=>$fullPath, 'existsViaSudo'=>$existsViaSudo]);
    reply_json_and_exit(['success'=>false, 'error'=>'Caminho não encontrado ou inacessível. exists_via_sudo='.$existsViaSudo, 'attempted'=>$fullPath], 404);
}

/** reforça jaula */
$safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);
$safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
$candidateSlash = rtrim($canonical, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($candidateSlash, $safeRootSlash) !== 0) {
    log_debug(['action'=>'outside_root', 'canonical'=>$canonical, 'safeRoot'=>$safeRoot]);
    reply_json_and_exit(['success'=>false, 'error'=>'Acesso negado: fora da raiz permitida.'], 403);
}

// verificar existência — se o PHP não consegue ver, tente via sudo
$exists_only_via_sudo = false;

if (!file_exists($canonical)) {
    // testar existência como root (via sudo). devolve "yes" ou "no"
    $existsViaSudo = trim((string)@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($canonical) . ' && echo yes || echo no 2>/dev/null'));
    if ($existsViaSudo === 'yes') {
        // o ficheiro existe, mas o processo PHP não consegue vê-lo — vamos forçar o uso do wrapper
        log_debug(['action'=>'exists_only_via_sudo','canonical'=>$canonical]);
        $exists_only_via_sudo = true;
    } else {
        // realmente não existe (nem via sudo)
        log_debug(['action'=>'notfound_after_realpath','canonical'=>$canonical,'exists_via_sudo'=>$existsViaSudo]);
        reply_json_and_exit(['success'=>false, 'error'=>'Arquivo não encontrado: ' . $candidate], 404);
    }
}


/** nome do arquivo para Content-Disposition */
$fileName = basename($canonical);
$fileName = preg_replace('/[^\p{L}\p{N}\-\._@() ]+/u', '_', $fileName);

/** TRY 1: se legível e é ficheiro, servir diretamente via fopen/readfile */
if (is_file($canonical) && is_readable($canonical) && !$exists_only_via_sudo) {
    $size = @filesize($canonical);
    // limpar buffers antes de enviar
    while (ob_get_length()) @ob_end_clean();
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    $cd = 'attachment; filename="' . addcslashes($fileName, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($fileName);
    header('Content-Disposition: ' . $cd);
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    if ($size !== false) header('Content-Length: ' . $size);

    $fh = @fopen($canonical, 'rb');
    if ($fh === false) {
        // log e fallback para wrapper
        log_debug(['action'=>'fopen_failed_but_readable', 'canonical'=>$canonical, 'whoami'=>trim((string)@shell_exec('whoami 2>/dev/null'))]);
        // prossegue para tentativa do wrapper abaixo
    } else {
        // stream
        set_time_limit(0);
        while (!feof($fh)) {
            $buf = fread($fh, 8192);
            if ($buf === false) break;
            echo $buf;
            flush();
        }
        fclose($fh);
        exit;
    }
}

/** AQUI: arquivo não é legível diretamente pelo PHP - vamos tentar wrapper via sudo */
log_debug(['action'=>'attempt_wrapper', 'canonical'=>$canonical, 'whoami'=>trim((string)@shell_exec('whoami 2>/dev/null'))]);

// validações básicas para usar wrapper
$wrapper = '/usr/local/bin/safe_download.sh';
$sudoBin = '/usr/bin/sudo';

// check: proc_open existence
if (!function_exists('proc_open')) {
    log_debug('proc_open_disabled');
    reply_json_and_exit(['success'=>false, 'error'=>'Operação privilegiada requerida mas proc_open não disponível no PHP-FPM. Contate o administrador.'], 500);
}

// quick sudo -n check to see if user can run passwordless sudo at all
$checkCmd = $sudoBin . ' -n /usr/bin/true';
$desc = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
$procCheck = @proc_open($checkCmd, $desc, $pipesCheck);
$checkRc = 1;
$checkErr = '';
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
    log_debug(['action'=>'sudo_check_failed', 'rc'=>$checkRc, 'stderr'=>trim($checkErr)]);
    reply_json_and_exit(['success'=>false, 'error'=>'Execução privilegiada (sudo -n) não disponível para o processo. Verifique sudoers para o usuário do pool.','detail'=>trim($checkErr)], 403);
}

// Checar se root pode executar o wrapper (não usar is_executable() porque testa permissões do user atual)
$wrapperCheckCmd = $sudoBin . ' -n /usr/bin/test -x ' . escapeshellarg($wrapper) . ' && echo OK || echo NO 2>/dev/null';
$wrapperCheckOut = trim(@shell_exec($wrapperCheckCmd));

if ($wrapperCheckOut !== 'OK') {
    // Log com detalhe: se sudo -n falhou, talvez sudoers/privilégios não estejam disponíveis
    log_debug([
        'action' => 'wrapper_missing_or_not_exec_via_sudo',
        'wrapper' => $wrapper,
        'sudo_check_cmd' => $wrapperCheckCmd,
        'sudo_check_out' => $wrapperCheckOut,
        'whoami' => trim((string)@shell_exec('whoami 2>/dev/null'))
    ]);
    reply_json_and_exit([
        'success' => false,
        'error' => 'Wrapper ausente ou não executável pelo root, ou execução via sudo não permitida ao processo. Verifique /usr/local/bin/safe_download.sh e sudoers.'
    ], 500);
}


// Start wrapper via proc_open
$cmd = $sudoBin . ' -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonical);
$descs = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
$proc = @proc_open($cmd, $descs, $pipes);
if (!is_resource($proc)) {
    log_debug(['action'=>'proc_open_wrapper_failed','cmd'=>$cmd]);
    reply_json_and_exit(['success'=>false,'error'=>'Falha ao iniciar processo privilegiado (proc_open). Verifique permissões e sudoers.'], 500);
}

// read immediate stderr preview non-blocking
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
$stderrPreview = stream_get_contents($pipes[2], 4096);
if ($stderrPreview !== '') {
    fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
    $rc = proc_close($proc);
    log_debug(['action'=>'wrapper_immediate_stderr','stderr'=>trim($stderrPreview),'rc'=>$rc,'cmd'=>$cmd]);
    reply_json_and_exit(['success'=>false,'error'=>'Erro ao iniciar wrapper: '.trim($stderrPreview)], 500);
}

// now send headers and stream stdout
while (ob_get_length()) @ob_end_clean();
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
$cd = 'attachment; filename="' . addcslashes($fileName, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($fileName);
header('Content-Disposition: ' . $cd);
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

set_time_limit(0);
stream_set_blocking($pipes[1], true);
stream_set_blocking($pipes[2], true);
while (!feof($pipes[1])) {
    $chunk = fread($pipes[1], 8192);
    if ($chunk === false) break;
    echo $chunk;
    flush();
}
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
$exitCode = proc_close($proc);
log_debug(['action'=>'wrapper_stream_finished','exit'=>$exitCode,'stderr_preview'=>substr($stderr,0,200),'cmd'=>$cmd]);

if ($exitCode !== 0) {
    // não conseguimos devolver JSON agora (headers já enviados) => registrar e encerrar
    exit;
}

exit;
