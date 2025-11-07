<?php
// api/list_files.php (versão robusta - garante saída JSON válida mesmo em caso de warning)
require '../config/session_guard.php';

ob_start(); // capturar qualquer saída acidental
// Suprimir warnings para a saída; vamos logar em arquivo
set_error_handler(function($severity, $message, $file, $line) {
    $logfile = '/tmp/list_files_debug.log';
    $entry = [
        'time' => date('c'),
        'error' => $message,
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ];
    @file_put_contents($logfile, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);
    // não interrompe a execução - evita enviar warnings para o cliente
    return true;
});

header('X-Content-Type-Options: nosniff');

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/list_files_debug.log';

function log_debug($o) {
    global $LOGFILE;
    $s = is_string($o) ? $o : json_encode($o, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents($LOGFILE, '['.date('c').'] '.$s.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function json_out_and_exit($data, $code = 200) {
    // limpar buffers gerados por warnings/printings
    while (ob_get_length()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

try {
    if (SAFE_ROOT_PATH === false) {
        throw new Exception('Raiz segura inválida no servidor.');
    }

    // ler raw JSON se houver e popular $_REQUEST/$_POST
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

    // debug inicial (leve)
    $dbg = [
        'time' => date('c'),
        'whoami_cli' => trim(@shell_exec('whoami')),
        'php_euid' => (function_exists('posix_geteuid') ? @posix_geteuid() : null),
        'php_effective_user' => (function_exists('posix_geteuid') && function_exists('posix_getpwuid') ? (@posix_getpwuid(posix_geteuid())['name'] ?? null) : get_current_user()),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'get' => $_GET,
        'post' => $_POST,
        'raw_input_first_4096' => substr($raw, 0, 4096)
    ];
    log_debug($dbg);

    // pegar path (aceita tanto com/sem /)
    $reqPath = $_REQUEST['path'] ?? ($_GET['path'] ?? null);
    if (!is_string($reqPath) || trim($reqPath) === '') {
        json_out_and_exit(['success' => false, 'error' => 'Parâmetro "path" é obrigatório.', 'files' => []], 400);
    }

    $candidate = trim(str_replace("\0", '', $reqPath));
    $candidate = preg_replace('#/{2,}#', '/', $candidate);

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);

    // if path is exactly '/' or empty -> map to safeRoot
    if ($candidate === '/' || $candidate === '') {
        $fullPath = $safeRoot;
    } else {
        // if user passed an absolute path starting with /home, accept; else build under safeRoot
        if (strpos($candidate, $safeRoot) === 0 || strpos($candidate, '/home/') === 0) {
            $fullPath = $candidate;
        } else {
            if ($candidate[0] !== '/') $candidate = '/' . ltrim($candidate, '/');
            $fullPath = $safeRoot . '/' . ltrim($candidate, '/');
        }
    }
    $fullPath = preg_replace('#/{2,}#', '/', $fullPath);

    log_debug(['action' => 'candidate_resolved', 'candidate' => $candidate, 'fullPath' => $fullPath]);

    // canonicalizar com realpath; fallback para sudo realpath
    $canonical = @realpath($fullPath);
    if ($canonical === false || $canonical === null) {
        $sudoCmd = '/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullPath) . ' 2>/dev/null';
        $sudoOut = trim(@shell_exec($sudoCmd));
        if ($sudoOut !== '') {
            $canonical = explode("\n", $sudoOut, 2)[0];
            log_debug(['action' => 'sudo_realpath_ok', 'cmd' => $sudoCmd, 'canonical' => $canonical]);
        }
    }

    if ($canonical === false || $canonical === null || $canonical === '') {
        log_debug(['action' => 'realpath_failed', 'attempted' => $fullPath]);
        json_out_and_exit(['success' => false, 'error' => 'Diretório não encontrado ou inacessível: ' . $reqPath, 'files' => []], 404);
    }

    // reforçar jaula
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
    $candidateSlash = rtrim($canonical, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($candidateSlash, $safeRootSlash) !== 0) {
        log_debug(['action' => 'outside_root', 'canonical' => $canonical, 'safeRoot' => $safeRoot]);
        json_out_and_exit(['success' => false, 'error' => 'Acesso negado: fora da raiz permitida.', 'files' => []], 403);
    }

    // montar results->path (com / na frente como no seu padrão original)
    $relativePath = substr($canonical, strlen($safeRoot));
    if ($relativePath === '') $relativePath = '/';
    $resultsPathForClient = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

    $files = [];

    // Tentar leitura via PHP se possível
    if (is_dir($canonical) && is_readable($canonical)) {
        $items = @scandir($canonical);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $fullItem = $canonical . DIRECTORY_SEPARATOR . $item;
                if (!file_exists($fullItem)) continue;

                $isDir = is_dir($fullItem);
                $uid = @fileowner($fullItem);
                $ownerInfo = ($uid !== false && function_exists('posix_getpwuid')) ? @posix_getpwuid($uid) : null;
                $ownerName = $ownerInfo ? ($ownerInfo['name'] ?? 'N/A') : 'N/A';
                $perms = @fileperms($fullItem);
                $permissions = ($perms !== false) ? substr(sprintf('%o', $perms), -3) : 'N/A';
                $mtime = @filemtime($fullItem) ?: 0;
                $size = $isDir ? 0 : (@filesize($fullItem) ?: 0);

                $itemRelativePath = ltrim($resultsPathForClient . '/' . $item, '/');
                $itemRelativePath = str_replace('//', '/', $itemRelativePath);

                $files[] = [
                    'name' => $item,
                    'path' => $itemRelativePath,
                    'type' => $isDir ? 'dir' : 'file',
                    'size' => $size,
                    'modified' => $mtime,
                    'owner' => $ownerName,
                    'permissions' => $permissions
                ];
            }
        }
    } else {
        log_debug(['action' => 'php_scandir_skipped', 'canonical' => $canonical, 'is_dir' => is_dir($canonical), 'is_readable' => is_readable($canonical)]);
    }

    // Se não obteve itens, tenta wrapper seguro (/usr/local/bin/safe_list.sh)
    if (empty($files)) {
        $wrapper = '/usr/local/bin/safe_list.sh';
        if (is_executable($wrapper)) {
            $cmd = '/usr/bin/sudo -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonical) . ' 2>&1';
            log_debug(['action' => 'wrapper_exec', 'cmd' => $cmd, 'canonical' => $canonical]);
            exec($cmd, $outLines, $exitCode);
            $stdout = implode("\n", $outLines);
            log_debug(['action' => 'wrapper_finished', 'exit' => $exitCode, 'stdout_preview' => substr($stdout, 0, 800)]);
            if ($exitCode === 0 && !empty($outLines)) {
                foreach ($outLines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '|') === false) continue;
                    $parts = explode('|', $line, 6);
                    if (count($parts) < 6) continue;
                    list($t, $name, $size, $mtime, $owner, $perm) = $parts;
                    $isDir = ($t === 'd');
                    $itemRelativePath = ltrim($resultsPathForClient . '/' . $name, '/');
                    $itemRelativePath = str_replace('//', '/', $itemRelativePath);

                    $files[] = [
                        'name' => $name,
                        'path' => $itemRelativePath,
                        'type' => $isDir ? 'dir' : 'file',
                        'size' => (int)$size,
                        'modified' => is_numeric($mtime) ? (int)$mtime : 0,
                        'owner' => $owner,
                        'permissions' => $perm
                    ];
                }
            } else {
                log_debug(['action' => 'wrapper_failed_or_empty', 'exit' => $exitCode, 'out_preview' => substr($stdout, 0, 800)]);
            }
        } else {
            log_debug(['action' => 'wrapper_not_exec', 'wrapper' => $wrapper]);
        }
    }

    // ordenar: pastas primeiro, depois arquivos, alfabeticamente
    usort($files, function($a, $b) {
        if ($a['type'] === $b['type']) return strcasecmp($a['name'], $b['name']);
        return ($a['type'] === 'dir') ? -1 : 1;
    });

    $response = [
        'success' => true,
        'path' => $resultsPathForClient,
        'files' => $files
    ];

    log_debug(['action' => 'reply', 'path' => $resultsPathForClient, 'count' => count($files)]);
    json_out_and_exit($response, 200);

} catch (Exception $e) {
    log_debug(['action' => 'exception', 'msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    json_out_and_exit(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage(), 'files' => []], 500);
}
