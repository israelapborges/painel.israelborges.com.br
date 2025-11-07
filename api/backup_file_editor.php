<?php
// api/backup_file_editor.php
// Cria backup de um arquivo sob /home de forma segura.
// - aceita path via POST/GET ou JSON body (path, file, src, source_path, old_path)
// - canonicaliza com realpath + sudo realpath fallback
// - tenta copiar com PHP; se não for possível, usa sudo cp/install fallback
// - cria diretório de backups <parent>/.backups (com mkdir PHP ou sudo mkdir -p)
// - mantém owner/groupp do arquivo original (best-effort via sudo chown)
// - log em /tmp/backup_file_editor_debug.log

require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/backup_file_editor_debug.log';

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

    // ler raw body JSON se houver
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

    // log request
    $dbg = [
        'time' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'get' => $_GET,
        'post' => $_POST,
        'request' => $_REQUEST,
        'raw_input_first_4096' => substr($raw, 0, 4096)
    ];
    log_debug($dbg);

    // obter path (vários aliases)
    $candidate = $_POST['path'] ?? $_REQUEST['path'] ?? $_POST['file'] ?? $_REQUEST['file'] ?? $_POST['src'] ?? $_REQUEST['src'] ?? $_POST['source_path'] ?? $_REQUEST['source_path'] ?? $_POST['old_path'] ?? null;

    if (!is_string($candidate) || trim($candidate) === '') {
        json_error_and_exit('Parâmetro "path" é obrigatório (POST/GET/JSON).', 400);
    }

    // sanitize e normalize
    $candidate = str_replace("\0", '', trim($candidate));
    $candidate = preg_replace('#/{2,}#', '/', $candidate);
    if ($candidate === '') json_error_and_exit('Parâmetro "path" inválido.', 400);
    if ($candidate[0] !== '/') $candidate = '/' . ltrim($candidate, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);

    // monta fullPath (aceita relative under /home ou absolute)
    if (strpos($candidate, $safeRoot) === 0 || strpos($candidate, '/home/') === 0) {
        $fullPath = $candidate;
    } else {
        $fullPath = $safeRoot . '/' . ltrim($candidate, '/');
    }
    $fullPath = preg_replace('#/{2,}#', '/', $fullPath);

    // canonicalize com realpath; fallback sudo realpath
    $canonical = @realpath($fullPath);
    $usedFallback = null;
    if ($canonical === false || $canonical === null) {
        log_debug(['action'=>'realpath_failed_initial','attempted'=>$fullPath]);
        $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullPath) . ' 2>/dev/null'));
        if ($sudoOut !== '') {
            $canonical = explode("\n", $sudoOut, 2)[0];
            log_debug(['action'=>'sudo_realpath_succeeded','canonical'=>$canonical]);
        }
    }

    // se ainda não encontrou, tentar heurísticas user/htdocs/public_html
    if ($canonical === false || $canonical === null || $canonical === '') {
        if (preg_match('#^/([^/]+)/(.*)$#', $candidate, $m)) {
            $user = $m[1];
            $rest = $m[2] ?? '';
            $tryPaths = [
                $safeRoot . '/' . $user . '/htdocs/' . $rest,
                $safeRoot . '/' . $user . '/public_html/' . $rest,
                $safeRoot . '/' . $user . '/' . $rest
            ];
            foreach ($tryPaths as $p) {
                $p = preg_replace('#/{2,}#','/',$p);
                $rp = @realpath($p);
                if ($rp && $rp !== false) {
                    $canonical = $rp;
                    $usedFallback = $p;
                    log_debug(['action'=>'fallback_realpath_ok','tried'=>$p,'canonical'=>$rp]);
                    break;
                }
                $sudoTry = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($p) . ' 2>/dev/null'));
                if ($sudoTry !== '') {
                    $canonical = explode("\n",$sudoTry,2)[0];
                    $usedFallback = $p;
                    log_debug(['action'=>'fallback_sudo_realpath_ok','tried'=>$p,'canonical'=>$canonical]);
                    break;
                }
            }
        }
    }

    if ($canonical === false || $canonical === null || $canonical === '') {
        // última checagem via sudo test -e
        $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($fullPath) . ' && echo yes || echo no 2>/dev/null'));
        log_debug(['action'=>'realpath_final_failed','attempted'=>$fullPath,'exists_via_sudo'=>$existsViaSudo,'usedFallback'=>$usedFallback ?? null]);
        json_error_and_exit('Arquivo não encontrado: ' . $candidate, 404);
    }

    // reforça jaula (must be under /home)
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
    $canonicalSlash = rtrim($canonical, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($canonicalSlash, $safeRootSlash) !== 0) {
        log_debug(['action'=>'attempt_outside_root','canonical'=>$canonical,'safeRoot'=>$safeRoot]);
        json_error_and_exit('Acesso negado: fora da raiz permitida.', 403);
    }

    // verificar existência do arquivo; se file_exists() falhar, perguntar ao sudo
    $exists_locally = @file_exists($canonical);
    if (!$exists_locally) {
        $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($canonical) . ' && echo yes || echo no 2>/dev/null'));
        if ($existsViaSudo !== 'yes') {
            log_debug(['action'=>'file_missing_after_canonical','canonical'=>$canonical,'sudo_exists'=>$existsViaSudo]);
            json_error_and_exit('Arquivo não encontrado: ' . $candidate, 404);
        }
    }

    // must be regular file (or at least treat symlink/file)
    $is_file_locally = @is_file($canonical);
    if (!$is_file_locally) {
        $isFileViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -f ' . escapeshellarg($canonical) . ' && echo yes || echo no 2>/dev/null'));
        if ($isFileViaSudo !== 'yes') {
            log_debug(['action'=>'not_regular_file','canonical'=>$canonical,'is_file_local'=>$is_file_locally,'is_file_sudo'=>$isFileViaSudo]);
            json_error_and_exit('O caminho não é um arquivo regular.', 400);
        }
    }

    // metadados do arquivo original (best-effort)
    $origSize = @filesize($canonical);
    $origMtime = @filemtime($canonical);
    $origUid = @fileowner($canonical);
    $origGid = @filegroup($canonical);
    $origOwner = ($origUid !== false) ? (@posix_getpwuid($origUid)['name'] ?? null) : null;

    // preparar diretório de backups: <parent>/.backups
    $parent = rtrim(dirname($canonical), '/');
    if ($parent === '') $parent = $safeRoot;
    $backupsDir = $parent . '/.backups';
    $backupsDir = preg_replace('#/{2,}#','/',$backupsDir);

    // criar backupsDir se não existir
    if (!is_dir($backupsDir)) {
        $mkOk = @mkdir($backupsDir, 0755, true);
        if ($mkOk === false) {
            // fallback sudo mkdir -p
            $cmd = '/usr/bin/sudo -n /bin/mkdir -p ' . escapeshellarg($backupsDir) . ' 2>&1';
            $out = null;
            exec($cmd, $outLines, $exitCode);
            $out = implode("\n", $outLines);
            log_debug(['action'=>'sudo_mkdir_backups','cmd'=>$cmd,'exit'=>$exitCode,'out'=>$out]);
            if ($exitCode !== 0) {
                json_error_and_exit('Falha ao criar diretório de backups: ' . substr($out,0,300), 500);
            }
        }
    }

    // montar nome do backup: basename + timestamp (preserva extensão)
    $base = basename($canonical);
    // separar nome e ext
    $ext = '';
    $nameOnly = $base;
    if (strpos($base, '.') !== false) {
        $pos = strrpos($base, '.');
        $nameOnly = substr($base, 0, $pos);
        $ext = substr($base, $pos); // inclui o ponto
    }
    $ts = date('Ymd-His');
    $backupName = $nameOnly . '-' . $ts . $ext;
    $backupFull = $backupsDir . '/' . $backupName;
    $backupFull = preg_replace('#/{2,}#','/',$backupFull);

    // tentativa 1: usar copy() do PHP (se source for legível)
    $copied = false;
    if (is_readable($canonical)) {
        $ok = @copy($canonical, $backupFull);
        if ($ok) {
            $copied = true;
            // tentar preservar perm/owner (best-effort)
            @chmod($backupFull, 0644);
            if ($origUid !== false && $origGid !== false) {
                // tentar chown como sudo (names OR numeric)
                $chownCmd = '/usr/bin/sudo -n /usr/bin/chown ' . intval($origUid) . ':' . intval($origGid) . ' ' . escapeshellarg($backupFull) . ' 2>/dev/null';
                @shell_exec($chownCmd);
            }
        } else {
            log_debug(['action'=>'php_copy_failed','src'=>$canonical,'dst'=>$backupFull,'err'=>error_get_last()]);
        }
    } else {
        log_debug(['action'=>'source_not_readable_by_php','path'=>$canonical]);
    }

    // tentativa 2: fallback via sudo cp or install
    if (!$copied) {
        $cmd = '/usr/bin/sudo -n /bin/cp -p ' . escapeshellarg($canonical) . ' ' . escapeshellarg($backupFull) . ' 2>&1';
        exec($cmd, $outLines, $exitCode);
        $out = implode("\n", $outLines);
        log_debug(['action'=>'sudo_cp_attempt','cmd'=>$cmd,'exit'=>$exitCode,'out_preview'=>substr($out,0,400)]);
        if ($exitCode === 0) {
            $copied = true;
        } else {
            // tentar install -m 0644 src dst
            $installCmd = '/usr/bin/sudo -n /usr/bin/install -m 0644 ' . escapeshellarg($canonical) . ' ' . escapeshellarg($backupFull) . ' 2>&1';
            exec($installCmd, $instOut, $instCode);
            $instOutStr = implode("\n",$instOut);
            log_debug(['action'=>'sudo_install_attempt','cmd'=>$installCmd,'exit'=>$instCode,'out_preview'=>substr($instOutStr,0,400)]);
            if ($instCode === 0) {
                $copied = true;
            } else {
                // tentar escrever via cat (ler via sudo e gravar tmp -> mv)
                $tmp = tempnam(sys_get_temp_dir(), 'bkp_');
                if ($tmp !== false) {
                    $catCmd = '/usr/bin/sudo -n /bin/cat ' . escapeshellarg($canonical) . ' > ' . escapeshellarg($tmp) . ' 2>&1';
                    exec($catCmd, $catOut, $catCode);
                    $catOutStr = implode("\n",$catOut);
                    log_debug(['action'=>'sudo_cat_to_tmp','cmd'=>$catCmd,'exit'=>$catCode,'out_preview'=>substr($catOutStr,0,400)]);
                    if ($catCode === 0 && file_exists($tmp)) {
                        $mvCmd = '/usr/bin/sudo -n /bin/mv ' . escapeshellarg($tmp) . ' ' . escapeshellarg($backupFull) . ' 2>&1';
                        exec($mvCmd, $mvOut, $mvCode);
                        $mvOutStr = implode("\n",$mvOut);
                        log_debug(['action'=>'sudo_mv_tmp_to_dest','cmd'=>$mvCmd,'exit'=>$mvCode,'out_preview'=>substr($mvOutStr,0,400)]);
                        if ($mvCode === 0) {
                            $copied = true;
                        } else {
                            @unlink($tmp);
                        }
                    } else {
                        @unlink($tmp);
                    }
                }
            }
        }
    }

    if (!$copied) {
        log_debug(['action'=>'backup_failed_all','src'=>$canonical,'dst'=>$backupFull]);
        json_error_and_exit('Falha ao criar backup do arquivo. Verifique sudoers/permissões.', 500);
    }

    // sucesso: retornar path relativo e mensagem
    $relBackup = str_replace(DIRECTORY_SEPARATOR, '/', substr($backupFull, strlen($safeRoot)));
    $res = [
        'success' => true,
        'backup_full' => $backupFull,
        'backup_path' => $relBackup,
        'message' => 'Backup criado com sucesso.',
        'size' => @filesize($backupFull)
    ];
    log_debug(['action'=>'backup_created','src'=>$canonical,'backup'=>$backupFull,'relative'=>$relBackup]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    json_error_and_exit('Erro interno: ' . $e->getMessage(), 500);
}
