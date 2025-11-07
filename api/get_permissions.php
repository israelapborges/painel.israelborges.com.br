<?php
// api/get_permissions.php
// Retorna informações de permissões / dono / grupo de um ficheiro
// - aceita path via GET/POST ou JSON body
// - normaliza com realpath, fallback sudo realpath
// - reforça jaula em /home
// - tenta stat() em PHP, se falhar usa `sudo stat -c`
// - log em /tmp/get_permissions_debug.log

require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

$ROOT_DEFINED = '/home';
define('SAFE_ROOT_PATH', realpath($ROOT_DEFINED));
$LOGFILE = '/tmp/get_permissions_debug.log';

function log_debug($o) {
    global $LOGFILE;
    $s = is_string($o) ? $o : json_encode($o, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents($LOGFILE, '['.date('c').'] '.$s.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function json_exit($data, $code = 200) {
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

    // ler raw JSON e popular REQUEST/POST
    $raw = @file_get_contents('php://input') ?: '';
    $maybeJson = null;
    if ($raw !== '') {
        $dec = @json_decode($raw, true);
        if (is_array($dec)) $maybeJson = $dec;
    }
    if (is_array($maybeJson)) {
        foreach ($maybeJson as $k => $v) {
            if (!isset($_REQUEST[$k])) $_REQUEST[$k] = $v;
            if (!isset($_GET[$k])) $_GET[$k] = $v;
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

    // aceitar aliases
    $candidate = $_REQUEST['path'] ?? $_REQUEST['file'] ?? $_REQUEST['src'] ?? $_REQUEST['source_path'] ?? null;
    if (!is_string($candidate) || trim($candidate) === '') {
        json_exit(['success' => false, 'error' => 'Parâmetro "path" obrigatório.'], 400);
    }

    // sanitize
    $candidate = str_replace("\0", '', trim($candidate));
    $candidate = preg_replace('#/{2,}#','/',$candidate);
    if ($candidate === '') json_exit(['success'=>false,'error'=>'Parâmetro "path" inválido.'], 400);
    if ($candidate[0] !== '/') $candidate = '/' . ltrim($candidate, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);

    // montar fullPath: aceita absolute (se começar com /home) ou relative under /home
    if (strpos($candidate, $safeRoot) === 0 || strpos($candidate, '/home/') === 0) {
        $fullPath = $candidate;
    } else {
        $fullPath = $safeRoot . '/' . ltrim($candidate, '/');
    }
    $fullPath = preg_replace('#/{2,}#','/',$fullPath);

    // canonicalizar com realpath; se falhar, fallback sudo realpath
    $canonical = @realpath($fullPath);
    if ($canonical === false || $canonical === null) {
        log_debug(['action'=>'realpath_failed_initial','attempted'=>$fullPath]);
        $sudoOut = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullPath) . ' 2>/dev/null'));
        if ($sudoOut !== '') {
            $canonical = explode("\n", $sudoOut, 2)[0];
            log_debug(['action'=>'sudo_realpath_ok','canonical'=>$canonical]);
        }
    } else {
        log_debug(['action'=>'realpath_ok','canonical'=>$canonical]);
    }

    if ($canonical === false || $canonical === null || $canonical === '') {
        // tenta verificar existência via sudo test -e para mensagem mais precisa
        $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($fullPath) . ' && echo yes || echo no'));
        json_exit(['success'=>false,'error'=>'Caminho não encontrado ou inacessível. exists_via_sudo='.$existsViaSudo,'attempted'=>$fullPath], 404);
    }

    // reforçar a jaula: canonical deve ficar dentro do SAFE_ROOT
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
    $candidateSlash = rtrim($canonical, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($candidateSlash, $safeRootSlash) !== 0) {
        json_exit(['success'=>false,'error'=>'Acesso negado: fora da raiz permitida.'], 403);
    }

    // se arquivo não existe (após canonical) -> erro
    // (se canonical existe mas arquivo não existir - raro - checamos)
    if (!file_exists($canonical)) {
        // talvez exista somente via sudo (PERM): tentamos sudo test -e
        $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($canonical) . ' && echo yes || echo no'));
        if ($existsViaSudo !== 'yes') {
            json_exit(['success'=>false,'error'=>'Arquivo não encontrado: '.$candidate], 404);
        }
        // se existe via sudo, continuamos (iremos obter stat via sudo)
        log_debug(['action'=>'file_exists_only_via_sudo','canonical'=>$canonical]);
    }

    // TENTA stat() em PHP
    $stat = @stat($canonical);
    $data = [
        'success' => true,
        'path' => str_replace(DIRECTORY_SEPARATOR, '/', substr($canonical, strlen($safeRoot))),
        'canonical' => $canonical,
        'owner' => null,
        'group' => null,
        'mode_octal' => null,
        'mode_numeric' => null,
        'owner_perms' => ['r'=>false,'w'=>false,'x'=>false],
        'group_perms' => ['r'=>false,'w'=>false,'x'=>false],
        'other_perms' => ['r'=>false,'w'=>false,'x'=>false],
        'is_file' => is_file($canonical),
        'is_dir' => is_dir($canonical),
    ];

    if ($stat !== false && isset($stat['mode'])) {
        // obter modo via fileperms/stat
        $perms = $stat['mode'];
        // converter para octal (pegar últimos 3 dígitos)
        $modeOct = substr(sprintf('%o', $perms), -3);
        // garantir 3 dígitos (padrão)
        if (strlen($modeOct) < 3) $modeOct = str_pad($modeOct, 3, '0', STR_PAD_LEFT);
        $data['mode_octal'] = $modeOct;
        $data['mode_numeric'] = intval($modeOct, 10);
        // dono/grupo
        $uid = $stat['uid'] ?? null;
        $gid = $stat['gid'] ?? null;
        if ($uid !== null && function_exists('posix_getpwuid')) {
            $pw = @posix_getpwuid($uid);
            $data['owner'] = $pw ? $pw['name'] : (string)$uid;
        } else {
            $data['owner'] = (string)$uid;
        }
        if ($gid !== null && function_exists('posix_getgrgid')) {
            $gr = @posix_getgrgid($gid);
            $data['group'] = $gr ? $gr['name'] : (string)$gid;
        } else {
            $data['group'] = (string)$gid;
        }
        // calcular bits de permissão
        $ownerDigit = intval($modeOct[0]);
        $groupDigit = intval($modeOct[1]);
        $otherDigit = intval($modeOct[2]);
        $data['owner_perms'] = [
            'r' => ($ownerDigit & 4) !== 0,
            'w' => ($ownerDigit & 2) !== 0,
            'x' => ($ownerDigit & 1) !== 0
        ];
        $data['group_perms'] = [
            'r' => ($groupDigit & 4) !== 0,
            'w' => ($groupDigit & 2) !== 0,
            'x' => ($groupDigit & 1) !== 0
        ];
        $data['other_perms'] = [
            'r' => ($otherDigit & 4) !== 0,
            'w' => ($otherDigit & 2) !== 0,
            'x' => ($otherDigit & 1) !== 0
        ];

        log_debug(['action'=>'stat_php_ok','canonical'=>$canonical,'mode_octal'=>$modeOct,'owner'=>$data['owner'],'group'=>$data['group']]);
        json_exit($data);
    }

    // Se chegamos aqui: stat() falhou (provavelmente permissão). Usar sudo stat fallback.
    log_debug(['action'=>'stat_php_failed','canonical'=>$canonical]);

    // Primeiro tentar wrapper /usr/local/bin/safe_stat.sh se existir (recomendado)
    $wrapper = '/usr/local/bin/safe_stat.sh';
    if (is_executable($wrapper)) {
        $cmd = '/usr/bin/sudo -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonical) . ' 2>&1';
        $out = trim(@shell_exec($cmd));
        log_debug(['action'=>'sudo_wrapper_stat_exec','cmd'=>$cmd,'out_preview'=>substr($out,0,1000)]);
        // O wrapper pode devolver um formato como: OK|mode|uid|gid|user|group
        // ou podemos parsear com `stat -c` se wrapper só retorna stdout do stat
        if ($out !== '') {
            // procurar por uma linha OK|... ou se não, tentar parse do stat output
            if (strpos($out, 'OK|') === 0) {
                $parts = explode('|', $out, 6);
                // OK|/path|mode_octal|uid|gid|user:group (depend do wrapper)
                $mode = $parts[2] ?? null;
                $uid = isset($parts[3]) ? intval($parts[3]) : null;
                $gid = isset($parts[4]) ? intval($parts[4]) : null;
                if ($mode !== null) {
                    $mode = preg_replace('/[^0-7]/','', (string)$mode);
                    if (strlen($mode) < 3) $mode = str_pad($mode,3,'0',STR_PAD_LEFT);
                    $data['mode_octal'] = $mode;
                    $data['mode_numeric'] = intval($mode, 10);
                }
                if ($uid !== null) {
                    if (function_exists('posix_getpwuid')) {
                        $pw = @posix_getpwuid($uid);
                        $data['owner'] = $pw ? $pw['name'] : (string)$uid;
                    } else {
                        $data['owner'] = (string)$uid;
                    }
                }
                if ($gid !== null) {
                    if (function_exists('posix_getgrgid')) {
                        $gr = @posix_getgrgid($gid);
                        $data['group'] = $gr ? $gr['name'] : (string)$gid;
                    } else {
                        $data['group'] = (string)$gid;
                    }
                }
            } else {
                // fallback: tentar `stat -c` parsing (caso wrapper apenas imprima stat)
                // Exemplo do stat -c: "%a %u %g" -> "644 1000 1000"
                // tentar rodar stat diretamente via sudo
                $statOut = trim(@shell_exec('/usr/bin/sudo -n /usr/bin/stat -c "%a %u %g" ' . escapeshellarg($canonical) . ' 2>/dev/null'));
                if ($statOut !== '') {
                    $parts = preg_split('/\s+/', $statOut);
                    if (count($parts) >= 3) {
                        $mode = preg_replace('/[^0-9]/','', $parts[0]);
                        if (strlen($mode) < 3) $mode = str_pad($mode, 3, '0', STR_PAD_LEFT);
                        $data['mode_octal'] = $mode;
                        $data['mode_numeric'] = intval($mode, 10);
                        $uid = intval($parts[1]);
                        $gid = intval($parts[2]);
                        if (function_exists('posix_getpwuid')) {
                            $pw = @posix_getpwuid($uid);
                            $data['owner'] = $pw ? $pw['name'] : (string)$uid;
                        } else {
                            $data['owner'] = (string)$uid;
                        }
                        if (function_exists('posix_getgrgid')) {
                            $gr = @posix_getgrgid($gid);
                            $data['group'] = $gr ? $gr['name'] : (string)$gid;
                        } else {
                            $data['group'] = (string)$gid;
                        }
                    }
                }
            }
        }
    } else {
        // wrapper não existe, usar sudo stat -c diretamente
        $statOut = trim(@shell_exec('/usr/bin/sudo -n /usr/bin/stat -c "%a %u %g" ' . escapeshellarg($canonical) . ' 2>/dev/null'));
        log_debug(['action'=>'sudo_stat_direct','cmd'=>'sudo stat -c "%a %u %g"','out_preview'=>substr($statOut,0,200)]);
        if ($statOut !== '') {
            $parts = preg_split('/\s+/', $statOut);
            if (count($parts) >= 3) {
                $mode = preg_replace('/[^0-9]/','', $parts[0]);
                if (strlen($mode) < 3) $mode = str_pad($mode, 3, '0', STR_PAD_LEFT);
                $data['mode_octal'] = $mode;
                $data['mode_numeric'] = intval($mode, 10);
                $uid = intval($parts[1]);
                $gid = intval($parts[2]);
                if (function_exists('posix_getpwuid')) {
                    $pw = @posix_getpwuid($uid);
                    $data['owner'] = $pw ? $pw['name'] : (string)$uid;
                } else {
                    $data['owner'] = (string)$uid;
                }
                if (function_exists('posix_getgrgid')) {
                    $gr = @posix_getgrgid($gid);
                    $data['group'] = $gr ? $gr['name'] : (string)$gid;
                } else {
                    $data['group'] = (string)$gid;
                }
            }
        }
    }

    // se ainda não temos mode_octal, falha
    if (empty($data['mode_octal'])) {
        log_debug(['action'=>'stat_fallback_failed','canonical'=>$canonical]);
        json_exit(['success'=>false,'error'=>'Falha ao obter estatísticas do ficheiro. Verifique sudoers/wrappers.'], 500);
    }

    // calcular owner/group/other perms a partir do octal
    $modeOct = $data['mode_octal'];
    $ownerDigit = intval($modeOct[0]);
    $groupDigit = intval($modeOct[1]);
    $otherDigit = intval($modeOct[2]);
    $data['owner_perms'] = [
        'r' => ($ownerDigit & 4) !== 0,
        'w' => ($ownerDigit & 2) !== 0,
        'x' => ($ownerDigit & 1) !== 0
    ];
    $data['group_perms'] = [
        'r' => ($groupDigit & 4) !== 0,
        'w' => ($groupDigit & 2) !== 0,
        'x' => ($groupDigit & 1) !== 0
    ];
    $data['other_perms'] = [
        'r' => ($otherDigit & 4) !== 0,
        'w' => ($otherDigit & 2) !== 0,
        'x' => ($otherDigit & 1) !== 0
    ];

    log_debug(['action'=>'stat_success_final','canonical'=>$canonical,'mode_octal'=>$data['mode_octal'],'owner'=>$data['owner'],'group'=>$data['group']]);
    json_exit($data);

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    json_exit(['success'=>false,'error'=>'Erro interno: '.$e->getMessage()], 500);
}
