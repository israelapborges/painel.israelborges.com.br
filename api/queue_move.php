<?php
// api/queue_move.php
// Move file/dir with privilege fallback (PHP rename -> wrapper -> sudo mv -> sudo cp+rm).
require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/queue_move_debug.log';

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

function json_success_and_exit($msg, $path = null, $method = null, $stdout = '') {
    while (ob_get_length()) @ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $res = ['success' => true, 'message' => $msg];
    if ($path !== null) $res['path'] = $path;
    if ($method !== null) $res['method'] = $method;
    if ($stdout !== '') $res['stdout'] = $stdout;
    echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

try {
    if (SAFE_ROOT_PATH === false) throw new Exception('Raiz segura inválida no servidor.');

    // read raw JSON body and merge into request arrays
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

    // parameters
    $srcRaw = $_REQUEST['src'] ?? $_REQUEST['source_path'] ?? $_REQUEST['path'] ?? null;
    if (!is_string($srcRaw) || trim($srcRaw) === '') json_error_and_exit('Parâmetro "src" (ou path/source_path) é obrigatório.', 400);

    // dst param (accept dest_dir OR dest/dst)
    $dstKeys = ['dest_dir','dest','dst','dest_path'];
    $dstParamKey = null;
    foreach ($dstKeys as $k) if (array_key_exists($k, $_REQUEST)) { $dstParamKey = $k; break; }
    if ($dstParamKey === null && is_array($maybeJson)) {
        foreach ($dstKeys as $k) if (array_key_exists($k, $maybeJson)) { $dstParamKey = $k; break; }
    }
    $dstRaw = null;
    if ($dstParamKey !== null) $dstRaw = $_REQUEST[$dstParamKey] ?? ($maybeJson[$dstParamKey] ?? null);
    if ($dstRaw === null) $dstRaw = $_REQUEST['dst'] ?? $_REQUEST['dest'] ?? $_REQUEST['dest_path'] ?? null;
    if (!is_string($dstRaw) || trim($dstRaw) === '') json_error_and_exit('Parâmetro "dst" (destino) é obrigatório.', 400);

    $overwrite = isset($_REQUEST['overwrite']) && in_array(strtolower((string)$_REQUEST['overwrite']), ['1','true','yes']);
    $recursive = isset($_REQUEST['recursive']) && in_array(strtolower((string)$_REQUEST['recursive']), ['1','true','yes']);

    // sanitize
    $srcRaw = str_replace("\0",'',trim($srcRaw));
    $srcRaw = preg_replace('#/{2,}#','/',$srcRaw);
    if ($srcRaw === '') json_error_and_exit('Parâmetro "src" inválido.', 400);
    if ($srcRaw[0] !== '/') $srcRaw = '/' . ltrim($srcRaw, '/');

    $dstRaw = str_replace("\0",'',trim($dstRaw));
    $dstRaw = preg_replace('#/{2,}#','/',$dstRaw);
    if ($dstRaw === '') json_error_and_exit('Parâmetro "dst" inválido.', 400);
    if ($dstRaw[0] !== '/') $dstRaw = '/' . ltrim($dstRaw, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;

    // build absolute candidates
    if (strpos($srcRaw, $safeRoot) === 0 || strpos($srcRaw, '/home/') === 0) $fullSrc = $srcRaw;
    else $fullSrc = $safeRoot . '/' . ltrim($srcRaw, '/');
    $fullSrc = preg_replace('#/{2,}#','/',$fullSrc);

    if (strpos($dstRaw, $safeRoot) === 0 || strpos($dstRaw, '/home/') === 0) $fullDstCandidate = $dstRaw;
    else $fullDstCandidate = $safeRoot . '/' . ltrim($dstRaw, '/');
    $fullDstCandidate = preg_replace('#/{2,}#','/',$fullDstCandidate);

    $dstParamWasDir = ($dstParamKey === 'dest_dir');
    $fullDstEndsWithSlash = (substr($fullDstCandidate, -1) === '/');

    log_debug(['action'=>'candidates','fullSrc'=>$fullSrc,'fullDstCandidate'=>$fullDstCandidate,'dstParamKey'=>$dstParamKey,'dstParamWasDir'=>$dstParamWasDir,'overwrite'=>$overwrite]);

    // canonicalize source (try php realpath then sudo realpath)
    $canonicalSrc = @realpath($fullSrc);
    if ($canonicalSrc === false || $canonicalSrc === null) {
        log_debug(['action'=>'realpath_src_failed','attempted'=>$fullSrc]);
        $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullSrc) . ' 2>/dev/null'));
        if ($sudoOut !== '') $canonicalSrc = explode("\n",$sudoOut,2)[0];
    }
    if ($canonicalSrc === false || $canonicalSrc === null || $canonicalSrc === '') json_error_and_exit('Origem não encontrada: ' . $srcRaw, 404);

    // security: ensure src inside safe root
    $canonicalSrcSlash = rtrim($canonicalSrc, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($canonicalSrcSlash, $safeRootSlash) !== 0) json_error_and_exit('Acesso negado: origem fora da raiz permitida.', 403);

    $srcIsDir = is_dir($canonicalSrc);
    $srcIsFile = is_file($canonicalSrc);

    // resolve destination similar to queue_copy logic
    $dstCanonical = @realpath($fullDstCandidate);
    $dstIsDir = ($dstCanonical !== false && is_dir($dstCanonical));
    $dstIsFile = ($dstCanonical !== false && is_file($dstCanonical));

    if ($dstParamWasDir || $fullDstEndsWithSlash) {
        if ($dstIsDir) {
            $dstParent = $dstCanonical;
        } else {
            // create dir (sudo)
            $mkdirCmd = '/usr/bin/sudo -n /bin/mkdir -p ' . escapeshellarg($fullDstCandidate) . ' 2>&1';
            exec($mkdirCmd, $mkOut, $mkCode);
            $mkOutStr = implode("\n",$mkOut);
            log_debug(['action'=>'sudo_mkdir_dst','cmd'=>$mkdirCmd,'exit'=>$mkCode,'out_preview'=>substr($mkOutStr,0,400)]);
            if ($mkCode !== 0) json_error_and_exit('Falha ao criar diretório de destino: ' . substr($mkOutStr,0,400), 500);
            $sudoReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullDstCandidate) . ' 2>/dev/null'));
            if ($sudoReal === '') json_error_and_exit('Falha ao resolver diretório de destino.', 500);
            $dstParent = $sudoReal;
        }
        $dstTarget = rtrim($dstParent,'/') . '/' . basename($canonicalSrc);
    } elseif ($dstIsDir) {
        $dstParent = $dstCanonical;
        $dstTarget = rtrim($dstParent,'/') . '/' . basename($canonicalSrc);
    } elseif ($dstIsFile) {
        $dstTarget = $dstCanonical;
        $dstParent = dirname($dstTarget);
    } else {
        // parent heuristics: ensure parent exists (sudo mkdir if needed)
        $parentCandidate = dirname($fullDstCandidate);
        $parentCanonical = @realpath($parentCandidate);
        if ($parentCanonical === false || $parentCanonical === null) {
            $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($parentCandidate) . ' 2>/dev/null'));
            if ($sudoOut !== '') $parentCanonical = explode("\n",$sudoOut,2)[0];
        }
        if ($parentCanonical === false || $parentCanonical === null || $parentCanonical === '') {
            $mkdirCmd = '/usr/bin/sudo -n /bin/mkdir -p ' . escapeshellarg($parentCandidate) . ' 2>&1';
            exec($mkdirCmd, $mkOut, $mkCode);
            $mkOutStr = implode("\n",$mkOut);
            log_debug(['action'=>'sudo_mkdir_dstParent','cmd'=>$mkdirCmd,'exit'=>$mkCode,'out_preview'=>substr($mkOutStr,0,400)]);
            if ($mkCode !== 0) json_error_and_exit('Falha ao preparar diretório de destino: ' . substr($mkOutStr,0,400), 500);
            $sudoReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($parentCandidate) . ' 2>/dev/null'));
            if ($sudoReal === '') json_error_and_exit('Falha ao resolver diretório de destino.', 500);
            $parentCanonical = $sudoReal;
        }
        $dstBasename = basename($fullDstCandidate);
        $srcBasename = basename($canonicalSrc);
        $treatAsDir = ($dstBasename === $srcBasename);
        if ($treatAsDir) {
            $dstParent = $parentCanonical;
            $dstTarget = rtrim($dstParent,'/') . '/' . basename($canonicalSrc);
        } else {
            $dstParent = $parentCanonical;
            $dstTarget = rtrim($dstParent,'/') . '/' . basename($fullDstCandidate);
        }
    }

    // ensure dstParent inside safe root
    $dstParentSlash = rtrim($dstParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($dstParentSlash, $safeRootSlash) !== 0) json_error_and_exit('Acesso negado: destino fora da raiz permitida.', 403);

    log_debug(['action'=>'resolved','canonicalSrc'=>$canonicalSrc,'srcIsDir'=>$srcIsDir,'dstParent'=>$dstParent,'dstTarget'=>$dstTarget]);

    // check destination existence (php or sudo)
    $existsDst = file_exists($dstTarget) || (trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($dstTarget) . ' && echo yes || echo no')) === 'yes');
    if ($existsDst && !$overwrite) json_error_and_exit('Destino já existe. Use overwrite=1 para substituir.', 409);

    // remove target if overwrite
    if ($existsDst && $overwrite) {
        $rmCmd = '/usr/bin/sudo -n /bin/rm -rf ' . escapeshellarg($dstTarget) . ' 2>&1';
        exec($rmCmd, $rmOut, $rmCode);
        log_debug(['action'=>'remove_existing_target','cmd'=>$rmCmd,'exit'=>$rmCode,'out_preview'=>substr(implode("\n",$rmOut),0,400)]);
    }

    // Try PHP rename() when possible (fast path)
    $moved = false;
    if ((is_writable($dstParent) || is_writable(dirname($dstParent))) && is_readable($canonicalSrc)) {
        clearstatcache(true, $canonicalSrc);
        try {
            if (@rename($canonicalSrc, $dstTarget)) {
                // success
                $moved = true;
                log_debug(['action'=>'php_rename_ok','src'=>$canonicalSrc,'dst'=>$dstTarget]);
                // adjust owner to parent owner best-effort
                $powner = @fileowner($dstParent);
                $pgroup = @filegroup($dstParent);
                if ($powner !== false && $pgroup !== false) {
                    @shell_exec('/usr/bin/sudo -n /usr/bin/chown ' . intval($powner) . ':' . intval($pgroup) . ' ' . escapeshellarg($dstTarget) . ' 2>/dev/null');
                }
                $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($dstTarget, strlen($safeRoot)));
                json_success_and_exit('Movido com rename PHP', $rel, 'php_rename', '');
            } else {
                log_debug(['action'=>'php_rename_failed','src'=>$canonicalSrc,'dst'=>$dstTarget,'err'=>error_get_last()]);
            }
        } catch (Throwable $th) {
            log_debug(['action'=>'php_rename_exception','msg'=>$th->getMessage()]);
        }
    } else {
        log_debug(['action'=>'php_rename_not_attempted','reason'=>'parent_not_writable_or_src_not_readable']);
    }

    // Fallback to wrapper or sudo mv
    $wrapper = '/usr/local/bin/safe_move.sh';
    if (!file_exists($wrapper)) {
        if (file_exists('/usr/local/bin/safe_rename.sh')) $wrapper = '/usr/local/bin/safe_rename.sh';
        else $wrapper = null;
    }
    if ($wrapper !== null) {
        if (!is_executable($wrapper)) {
            log_debug(['warning'=>'wrapper_not_executable_by_php','wrapper'=>$wrapper,'note'=>'calling via sudo anyway']);
        }
        $cmd = '/usr/bin/sudo -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonicalSrc) . ' ' . escapeshellarg($dstTarget) . ' 2>&1';
    } else {
        $cmd = '/usr/bin/sudo -n /bin/mv -T ' . escapeshellarg($canonicalSrc) . ' ' . escapeshellarg($dstTarget) . ' 2>&1';
    }

    log_debug(['action'=>'wrapper_exec','cmd'=>$cmd]);
    exec($cmd, $outLines, $exitCode);
    $out = implode("\n",$outLines);
    log_debug(['action'=>'wrapper_finished','exit'=>$exitCode,'stdout_preview'=>substr($out,0,1000)]);

    // If wrapper returned OK|/path, try to use that path
    $parsedPath = null;
    foreach ($outLines as $line) {
        $line = trim($line);
        if (strpos($line, 'OK|') === 0) {
            $parsedPath = substr($line, 3);
            break;
        }
    }

    if ($exitCode === 0) {
        // success path: prefer parsedPath if available
        if ($parsedPath !== null && $parsedPath !== '') {
            $dstTarget = $parsedPath;
        } else {
            $dstReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($dstTarget) . ' 2>/dev/null'));
            if ($dstReal !== '') $dstTarget = $dstReal;
        }
        // best-effort chown to parent owner
        $powner = @fileowner($dstParent);
        $pgroup = @filegroup($dstParent);
        if ($powner !== false && $pgroup !== false) {
            @shell_exec('/usr/bin/sudo -n /usr/bin/chown ' . intval($powner) . ':' . intval($pgroup) . ' ' . escapeshellarg($dstTarget) . ' 2>/dev/null');
        }
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($dstTarget, strlen($safeRoot)));
        json_success_and_exit('Movido com wrapper/sudo', $rel, 'sudo_mv', $out);
    }

    // Last resort: cp then rm
    log_debug(['action'=>'attempting_cp_then_rm_fallback']);
    if ($srcIsDir) {
        $cpCmd = '/usr/bin/sudo -n /bin/cp -a ' . escapeshellarg($canonicalSrc) . ' ' . escapeshellarg($dstTarget) . ' 2>&1';
    } else {
        $cpCmd = '/usr/bin/sudo -n /bin/cp -p ' . escapeshellarg($canonicalSrc) . ' ' . escapeshellarg($dstTarget) . ' 2>&1';
    }
    exec($cpCmd, $cpOut, $cpCode);
    $cpOutStr = implode("\n",$cpOut);
    log_debug(['action'=>'cp_attempt','cmd'=>$cpCmd,'exit'=>$cpCode,'out_preview'=>substr($cpOutStr,0,1000)]);
    if ($cpCode !== 0) {
        log_debug(['action'=>'cp_failed','out'=>$cpOutStr]);
        json_error_and_exit('Falha ao mover (cp fallback): ' . substr($cpOutStr,0,800), 500, ['stdout'=>$cpOutStr]);
    }
    // remove source
    $rmCmd = '/usr/bin/sudo -n /bin/rm -rf ' . escapeshellarg($canonicalSrc) . ' 2>&1';
    exec($rmCmd, $rmOut, $rmCode);
    $rmOutStr = implode("\n",$rmOut);
    log_debug(['action'=>'rm_after_cp','cmd'=>$rmCmd,'exit'=>$rmCode,'out_preview'=>substr($rmOutStr,0,400)]);

    // resolve final target
    $dstReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($dstTarget) . ' 2>/dev/null'));
    if ($dstReal !== '') $dstTarget = $dstReal;

    // chown best-effort to parent owner
    $powner = @fileowner($dstParent);
    $pgroup = @filegroup($dstParent);
    if ($powner !== false && $pgroup !== false) {
        @shell_exec('/usr/bin/sudo -n /usr/bin/chown ' . intval($powner) . ':' . intval($pgroup) . ' ' . escapeshellarg($dstTarget) . ' 2>/dev/null');
    }

    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($dstTarget, strlen($safeRoot)));
    json_success_and_exit('Movido com cp_then_rm', $rel, 'cp_then_rm', $cpOutStr . "\n" . $rmOutStr);

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    json_error_and_exit('Erro interno: ' . $e->getMessage(), 500);
}
