<?php
// api/get_file_content.php
// Versão revisada — leitura direta ou via wrapper sudo; tenta devolver texto "raw" para editores
require '../config/session_guard.php';
header('X-Content-Type-Options: nosniff');

define('SAFE_ROOT', '/home');
define('LOGFILE', '/tmp/get_file_content_debug.log');

function log_debug($o) {
    $s = is_string($o) ? $o : json_encode($o, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    @file_put_contents(LOGFILE, '['.date('c').'] '.$s.PHP_EOL, FILE_APPEND | LOCK_EX);
}

function json_error($msg, $code = 400) {
    while (ob_get_length()) @ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

try {
    $raw = @file_get_contents('php://input') ?: '';
    $maybeJson = @json_decode($raw, true);
    if (is_array($maybeJson)) {
        foreach ($maybeJson as $k => $v) {
            if (!isset($_REQUEST[$k])) $_REQUEST[$k] = $v;
            if (!isset($_GET[$k])) $_GET[$k] = $v;
        }
    }

    $dbg = [
        'time' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'get' => $_GET,
        'raw_input_first_2048' => substr($raw, 0, 2048)
    ];
    log_debug($dbg);

    $candidate = $_REQUEST['path'] ?? null;
    if (!is_string($candidate) || trim($candidate) === '') {
        json_error('Parâmetro "path" necessário.', 400);
    }

    // normalize candidate
    $candidate = str_replace("\0", '', trim($candidate));
    $candidate = preg_replace('#/{2,}#','/',$candidate);
    if ($candidate === '') json_error('Parâmetro "path" inválido.', 400);
    if ($candidate[0] !== '/') $candidate = '/' . ltrim($candidate, '/');

    $safeRoot = rtrim(realpath(SAFE_ROOT), DIRECTORY_SEPARATOR);
    if ($safeRoot === false || $safeRoot === '') {
        log_debug('SAFE_ROOT resolve failed');
        json_error('Erro interno: raiz segura inválida.', 500);
    }

    // build absolute path if needed
    if (strpos($candidate, $safeRoot) === 0 || strpos($candidate, '/home/') === 0) {
        $fullPath = $candidate;
    } else {
        $fullPath = $safeRoot . '/' . ltrim($candidate, '/');
    }
    $fullPath = preg_replace('#/{2,}#','/',$fullPath);

    // Attempt php realpath
    $canonical = @realpath($fullPath);
    if ($canonical === false || $canonical === null) {
        log_debug(['action'=>'realpath_failed_initial','attempted'=>$fullPath]);
        // try sudo realpath
        $sudoReal = trim(@shell_exec('/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullPath) . ' 2>/dev/null'));
        if ($sudoReal !== '') {
            $canonical = explode("\n",$sudoReal,2)[0];
            log_debug(['action'=>'sudo_realpath_ok','canonical'=>$canonical]);
        }
    } else {
        log_debug(['action'=>'realpath_ok','canonical'=>$canonical]);
    }

    if (!is_string($canonical) || $canonical === '') {
        json_error('Arquivo não encontrado (realpath falhou).', 404);
    }

    // enforce jail
    $canonicalSlash = rtrim($canonical, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $safeRootSlash = rtrim($safeRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($canonicalSlash, $safeRootSlash) !== 0) {
        log_debug(['action'=>'attempt_outside_root','canonical'=>$canonical,'safeRoot'=>$safeRoot]);
        json_error('Acesso negado: fora da raiz permitida.', 403);
    }

    // Ensure target exists (maybe only via sudo)
    $exists = file_exists($canonical);
    $isReadable = is_readable($canonical);

    if (!$exists) {
        // maybe only via sudo
        $sudoTest = trim(@shell_exec('/usr/bin/sudo /usr/bin/test -e ' . escapeshellarg($canonical) . ' && echo "Y" || echo "N" 2>/dev/null'));
        if ($sudoTest === "Y") {
            $exists = true;
            log_debug(['action'=>'exists_only_via_sudo','canonical'=>$canonical]);
        }
    }

    if (!$exists) {
        json_error('Arquivo não encontrado: ' . $canonical, 404);
    }

    // is file?
    $isFile = is_file($canonical);
    if (!$isFile) {
        // maybe via sudo
        $sudoIsFile = trim(@shell_exec('/usr/bin/sudo /usr/bin/test -f ' . escapeshellarg($canonical) . ' && echo "Y" || echo "N" 2>/dev/null'));
        if ($sudoIsFile === "Y") {
            $isFile = true;
        }
    }
    if (!$isFile) {
        json_error('Caminho não é um arquivo regular.', 400);
    }

    // If php can read, use it.
    if ($isReadable) {
        $content = @file_get_contents($canonical);
        if ($content === false) {
            log_debug(['action'=>'php_read_failed','canonical'=>$canonical,'err'=>error_get_last()]);
            $isReadable = false; // fall back to sudo
        } else {
            // determine mime
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($canonical) ?: 'application/octet-stream';
            $size = @filesize($canonical) ?: strlen($content);
            $name = basename($canonical);

            // decide binary or text
            $binary = false;
            if (strpos($mime, 'text/') === 0) {
                $binary = false;
            } else {
                // heuristics: if contains NUL or many non-printable chars -> binary
                if (preg_match('/[\x00]/', $content) || preg_match('/[^\r\n\t\x20-\x7E]/', substr($content, 0, 4096))) {
                    $binary = true;
                } else {
                    $binary = false;
                }
            }

            // prepare response
            $resp = [
                'success' => true,
                'mime' => $mime,
                'size' => $size,
                'name' => $name,
                'binary' => $binary
            ];

            if ($binary) {
                $resp['content_base64'] = base64_encode($content);
            } else {
                // try to ensure UTF-8 for editors
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $content = mb_convert_encoding($content, 'UTF-8', 'auto');
                }
                $resp['content'] = $content;
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($resp, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        }
    }

    // If we reach here, we must use sudo wrapper to read file
    // Wrapper expected: either raw content, or header "OK|mime|size|basename\n<content>"
    $cmd = '/usr/bin/sudo -n /usr/local/bin/safe_read.sh ' . escapeshellarg($canonical) . ' 2>&1';
    log_debug(['action'=>'calling_wrapper','cmd'=>$cmd]);
    $out = @shell_exec($cmd);
    if ($out === null) {
        log_debug(['action'=>'wrapper_null_output','cmd'=>$cmd]);
        json_error('Erro ao executar comando privilegiado (wrapper).', 500);
    }

    // Some wrappers echo header then raw content. Try to parse header.
    $mime = 'application/octet-stream';
    $size = null;
    $name = basename($canonical);
    $content_raw = $out;

    // If output starts with OK|...
    if (preg_match('/\AOK\|([^\|]+)\|(\d+)\|([^\n]+)\n/s', $out, $m)) {
        $mime = $m[1];
        $size = intval($m[2]);
        $name = $m[3];
        $content_raw = substr($out, strlen($m[0]));
        log_debug(['action'=>'wrapper_header_parsed','mime'=>$mime,'size'=>$size,'name'=>$name]);
    } else {
        // if wrapper returned something like "OK|/path" or "ERR|..." but no header — try to detect simple "OK|path" patterns
        if (preg_match('/\AOK\|/',$out)) {
            // remove first line if it's just OK|something
            $parts = explode("\n", $out, 2);
            if (count($parts) === 2) {
                $content_raw = $parts[1];
            } else {
                $content_raw = '';
            }
        }
    }

    // Trim only trailing null bytes, but preserve other whitespace
    // Detect if content_raw is base64 encoded: decode with strict mode and re-encode to compare
    $trimmed = trim($content_raw, "\r\n");
    $decoded = @base64_decode($trimmed, true);
    $looksLikeBase64 = false;
    if ($decoded !== false) {
        // re-encode and compare normalized strings (allowing for trailing newlines)
        $reenc = rtrim(base64_encode($decoded), '=');
        $orignorm = rtrim(preg_replace('/\s+/', '', $trimmed), '=');
        // If significant similarity, consider it base64
        if (strlen($reenc) > 0 && substr($reenc, 0, min(8, strlen($reenc))) === substr($orignorm, 0, min(8, strlen($orignorm)))) {
            $looksLikeBase64 = true;
        }
    }

    // If wrapper reported mime starting with text/, prefer treating content_raw as raw text even if matches base64 pattern.
    if (strpos($mime, 'text/') === 0 || in_array($mime, ['application/javascript','application/json','application/xml','application/x-httpd-php'])) {
        $isBinary = false;
    } else {
        // heuristics: content has NUL or many nonprintable -> binary
        if (preg_match('/[\x00]/', substr($content_raw, 0, 8192)) || preg_match('/[^\r\n\t\x20-\x7E]/', substr($content_raw, 0, 4096))) {
            $isBinary = true;
        } elseif ($looksLikeBase64) {
            // wrapper gave base64 — decode and inspect decoded bytes for text
            $decodedSample = $decoded;
            if ($decodedSample === false) {
                $isBinary = true;
            } else {
                if (preg_match('/[\x00]/', substr($decodedSample, 0, 8192)) || preg_match('/[^\r\n\t\x20-\x7E]/', substr($decodedSample, 0, 4096))) {
                    $isBinary = true;
                } else {
                    $isBinary = false;
                }
            }
        } else {
            $isBinary = false;
        }
    }

    // Prepare response
    $resp = [
        'success' => true,
        'mime' => $mime,
        'size' => $size,
        'name' => $name,
        'binary' => $isBinary
    ];

    if ($isBinary) {
        if ($looksLikeBase64 && $decoded !== false) {
            $resp['content_base64'] = trim($trimmed);
            $resp['size'] = $size ?: strlen($decoded);
        } else {
            // content_raw might be raw binary — base64 encode it for safe JSON transfer
            $resp['content_base64'] = base64_encode($content_raw);
            $resp['size'] = $size ?: strlen($content_raw);
        }
    } else {
        // need to produce textual content for editor
        if ($looksLikeBase64 && $decoded !== false) {
            $text = $decoded;
        } else {
            $text = $content_raw;
        }
        // ensure UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        $resp['content'] = $text;
        $resp['size'] = $size ?: strlen($text);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;

} catch (Exception $e) {
    log_debug(['action'=>'exception','msg'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    json_error('Erro interno: ' . $e->getMessage(), 500);
}
