<?php
// api/create_new.php
// Versão atualizada para aceitar:
// - path (como antes) OU parent_dir + name
// - type: file | dir (aceita aliases folder,directory)
// Mantém realpath + sudo fallback, jaula em /home, fallback sudo install/mv/chown/mkdir, e logs.

require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/create_new_debug.log';

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

    // --- Ler raw JSON e popular $_REQUEST/$_POST ---
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
        'raw_input_first_4096' => substr($raw, 0, 4096)
    ];
    log_debug($dbg);

    // ----- Determinar alvo (path OR parent_dir + name) -----
    $candidate = null;
    // Prefer explicit parent_dir+name if present
    $parentDirRaw = $_REQUEST['parent_dir'] ?? $_REQUEST['parent'] ?? null;
    $nameRaw = $_REQUEST['name'] ?? null;

    if (is_string($parentDirRaw) && trim($parentDirRaw) !== '' && is_string($nameRaw) && trim($nameRaw) !== '') {
        // normalize parent_dir
        $parentDir = str_replace("\0", '', trim($parentDirRaw));
        $parentDir = preg_replace('#/{2,}#','/',$parentDir);
        if ($parentDir[0] !== '/') $parentDir = '/' . ltrim($parentDir, '/');

        // sanitize name: allow subpaths but remove .. and null bytes
        $name = str_replace("\0", '', trim($nameRaw));
        $name = preg_replace('#/{2,}#','/',$name);
        $name = ltrim($name, '/'); // we'll join to parent

        // montar candidate como parentDir/name
        $candidate = rtrim($parentDir, '/') . '/' . $name;
    } else {
        // fallback para o comportamento antigo: aceitar path/file/src...
        $candidate = $_REQUEST['path'] ?? $_REQUEST['file'] ?? $_REQUEST['src'] ?? $_REQUEST['source_path'] ?? $_REQUEST['old_path'] ?? null;
    }

    if (!is_string($candidate) || trim($candidate) === '') {
        json_error_and_exit('Parâmetro "path" ou (parent_dir + name) é obrigatório.', 400);
    }

    // tipo: aceitar aliases
    $typeRaw = strtolower((string)($_REQUEST['type'] ?? $_POST['type'] ?? 'file'));
    if (in_array($typeRaw, ['dir','directory','folder'])) {
        $type = 'dir';
    } else {
        $type = 'file';
    }

    // flags
    $recursive = false;
    if (isset($_REQUEST['recursive']) && in_array(strtolower((string)$_REQUEST['recursive']), ['1','true','yes'])) $recursive = true;
    $overwrite = false;
    if (isset($_REQUEST['overwrite']) && in_array(strtolower((string)$_REQUEST['overwrite']), ['1','true','yes'])) $overwrite = true;

    // mode (string like 0644)
    $mode = isset($_REQUEST['mode']) ? preg_replace('/[^0-7]/','', (string)$_REQUEST['mode']) : null;
    if ($mode === '') $mode = null;

    // content optional (for files)
    $content = null;
    if ($type === 'file') {
        if (isset($_REQUEST['content_base64'])) {
            $content = base64_decode((string)$_REQUEST['content_base64'], true);
            if ($content === false) json_error_and_exit('content_base64 inválido.', 400);
        } elseif (isset($_REQUEST['content'])) {
            $content = (string)$_REQUEST['content'];
        } elseif (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $content = @file_get_contents($_FILES['file']['tmp_name']);
            if ($content === false) $content = '';
        } else {
            // criar arquivo vazio se não fornecer conteúdo
            $content = '';
        }
    }

    // sanitize/normalize candidate path
    $candidate = str_replace("\0", '', trim($candidate));
    $candidate = preg_replace('#/{2,}#','/',$candidate);
    if ($candidate === '') json_error_and_exit('Parâmetro "path" inválido.', 400);
    if ($candidate[0] !== '/') $candidate = '/' . ltrim($candidate, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);

    // montar fullPath: aceita absolute or relative under /home
    if (strpos($candidate, $safeRoot) === 0 || strpos($candidate, '/home/') === 0) {
        $fullPath = $candidate;
    } else {
        $fullPath = $safeRoot . '/' . ltrim($candidate, '/');
    }
    $fullPath = preg_replace('#/{2,}#','/',$fullPath);

    // parent dir (may not exist)
    $parent = rtrim(dirname($fullPath), '/');
    if ($parent === '') $parent = $safeRoot;

    // tentar realpath do parent (pode falhar se não existir)
    $canonicalParent = @realpath($parent);
    if ($canonicalParent === false || $canonicalParent === null) {
        log_debug(['action'=>'realpath_parent_failed','parent'=>$parent]);
        // tentar sudo realpath
        $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($parent) . ' 2>/dev/null'));
        if ($sudoOut !== '') {
            $canonicalParent = explode("\n", $sudoOut, 2)[0];
            log_debug(['action'=>'sudo_realpath_parent_ok','parent'=>$parent,'canonical'=>$canonicalParent]);
        }
    }

    // se parent não existir e for permitido criar
    if ($canonicalParent === false || $canonicalParent === null || $canonicalParent === '') {
        $tryMake = false;
        if ($type === 'dir') $tryMake = true;
        if ($type === 'file' && $recursive) $tryMake = true;

        if ($tryMake) {
            $mkOk = @mkdir($parent, 0755, true);
            if ($mkOk) {
                $canonicalParent = @realpath($parent);
            } else {
                // fallback sudo mkdir -p
                $cmd = '/usr/bin/sudo -n /bin/mkdir -p ' . escapeshellarg($parent) . ' 2>&1';
                exec($cmd, $outLines, $exitCode);
                $out = implode("\n", $outLines);
                log_debug(['action'=>'sudo_mkdir_parent','cmd'=>$cmd,'exit'=>$exitCode,'out_preview'=>substr($out,0,400)]);
                if ($exitCode === 0) {
                    $sudoReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($parent) . ' 2>/dev/null'));
                    if ($sudoReal !== '') $canonicalParent = explode("\n",$sudoReal,2)[0];
                }
            }
        }
    }

    if ($canonicalParent === false || $canonicalParent === null || $canonicalParent === '') {
        log_debug(['action'=>'parent_canonical_failed_final','parent'=>$parent]);
        json_error_and_exit('Falha ao preparar diretório pai. Contate o administrador.', 500);
    }

    // reforçar jaula: parent dentro de /home
    $canonicalParentSlash = rtrim($canonicalParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
    if (strpos($canonicalParentSlash, $safeRootSlash) !== 0) {
        log_debug(['action'=>'attempt_outside_root','canonicalParent'=>$canonicalParent,'safeRoot'=>$safeRoot]);
        json_error_and_exit('Acesso negado: fora da raiz permitida.', 403);
    }

    // montar target final (basename do fullPath)
    $target = $canonicalParent . '/' . basename($fullPath);
    $target = preg_replace('#/{2,}#','/',$target);

    // checar existência e comportamento overwrite
    $existsLocally = @file_exists($target);
    if ($existsLocally && !$overwrite) {
        json_error_and_exit('Alvo já existe. Use overwrite=1 para substituir.', 409);
    }

    // --- Criar diretório ---
    if ($type === 'dir') {
        if (is_dir($target)) {
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($safeRoot)));
            echo json_encode(['success'=>true,'path'=>$rel,'message'=>'Diretório já existe.'], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
            exit;
        }

        // tentar mkdir com PHP
        $mk = @mkdir($target, $mode ? octdec($mode) : 0755, $recursive);
        $createdBy = 'php';
        if (!$mk) {
            // fallback sudo mkdir
            $cmd = '/usr/bin/sudo -n /bin/mkdir -p ' . escapeshellarg($target) . ' 2>&1';
            exec($cmd, $outLines, $exitCode);
            if ($exitCode !== 0) {
                log_debug(['action'=>'sudo_mkdir_failed','cmd'=>$cmd,'exit'=>$exitCode,'out'=>implode("\n",$outLines)]);
                json_error_and_exit('Falha ao criar diretório. Verifique permissões.', 500);
            }
            $createdBy = 'sudo';
        }

        // definir modo
        if ($mode) {
            @chmod($target, octdec($mode));
        }

        // aplicar chown automático — sempre forçar dono/grupo do usuário efetivo
        $user = posix_getpwuid(posix_geteuid());
        $group = posix_getgrgid(posix_getegid());
        if (!empty($user['name']) && !empty($group['name'])) {
            $chownCmd = sprintf('/usr/bin/sudo -n /usr/bin/chown %s:%s %s 2>/dev/null',
                escapeshellarg($user['name']),
                escapeshellarg($group['name']),
                escapeshellarg($target)
            );
            @shell_exec($chownCmd);
            log_debug(['action'=>'sudo_chown_ok_after_mkdir','target'=>$target,'owner'=>$user['name'],'group'=>$group['name']]);
        }

        $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($safeRoot)));
        log_debug(['action'=>'dir_created_'.$createdBy,'target'=>$target,'mode'=>$mode]);
        echo json_encode(['success'=>true,'path'=>$rel,'message'=>'Diretório criado com sucesso.'], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        exit;
    }

    // --- Criar ficheiro ---
    $preferredMode = $mode ? $mode : '0644';
    $wrote = false;

    // se existe e overwrite solicitado -> remover
    if ($existsLocally && $overwrite) {
        if (!is_writable($target)) {
            @shell_exec('/usr/bin/sudo -n /bin/rm -f ' . escapeshellarg($target) . ' 2>/dev/null');
        } else {
            @unlink($target);
        }
    }

    // tentativa 1: PHP direto
    $bytes = @file_put_contents($target, $content, LOCK_EX);
    if ($bytes !== false) {
        $wrote = true;
        @chmod($target, octdec($preferredMode));
    } else {
        // fallback sudo install
        $tmp = tempnam(sys_get_temp_dir(), 'create_');
        @file_put_contents($tmp, $content);
        $installCmd = sprintf('/usr/bin/sudo -n /usr/bin/install -m %s %s %s 2>&1',
            escapeshellarg($preferredMode),
            escapeshellarg($tmp),
            escapeshellarg($target)
        );
        exec($installCmd, $outLines, $exitCode);
        @unlink($tmp);
        if ($exitCode === 0) {
            $wrote = true;
        } else {
            log_debug(['action'=>'sudo_install_failed','exit'=>$exitCode,'out'=>$outLines]);
        }
    }

    // pós-chown se criou com sucesso
    if ($wrote) {
        $user = posix_getpwuid(posix_geteuid());
        $group = posix_getgrgid(posix_getegid());
        if (!empty($user['name']) && !empty($group['name'])) {
            $cmd = sprintf('/usr/bin/sudo -n /usr/bin/chown %s:%s %s 2>/dev/null',
                escapeshellarg($user['name']),
                escapeshellarg($group['name']),
                escapeshellarg($target)
            );
            @shell_exec($cmd);
            log_debug(['action'=>'sudo_chown_ok_after_file','target'=>$target,'owner'=>$user['name'],'group'=>$group['name']]);
        }

        clearstatcache(true, $target);
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($safeRoot)));
        $res = [
            'success' => true,
            'path' => $rel,
            'message' => 'Ficheiro criado com sucesso.',
            'bytes' => @filesize($target)
        ];
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    json_error_and_exit('Falha ao criar ficheiro. Verifique sudoers/permissões.', 500);


    // --- Criar ficheiro ---
    $preferredMode = $mode ? $mode : '0644';
    $wrote = false;

    // se existe e overwrite solicitado -> remover (tentar PHP, senão sudo rm)
    if ($existsLocally && $overwrite) {
        if (!is_writable($target)) {
            @shell_exec('/usr/bin/sudo -n /bin/rm -f ' . escapeshellarg($target) . ' 2>/dev/null');
        } else {
            @unlink($target);
        }
    }

    // tentativa 1: PHP
    $bytes = @file_put_contents($target, $content, LOCK_EX);
    if ($bytes !== false) {
        $wrote = true;
        @chmod($target, octdec($preferredMode));
        log_debug(['action'=>'file_written_php','target'=>$target,'bytes'=>$bytes,'mode'=>$preferredMode]);
    } else {
        log_debug(['action'=>'php_write_failed','target'=>$target,'err'=>error_get_last()]);
    }

    // tentativa 2: fallback sudo install (tmp -> install)
    if (!$wrote) {
        $tmp = tempnam(sys_get_temp_dir(), 'create_');
        if ($tmp === false) {
            log_debug(['action'=>'tmp_create_failed']);
            json_error_and_exit('Erro interno: não foi possível criar ficheiro temporário.', 500);
        }
        $ok = @file_put_contents($tmp, $content);
        if ($ok === false) {
            @unlink($tmp);
            log_debug(['action'=>'tmp_write_failed','tmp'=>$tmp]);
            json_error_and_exit('Erro interno: falha ao gravar temporário.', 500);
        }
        $installCmd = '/usr/bin/sudo -n /usr/bin/install -m ' . escapeshellarg($preferredMode) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($target) . ' 2>&1';
        exec($installCmd, $outLines, $exitCode);
        $out = implode("\n", $outLines);
        if ($exitCode !== 0) {
            // tentar mv como fallback
            $mvCmd = '/usr/bin/sudo -n /bin/mv ' . escapeshellarg($tmp) . ' ' . escapeshellarg($target) . ' 2>&1';
            exec($mvCmd, $mvOut, $mvCode);
            $mvOutStr = implode("\n",$mvOut);
            if ($mvCode !== 0) {
                @unlink($tmp);
                log_debug(['action'=>'sudo_install_and_mv_failed','install'=>$out,'install_code'=>$exitCode,'mv'=>$mvOutStr,'mv_code'=>$mvCode]);
                json_error_and_exit('Falha ao criar ficheiro com privilégios elevados. Saída: ' . substr($out . "\n" . $mvOutStr, 0, 800), 500);
            } else {
                $wrote = true;
            }
        } else {
            $wrote = true;
        }

        if ($wrote) {
            // ajustar dono do ficheiro para o mesmo do parent (best-effort)
            $powner = @fileowner($canonicalParent);
            $pgroup = @filegroup($canonicalParent);
            if ($powner !== false && $pgroup !== false) {
                @shell_exec('/usr/bin/sudo -n /usr/bin/chown ' . intval($powner) . ':' . intval($pgroup) . ' ' . escapeshellarg($target) . ' 2>/dev/null');
            }
        }
    }

    if (!$wrote) {
        json_error_and_exit('Falha ao criar ficheiro. Verifique sudoers/permissões.', 500);
    }

    clearstatcache(true, $target);
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($safeRoot)));
    $res = [
        'success' => true,
        'path' => $rel,
        'message' => 'Ficheiro criado com sucesso.',
        'bytes' => @filesize($target)
    ];
    log_debug(['action'=>'file_created_success','target'=>$target,'relative'=>$rel]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    json_error_and_exit('Erro interno: ' . $e->getMessage(), 500);
}
