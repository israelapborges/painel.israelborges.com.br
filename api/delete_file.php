<?php
// api/delete_file.php
// Deletar arquivo / diretório com fallback privilegiado (sudo -n rm -rf) quando necessário.
// Regras:
// - path obrigatório
// - se path é diretório e não vazio -> erro salvo a menos que recursive=1 ou force=1
// - se PHP não tem permissão, tenta sudo realpath / sudo rm
require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/delete_file_debug.log';

function log_debug($o) {
    global $LOGFILE;
    $s = is_string($o) ? $o : json_encode($o, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents($LOGFILE, '['.date('c').'] '.$s.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function json_error_and_exit($msg, $code = 400, $extra = []) {
    while (ob_get_length()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $out = array_merge(['success' => false, 'error' => $msg], $extra);
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function json_success_and_exit($msg, $path = null, $stdout = '') {
    while (ob_get_length()) @ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $res = ['success' => true, 'message' => $msg];
    if ($path !== null) $res['path'] = $path;
    if ($stdout !== '') $res['stdout'] = $stdout;
    echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

try {
    if (SAFE_ROOT_PATH === false) throw new Exception('Raiz segura inválida no servidor.');

    // read JSON body if present
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

    $dbg = [
        'time'=>date('c'),
        'remote_addr'=>$_SERVER['REMOTE_ADDR'] ?? null,
        'method'=>$_SERVER['REQUEST_METHOD'] ?? null,
        'content_type'=>$_SERVER['CONTENT_TYPE'] ?? null,
        'get'=>$_GET,'post'=>$_POST,'request'=>$_REQUEST,
        'raw_input_first_4096'=>substr($raw,0,4096)
    ];
    log_debug($dbg);

    // params
    $pathRaw = $_REQUEST['path'] ?? $_REQUEST['src'] ?? $_REQUEST['target'] ?? null;
    if (!is_string($pathRaw) || trim($pathRaw) === '') {
        json_error_and_exit('Parâmetro "path" é obrigatório.', 400);
    }
    $recursive = false;
    if (isset($_REQUEST['recursive']) && in_array(strtolower((string)$_REQUEST['recursive']), ['1','true','yes'])) $recursive = true;
    // legacy alias
    if (isset($_REQUEST['force']) && in_array(strtolower((string)$_REQUEST['force']), ['1','true','yes'])) $recursive = true;

    // normalize
    $pathRaw = str_replace("\0",'',trim($pathRaw));
    $pathRaw = preg_replace('#/{2,}#','/',$pathRaw);
    if ($pathRaw === '') json_error_and_exit('Parâmetro "path" inválido.', 400);
    if ($pathRaw[0] !== '/') $pathRaw = '/' . ltrim($pathRaw, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;

    // build absolute candidate
    if (strpos($pathRaw, $safeRoot) === 0 || strpos($pathRaw, '/home/') === 0) $fullPathCandidate = $pathRaw;
    else $fullPathCandidate = $safeRoot . '/' . ltrim($pathRaw, '/');
    $fullPathCandidate = preg_replace('#/{2,}#','/',$fullPathCandidate);

    log_debug(['action'=>'candidate','candidate'=>$fullPathCandidate,'recursive'=>$recursive]);

    // try realpath (may fail if file exists only via sudo or not readable)
    $canonical = @realpath($fullPathCandidate);
    if ($canonical === false || $canonical === null) {
        log_debug(['action'=>'realpath_failed_initial','attempted'=>$fullPathCandidate]);
        $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullPathCandidate) . ' 2>/dev/null'));
        if ($sudoOut !== '') {
            $canonical = explode("\n",$sudoOut,2)[0];
            log_debug(['action'=>'sudo_realpath_ok','canonical'=>$canonical]);
        }
    } else {
        log_debug(['action'=>'realpath_ok','canonical'=>$canonical]);
    }

    if ($canonical === false || $canonical === null || $canonical === '') {
        json_error_and_exit('Arquivo/diretório não encontrado: ' . $pathRaw, 404);
    }

    // enforce safe root jail
    $canonicalSlash = rtrim($canonical, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($canonicalSlash, $safeRootSlash) !== 0) {
        log_debug(['action'=>'outside_root_attempt','canonical'=>$canonical,'safeRoot'=>$safeRoot]);
        json_error_and_exit('Acesso negado: fora da raiz permitida.', 403);
    }

    // determine type
    $isDir = is_dir($canonical);
    $isFile = is_file($canonical);
    // If PHP cannot stat but sudo realpath succeeded, we should detect existence via sudo test
    if (!$isDir && !$isFile) {
        $existsViaSudo = trim(@shell_exec('/usr/bin/sudo -n /bin/test -e ' . escapeshellarg($canonical) . ' && echo yes || echo no'));
        if ($existsViaSudo === 'yes') {
            // try to detect type via sudo
            $isDir = (trim(@shell_exec('/usr/bin/sudo -n /usr/bin/test -d ' . escapeshellarg($canonical) . ' && echo yes || echo no')) === 'yes');
            $isFile = (trim(@shell_exec('/usr/bin/sudo -n /usr/bin/test -f ' . escapeshellarg($canonical) . ' && echo yes || echo no')) === 'yes');
            log_debug(['action'=>'exists_only_via_sudo','canonical'=>$canonical,'isDir'=>$isDir,'isFile'=>$isFile]);
        }
    }

    // if directory, check emptiness
    if ($isDir) {
        $dirNotEmpty = false;
        // try PHP iterator if readable
        if (is_readable($canonical)) {
            try {
                $it = new FilesystemIterator($canonical, FilesystemIterator::SKIP_DOTS);
                $dirNotEmpty = $it->valid();
            } catch (Throwable $t) {
                log_debug(['action'=>'dir_iter_error','err'=>$t->getMessage()]);
                // fallback to sudo ls
                $count = (int) trim(@shell_exec('/usr/bin/sudo -n bash -lc "ls -A ' . escapeshellarg($canonical) . ' 2>/dev/null | wc -l"'));
                $dirNotEmpty = ($count > 0);
            }
        } else {
            // not readable by PHP -> use sudo to count
            $count = (int) trim(@shell_exec('/usr/bin/sudo -n bash -lc "ls -A ' . escapeshellarg($canonical) . ' 2>/dev/null | wc -l"'));
            $dirNotEmpty = ($count > 0);
        }

        if ($dirNotEmpty && !$recursive) {
            log_debug(['action'=>'dir_not_empty','canonical'=>$canonical]);
            json_error_and_exit('Diretório não vazio (use recursive=1 para forçar) — saída: ERR|dir_not_empty', 409, ['stdout'=>'ERR|dir_not_empty']);
        }
    }

    // Attempt deletion with PHP when possible (files & empty dirs and writable)
    $deleted = false;
    $deleteStdout = '';

    if ($isFile) {
        if (is_writable($canonical) && is_readable($canonical)) {
            if (@unlink($canonical)) {
                $deleted = true;
                log_debug(['action'=>'php_unlink_ok','path'=>$canonical]);
            } else {
                log_debug(['action'=>'php_unlink_failed','path'=>$canonical,'err'=>error_get_last()]);
            }
        } else {
            log_debug(['action'=>'php_unlink_not_attempted','reason'=>'not_writable_or_not_readable']);
        }
    } elseif ($isDir) {
        // empty dir delete via rmdir if writable by PHP
        if (is_writable($canonical) && is_readable($canonical)) {
            if (@rmdir($canonical)) {
                $deleted = true;
                log_debug(['action'=>'php_rmdir_ok','path'=>$canonical]);
            } else {
                log_debug(['action'=>'php_rmdir_failed','path'=>$canonical,'err'=>error_get_last()]);
            }
        } else {
            log_debug(['action'=>'php_rmdir_not_attempted','reason'=>'not_writable_or_not_readable']);
        }
    }

    // If not deleted yet, fallback to sudo rm -rf (only if recursive requested or target was file/empty dir)
    if (!$deleted) {
        // If target is dir and recursive not set (should be caught earlier), we won't delete
        if ($isDir && !$recursive) {
            json_error_and_exit('Diretório não vazio (use recursive=1 para forçar) — saída: ERR|dir_not_empty', 409, ['stdout'=>'ERR|dir_not_empty']);
        }

        // Use sudo rm -rf
        $cmd = '/usr/bin/sudo -n /bin/rm -rf ' . escapeshellarg($canonical) . ' 2>&1';
        log_debug(['action'=>'sudo_rm_cmd','cmd'=>$cmd]);
        exec($cmd, $outLines, $exitCode);
        $out = implode("\n",$outLines);
        log_debug(['action'=>'sudo_rm_finished','exit'=>$exitCode,'out_preview'=>substr($out,0,1000)]);
        $deleteStdout = $out;
        if ($exitCode === 0) {
            $deleted = true;
        } else {
            // If sudo failed with non-zero, return error with stdout for debugging
            json_error_and_exit('Falha ao deletar com privilégios. Saída: ' . substr($out,0,800), 500, ['stdout'=>$out]);
        }
    }

    if ($deleted) {
        // respond with relative path
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($canonical, strlen($safeRoot)));
        json_success_and_exit('Removido com sucesso.', $rel, $deleteStdout);
    } else {
        json_error_and_exit('Falha ao remover (não foi realizado nenhum método).', 500);
    }

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    json_error_and_exit('Erro interno: ' . $e->getMessage(), 500);
}
