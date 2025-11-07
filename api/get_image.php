<?php
require '../config/session_guard.php';

// Este endpoint serve imagens (binário) por padrão.
// Parámetros:
//  - path (obrigatório): caminho relativo a /home, ex: /israelborges-ask/htdocs/img.png
//  - w (opcional): largura do thumbnail (px)
//  - h (opcional): altura do thumbnail (px)
//  - as_json=1 (opcional): retorna metadata + content_base64 em JSON em vez de enviar binário
//  - raw=1 (equivalente as_json=0): força envio binário (útil para <img src=>)
 
$root_path_defined = '/home';
define('SAFE_ROOT_PATH', realpath($root_path_defined));

try {
    if (SAFE_ROOT_PATH === false) {
        throw new Exception('Raiz segura inválida no servidor.');
    }

    // input
    $userPath = $_GET['path'] ?? $_POST['path'] ?? '';
    if (!is_string($userPath) || $userPath === '') {
        throw new Exception('Parâmetro "path" necessário.');
    }

    // thumbnail params
    $w = isset($_GET['w']) ? (int)$_GET['w'] : (isset($_POST['w']) ? (int)$_POST['w'] : 0);
    $h = isset($_GET['h']) ? (int)$_GET['h'] : (isset($_POST['h']) ? (int)$_POST['h'] : 0);
    $asJson = isset($_GET['as_json']) && ($_GET['as_json'] === '1' || $_GET['as_json'] === 'true');
    $isRaw = isset($_GET['raw']) && ($_GET['raw'] === '1' || $_GET['raw'] === 'true');

    // sanitize path
    $userPath = str_replace("\0", '', $userPath);
    $userPath = preg_replace('#/{2,}#', '/', $userPath);
    if ($userPath[0] !== '/') $userPath = '/' . $userPath;

    $fullPath = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR) . '/' . ltrim($userPath, '/');

    // 1) tenta realpath() com o user do PHP
    $canonicalPath = realpath($fullPath);

    // 2) fallback: se realpath falhar, tenta realpath via sudo (root)
    if ($canonicalPath === false || $canonicalPath === null) {
        $sudoRealpathCmd = '/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullPath) . ' 2>/dev/null';
        $sudoOut = trim(shell_exec($sudoRealpathCmd));
        if ($sudoOut !== '') {
            $canonicalPath = explode("\n", $sudoOut, 2)[0];
        }
    }

    if ($canonicalPath === false || $canonicalPath === '' || $canonicalPath === null) {
        throw new Exception('Caminho não encontrado: ' . htmlspecialchars($userPath));
    }

    // 3) reforça a jaula: canonicalPath deve começar com SAFE_ROOT_PATH
    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);
    $candidate = rtrim($canonicalPath, DIRECTORY_SEPARATOR);
    $safeRootSlash = $safeRoot . DIRECTORY_SEPARATOR;
    $candidateSlash = $candidate . DIRECTORY_SEPARATOR;

    if ($candidate !== $safeRoot && strpos($candidateSlash, $safeRootSlash) !== 0) {
        throw new Exception('Acesso negado: fora da raiz permitida.');
    }

    // 4) obter o conteúdo: se wrapper existe, usa sudo wrapper; senão fallback com PHP
    $wrapper = '/usr/local/bin/safe_read.sh';
    $raw = null;
    $viaWrapper = false;

    if (is_executable($wrapper)) {
        $cmd = '/usr/bin/sudo ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonicalPath) . ' 2>&1';
        $raw = shell_exec($cmd);
        $viaWrapper = true;
        if ($raw === null) throw new Exception('Falha ao chamar wrapper de leitura.');
    } else {
        // fallback: ler com PHP (somente se legível)
        if (!is_file($canonicalPath) || !is_readable($canonicalPath)) {
            throw new Exception('Arquivo não legível pelo PHP e wrapper indisponível.');
        }
        $mime = (function($p){
            $f = @finfo_open(FILEINFO_MIME_TYPE);
            if ($f !== false) {
                $m = @finfo_file($f, $p);
                finfo_close($f);
                return $m ?: 'application/octet-stream';
            }
            return 'application/octet-stream';
        })($canonicalPath);
        $size = @filesize($canonicalPath);
        $basename = basename($canonicalPath);
        $content = @file_get_contents($canonicalPath);
        if ($content === false) throw new Exception('Falha ao ler arquivo com PHP.');
        $b64 = base64_encode($content);
        $raw = "OK|{$mime}|{$size}|{$basename}\n" . $b64;
    }

    $raw = ltrim($raw, "\n\r\t ");
    if ($raw === '') throw new Exception('Leitura vazia.');

    // separar header e base64 body
    $pos = strpos($raw, "\n");
    if ($pos === false) {
        $first = $raw;
        $body = '';
    } else {
        $first = substr($raw, 0, $pos);
        $body = substr($raw, $pos + 1);
    }

    // tratar erros do wrapper
    if (strpos($first, 'ERR|') === 0) {
        $parts = explode('|', $first, 2);
        $code = $parts[1] ?? 'unknown';
        throw new Exception('Wrapper error: ' . $code);
    }

    if (strpos($first, 'OK|') !== 0) {
        throw new Exception('Formato inesperado da resposta do leitor.');
    }

    $meta = explode('|', $first, 4);
    $mime = $meta[1] ?? 'application/octet-stream';
    $size = isset($meta[2]) && is_numeric($meta[2]) ? (int)$meta[2] : null;
    $basename = $meta[3] ?? basename($canonicalPath);

    // se as_json pedir, devolve JSON com content_base64
    if ($asJson) {
        header('Content-Type: application/json');
        $out = [
            'success' => true,
            'name' => $basename,
            'mime' => $mime,
            'size' => $size,
            'content_base64' => $body,
            'via_wrapper' => $viaWrapper
        ];
        echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    // Para envio direto (binário), decodifica base64 para binário
    $imgData = base64_decode($body, true);
    if ($imgData === false) throw new Exception('Falha ao decodificar imagem.');

    // proteção de memória: limite para envio direto (ajuste conforme necessidade)
    $MAX_DIRECT_BYTES = 15 * 1024 * 1024; // 15 MB
    if (strlen($imgData) > $MAX_DIRECT_BYTES && $w <= 0 && $h <= 0) {
        // se arquivo grande e não pediu thumbnail, retornamos 413 para evitar OOMs
        header('HTTP/1.1 413 Payload Too Large');
        throw new Exception('Imagem muito grande para transmissão direta. Peça um thumbnail (w/h).');
    }

    // Se pediu thumbnail (w ou h), usar GD para redimensionar
    if (($w > 0 || $h > 0) && function_exists('imagecreatefromstring')) {
        $srcImg = @imagecreatefromstring($imgData);
        if ($srcImg === false) {
            throw new Exception('Falha ao criar imagem a partir dos dados.');
        }
        $origW = imagesx($srcImg);
        $origH = imagesy($srcImg);

        // calcula dimensões mantendo proporção
        if ($w > 0 && $h > 0) {
            $dstW = $w; $dstH = $h;
        } elseif ($w > 0) {
            $dstW = $w;
            $dstH = (int)max(1, round($origH * ($w / $origW)));
        } else { // $h > 0
            $dstH = $h;
            $dstW = (int)max(1, round($origW * ($h / $origH)));
        }

        // evita dimensionamento absurdo
        $dstW = max(1, min(8000, $dstW));
        $dstH = max(1, min(8000, $dstH));

        $dstImg = imagecreatetruecolor($dstW, $dstH);
        // preservar transparência para PNG/GIF
        if (stripos($mime, 'png') !== false || stripos($mime, 'gif') !== false) {
            imagecolortransparent($dstImg, imagecolorallocatealpha($dstImg, 0, 0, 0, 127));
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
        }
        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $origW, $origH);

        // prepara envio
        if (ob_get_length()) ob_end_clean();
        header_remove();
        header('Content-Type: ' . $mime);
        // opcional: Content-Length omitido (GD outputs directly)
        if (stripos($mime, 'png') !== false) {
            imagepng($dstImg);
        } elseif (stripos($mime, 'gif') !== false) {
            imagegif($dstImg);
        } else {
            // para jpeg/webp e outros use jpeg por padrão
            imagejpeg($dstImg, null, 85);
        }
        imagedestroy($srcImg);
        imagedestroy($dstImg);
        exit;
    }

    // envio binário direto
    if (ob_get_length()) ob_end_clean();
    header_remove();
    header('Content-Type: ' . $mime);
    if ($size !== null) header('Content-Length: ' . $size);
    // permitir cache no browser (ajuste conforme sua política)
    header('Cache-Control: public, max-age=86400');
    echo $imgData;
    exit;

} catch (Exception $e) {
    // Se já enviamos headers de imagem, encerrar; senão retornar JSON de erro
    if (!headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } else {
        // Se headers já enviados, apenas fim de saída
        error_log('get_image.php error after headers sent: ' . $e->getMessage());
    }
    exit;
}
?>
