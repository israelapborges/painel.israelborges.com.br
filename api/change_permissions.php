<?php
// api/change_permissions.php
// Altera modo e (opcionalmente) owner de um arquivo/diretório.
// Versão atualizada: chown mais robusto (resolve username, fallback paths, melhor logging).

require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/change_permissions_debug.log';

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
    if (SAFE_ROOT_PATH === false) throw new Exception('Raiz segura inválida no servidor.');

    // merge json body into $_REQUEST/$_POST
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

    // debug request
    log_debug([
        'time' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'get' => $_GET,
        'post' => $_POST,
        'raw_input_first_4096' => substr($raw,0,4096)
    ]);

    // required param
    $candidate = $_REQUEST['path'] ?? $_REQUEST['file'] ?? null;
    if (!is_string($candidate) || trim($candidate) === '') {
        json_error_and_exit('Parâmetro "path" (origem) é obrigatório.', 400);
    }

    // sanitize path
    $candidate = str_replace("\0", '', trim($candidate));
    $candidate = preg_replace('#/{2,}#','/',$candidate);
    if ($candidate === '') json_error_and_exit('Parâmetro "path" inválido.', 400);
    if ($candidate[0] !== '/') $candidate = '/' . ltrim($candidate, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);

    // If candidate is not absolute under /home, prefix safe root
    if (strpos($candidate, $safeRoot) === 0 || strpos($candidate, '/home/') === 0) {
        $fullPath = $candidate;
    } else {
        $fullPath = $safeRoot . '/' . ltrim($candidate, '/');
    }
    $fullPath = preg_replace('#/{2,}#','/',$fullPath);

    // canonicalization: try realpath first, else sudo realpath
    $canonical = @realpath($fullPath);
    if ($canonical === false || $canonical === null) {
        log_debug(['action'=>'realpath_failed_initial','attempted'=>$fullPath]);
        $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullPath) . ' 2>/dev/null'));
        if ($sudoOut !== '') {
            $canonical = explode("\n", $sudoOut, 2)[0];
            log_debug(['action'=>'sudo_realpath_ok','canonical'=>$canonical]);
        }
    }

    if ($canonical === false || $canonical === null || $canonical === '') {
        json_error_and_exit('Arquivo/diretório não encontrado (realpath falhou).', 404);
    }

    // Ensure canonical inside safe root
    $canonicalSlash = rtrim($canonical, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
    if (strpos($canonicalSlash, $safeRootSlash) !== 0) {
        log_debug(['action'=>'outside_root_attempt','canonical'=>$canonical,'safeRoot'=>$safeRoot]);
        json_error_and_exit('Acesso negado: fora da raiz permitida.', 403);
    }

    // gather current stat (try PHP first)
    $curMode = null; $curOwner = null; $curGroup = null;
    if (file_exists($canonical) && ($st = @stat($canonical)) !== false) {
        $curMode = sprintf('%o', $st['mode'] & 0777);
        $curOwner = @posix_getpwuid($st['uid'])['name'] ?? $st['uid'];
        $curGroup = @posix_getgrgid($st['gid'])['name'] ?? $st['gid'];
        log_debug(['action'=>'stat_php_ok','canonical'=>$canonical,'mode'=>$curMode,'owner'=>$curOwner,'group'=>$curGroup]);
    } else {
        // sudo stat fallback
        $statOut = trim(@shell_exec('/usr/bin/sudo -n /usr/bin/stat -c "%a %U %G" ' . escapeshellarg($canonical) . ' 2>/dev/null'));
        if ($statOut !== '') {
            $parts = preg_split('/\s+/', $statOut);
            if (count($parts) >= 3) {
                $curMode = $parts[0];
                $curOwner = $parts[1];
                $curGroup = $parts[2];
                log_debug(['action'=>'sudo_stat_ok','canonical'=>$canonical,'mode'=>$curMode,'owner'=>$curOwner,'group'=>$curGroup]);
            }
        }
    }

    // parse inputs
    $modeRaw = isset($_REQUEST['mode']) ? trim((string)$_REQUEST['mode']) : '';
    $modeSan = preg_replace('/[^0-7]/','', $modeRaw);
    if ($modeSan === '') $modeSan = null;

    $ownerRaw = isset($_REQUEST['owner']) ? trim((string)$_REQUEST['owner']) : '';
    $ownerSan = $ownerRaw === '' ? null : preg_replace('/[^\w\.\-_:]/','', $ownerRaw); // allow colon for user:group

    $results = [
        'success' => false,
        'path' => $canonical,
        'prev' => ['mode' => $curMode, 'owner' => $curOwner, 'group' => $curGroup],
        'applied' => []
    ];

    // apply mode if provided
    if ($modeSan !== null) {
        $octInt = octdec($modeSan);
        $chmodOk = false;

        if (@chmod($canonical, $octInt)) {
            $chmodOk = true;
            log_debug(['action'=>'chmod_php_ok','canonical'=>$canonical,'mode'=>$modeSan]);
        } else {
            $cmd = '/usr/bin/sudo -n /bin/chmod ' . escapeshellarg($modeSan) . ' ' . escapeshellarg($canonical) . ' 2>&1';
            exec($cmd, $outLines, $exitCode);
            $out = implode("\n", $outLines);
            if ($exitCode === 0) {
                $chmodOk = true;
                log_debug(['action'=>'chmod_sudo_ok','cmd'=>$cmd,'out_preview'=>substr($out,0,400)]);
            } else {
                log_debug(['action'=>'chmod_failed','cmd'=>$cmd,'exit'=>$exitCode,'out_preview'=>substr($out,0,400)]);
                json_error_and_exit('Falha ao alterar modo. Saída: ' . substr($out,0,400), 500);
            }
        }
        if ($chmodOk) $results['applied']['mode'] = $modeSan;
    }

    // apply owner if provided (more robust)
    if ($ownerSan !== null) {
        // split possible user:group
        $userPart = $ownerSan;
        $groupPart = null;
        if (strpos($ownerSan, ':') !== false) {
            list($userPart, $groupPart) = explode(':', $ownerSan, 2);
        }

        log_debug(['action'=>'owner_request','ownerSan'=>$ownerSan,'userPart'=>$userPart,'groupPart'=>$groupPart]);

        // try to resolve user to uid/gid if possible
        $resolvedOwnerArg = null; // what we'll pass to chown
        $resolvedBy = null;

        // If posix_getpwnam exists, try it
        if (function_exists('posix_getpwnam')) {
            $pw = @posix_getpwnam($userPart);
            if ($pw !== false && is_array($pw)) {
                $uname = $pw['name'];
                if ($groupPart !== null) {
                    // try to keep group name as-is (shell chown accepts user:group)
                    $resolvedOwnerArg = $uname . ':' . $groupPart;
                } else {
                    $resolvedOwnerArg = $uname;
                }
                $resolvedBy = 'posix';
                log_debug(['action'=>'posix_resolve','pw'=>$pw,'resolvedOwnerArg'=>$resolvedOwnerArg]);
            }
        }

        // If posix didn't resolve, allow numeric uid:gids or raw string (shell will fail if invalid)
        if ($resolvedOwnerArg === null) {
            // if numeric uid provided (only digits) and maybe group numeric
            $isNumericUser = preg_match('/^\d+$/', $userPart);
            $isNumericGroup = $groupPart !== null && preg_match('/^\d+$/', $groupPart);
            if ($isNumericUser) {
                $resolvedOwnerArg = $userPart . ($groupPart !== null ? ':' . $groupPart : '');
                $resolvedBy = 'numeric';
            } else {
                // fallback: use raw sanitized string (username or user:group)
                $resolvedOwnerArg = $ownerSan;
                $resolvedBy = 'raw';
            }
            log_debug(['action'=>'owner_fallback','resolvedOwnerArg'=>$resolvedOwnerArg,'resolvedBy'=>$resolvedBy]);
        }

        // Try PHP chown if we have numeric uid/gid (chown in PHP expects numeric id; passing string fails)
        $phpChownAttempted = false;
        $phpChownOk = false;
        if (preg_match('/^(\d+)(?::(\d+))?$/', $resolvedOwnerArg, $m)) {
            $phpChownAttempted = true;
            $uid = intval($m[1]);
            $gid = isset($m[2]) ? intval($m[2]) : -1;
            if ($gid >= 0) {
                if (@chown($canonical, $uid) && @chgrp($canonical, $gid)) {
                    $phpChownOk = true;
                    log_debug(['action'=>'php_chown_uidgid_ok','uid'=>$uid,'gid'=>$gid]);
                } else {
                    log_debug(['action'=>'php_chown_uidgid_failed','uid'=>$uid,'gid'=>$gid,'err'=>error_get_last()]);
                }
            } else {
                if (@chown($canonical, $uid)) {
                    $phpChownOk = true;
                    log_debug(['action'=>'php_chown_uid_ok','uid'=>$uid]);
                } else {
                    log_debug(['action'=>'php_chown_uid_failed','uid'=>$uid,'err'=>error_get_last()]);
                }
            }
        }

        // If PHP chown didn't do it, try sudo chown with common binary locations
        if (!$phpChownOk) {
            $chownPaths = ['/bin/chown', '/usr/bin/chown', '/usr/local/bin/chown'];
            $chownOk = false;
            $chownOut = '';
            foreach ($chownPaths as $chownBin) {
                if (!file_exists($chownBin)) continue;
                $cmd = '/usr/bin/sudo -n ' . escapeshellcmd($chownBin) . ' ' . escapeshellarg($resolvedOwnerArg) . ' ' . escapeshellarg($canonical) . ' 2>&1';
                exec($cmd, $outLines, $exitCode);
                $out = implode("\n", $outLines);
                log_debug(['action'=>'chown_attempt','cmd'=>$cmd,'exit'=>$exitCode,'out_preview'=>substr($out,0,400)]);
                if ($exitCode === 0) {
                    $chownOk = true;
                    $chownOut = $out;
                    break;
                }
            }

            if (!$chownOk) {
                // last attempt: try with /usr/bin/chown even if not present (will likely fail)
                $cmd = '/usr/bin/sudo -n /usr/bin/chown ' . escapeshellarg($resolvedOwnerArg) . ' ' . escapeshellarg($canonical) . ' 2>&1';
                exec($cmd, $outLines, $exitCode);
                $out = implode("\n",$outLines);
                log_debug(['action'=>'chown_last_attempt','cmd'=>$cmd,'exit'=>$exitCode,'out_preview'=>substr($out,0,400)]);
                if ($exitCode === 0) {
                    $chownOk = true; $chownOut = $out;
                }
            }

            if (!$chownOk) {
                json_error_and_exit('Falha ao alterar proprietário. Verifique se o usuário existe e se sudoers permite chown. Última saída: ' . substr($out,0,400), 500);
            } else {
                $results['applied']['owner'] = $resolvedOwnerArg;
                log_debug(['action'=>'chown_sudo_ok','resolvedOwnerArg'=>$resolvedOwnerArg,'out_preview'=>substr($chownOut,0,400)]);
            }
        } else {
            // php chown succeeded
            $results['applied']['owner'] = $resolvedOwnerArg . ' (php-chown)';
        }
    }

    // final stat to show new state (try PHP then sudo)
    $newMode = null; $newOwner = null; $newGroup = null;
    clearstatcache(true, $canonical);
    if (file_exists($canonical) && ($st2 = @stat($canonical)) !== false) {
        $newMode = sprintf('%o', $st2['mode'] & 0777);
        $newOwner = @posix_getpwuid($st2['uid'])['name'] ?? $st2['uid'];
        $newGroup = @posix_getgrgid($st2['gid'])['name'] ?? $st2['gid'];
    } else {
        $statOut2 = trim(@shell_exec('/usr/bin/sudo -n /usr/bin/stat -c "%a %U %G" ' . escapeshellarg($canonical) . ' 2>/dev/null'));
        if ($statOut2 !== '') {
            $parts2 = preg_split('/\s+/', $statOut2);
            if (count($parts2) >= 3) {
                $newMode = $parts2[0];
                $newOwner = $parts2[1];
                $newGroup = $parts2[2];
            }
        }
    }
    $results['new'] = ['mode' => $newMode, 'owner' => $newOwner, 'group' => $newGroup];
    $results['success'] = true;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    json_error_and_exit('Erro interno: ' . $e->getMessage(), 500);
}
