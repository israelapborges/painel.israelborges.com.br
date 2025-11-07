<?php
require '../config/session_guard.php';
header('Content-Type: application/json; charset=utf-8');

$root_path_defined = '/home';
define('SAFE_ROOT_PATH', realpath($root_path_defined));

$results = [];

try {
    if (SAFE_ROOT_PATH === false) {
        throw new Exception('Raiz segura inválida no servidor.');
    }

    // ---- leitura raw para detectar JSON base64 e para debug ----------
    $rawInput = @file_get_contents('php://input') ?: '';

    $maybeJson = null;
    if ($rawInput !== '') {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) $maybeJson = $decoded;
    }

    // Debug log (remova em produção)
    $log = [
        'time' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNK',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'get' => $_GET,
        'post' => $_POST,
        'files_keys' => array_keys($_FILES ?: []),
        'raw_input_first_2048' => substr($rawInput, 0, 2048),
        'maybeJson_keys' => is_array($maybeJson) ? array_keys($maybeJson) : null
    ];
    @file_put_contents('/tmp/upload_file_debug.log', json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);

    // ---- Se veio JSON com content_base64 copiamos para $_POST (sem sobrescrever) --
    if (is_array($maybeJson)) {
        foreach (['path','filename','content_base64','overwrite'] as $k) {
            if (array_key_exists($k, $maybeJson) && !isset($_POST[$k])) {
                $_POST[$k] = $maybeJson[$k];
            }
        }
    }

    // ---- Recebe path (diretório alvo) e normalize -----------------------
    $targetDirParam = $_POST['path'] ?? $_GET['path'] ?? '';
    if (!is_string($targetDirParam) || trim($targetDirParam) === '') {
        throw new Exception('Parâmetro "path" (diretório alvo) é obrigatório.');
    }
    $targetDirParam = str_replace("\0", '', $targetDirParam);
    $targetDirParam = preg_replace('#/{2,}#', '/', trim($targetDirParam));
    if ($targetDirParam === '') $targetDirParam = '/';
    if ($targetDirParam[0] !== '/') $targetDirParam = '/' . $targetDirParam;

    // ---- Nome do arquivo alvo (pode vir do upload ou do JSON) ----------
    $origName = null;
    $uploadedFileArray = null;

    // Preferência: campo 'file'
    if (!empty($_FILES) && isset($_FILES['file']) && !empty($_FILES['file']['name'])) {
        $uploadedFileArray = $_FILES['file'];
    } else {
        // se não existe 'file', use o primeiro campo presente em $_FILES (ex: file_to_upload)
        if (!empty($_FILES)) {
            $keys = array_keys($_FILES);
            $firstKey = $keys[0];
            if (!empty($_FILES[$firstKey]['name'])) {
                $uploadedFileArray = $_FILES[$firstKey];
            }
        }
    }

    // Se veio via multipart, pega nome
    if ($uploadedFileArray !== null) {
        $origName = $uploadedFileArray['name'];
    } elseif (!empty($_POST['filename'])) {
        $origName = basename((string)$_POST['filename']);
    } else {
        throw new Exception('Nome do ficheiro ausente. Envie campo file (multipart) ou filename + content_base64 (JSON).');
    }

    // sanitize filename
    $origName = basename($origName);
    $origName = preg_replace('/[^\w\-.@() ]+/', '_', $origName);
    $origName = mb_strcut($origName, 0, 255);

    // overwrite flag
    $allowOverwrite = false;
    $ov = $_POST['overwrite'] ?? $_GET['overwrite'] ?? null;
    if ($ov === '1' || $ov === 'true' || $ov === 'yes') $allowOverwrite = true;

    // monta full paths
    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);
    // aceita path absoluto começando em /home ou relativo
    if (strpos($targetDirParam, $safeRoot) === 0 || strpos($targetDirParam, '/home/') === 0) {
        $fullDir = $targetDirParam;
    } else {
        $fullDir = $safeRoot . '/' . ltrim($targetDirParam, '/');
    }
    $fullDir = preg_replace('#/{2,}#', '/', $fullDir);
    $fullDest = rtrim($fullDir, DIRECTORY_SEPARATOR) . '/' . $origName;

    // verifica parent canonical, com fallback sudo realpath
    $parent = dirname($fullDest);
    $canonicalParent = realpath($parent);
    if ($canonicalParent === false || $canonicalParent === null) {
        $sudoCmd = '/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($parent) . ' 2>/dev/null';
        $sudoOut = trim(@shell_exec($sudoCmd));
        if ($sudoOut !== '') $canonicalParent = explode("\n", $sudoOut, 2)[0];
    }
    if ($canonicalParent === false || $canonicalParent === '' || $canonicalParent === null) {
        throw new Exception('Diretório alvo não encontrado ou inacessível: ' . htmlspecialchars($parent));
    }
    $canonicalParentSlash = rtrim($canonicalParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($canonicalParentSlash, $safeRoot . DIRECTORY_SEPARATOR) !== 0) {
        throw new Exception('Diretório alvo está fora da raiz permitida.');
    }

    // ---- Obter o ficheiro tmp (duas vias) ----------------------------------
    $tmpUploaded = null;
    // 1) via multipart/form-data (usando $uploadedFileArray se definido)
    if ($uploadedFileArray !== null && !empty($uploadedFileArray['tmp_name']) && file_exists($uploadedFileArray['tmp_name'])) {
        $tmpUploaded = $uploadedFileArray['tmp_name'];
    }

    // 2) via JSON base64 (content_base64)
    if ($tmpUploaded === null && !empty($_POST['content_base64'])) {
        $b64 = (string)$_POST['content_base64'];
        $decoded = base64_decode($b64, true);
        if ($decoded === false) throw new Exception('content_base64 inválido.');
        $tmp = tempnam(sys_get_temp_dir(), 'upload_base64_');
        if ($tmp === false) throw new Exception('Falha ao criar temporário.');
        if (file_put_contents($tmp, $decoded) === false) { @unlink($tmp); throw new Exception('Falha ao escrever temporário.'); }
        $tmpUploaded = $tmp;
    }

    if ($tmpUploaded === null) {
        // nada recebido — registrar debug e devolver mensagem clara
        $dbg = [
            'files' => $_FILES,
            'post_keys' => array_keys($_POST)
        ];
        @file_put_contents('/tmp/upload_file_debug.log', date('c') . ' NO_FILE_RECEIVED: ' . json_encode($dbg) . PHP_EOL, FILE_APPEND | LOCK_EX);
        throw new Exception('Arquivo não recebido (campo file) ou content_base64 ausente. Verifique Content-Type/multipart e tamanho (upload_max_filesize/post_max_size).');
    }

    // ajustar permissões temporárias para segurança
    @chmod($tmpUploaded, 0600);

    // limitar tamanho (ex: 50 MiB) — ajuste conforme necessidade
    $MAX_BYTES = 50 * 1024 * 1024;
    $bytes = @filesize($tmpUploaded) ?: 0;
    if ($bytes > $MAX_BYTES) { @unlink($tmpUploaded); throw new Exception('Arquivo excede limite máximo (' . ($MAX_BYTES/1024/1024) . ' MiB).'); }

    // ---- chame o wrapper para mover tmp -> destino (mais seguro) ----------
    $wrapper = '/usr/local/bin/safe_upload.sh';

    if (file_exists($wrapper)) {
        // usamos sudo -n para falhar imediatamente se senha necessária
        $flag = $allowOverwrite ? '--overwrite' : '';
        $cmd = '/usr/bin/sudo -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($tmpUploaded) . ' ' . escapeshellarg($fullDest);
        if ($flag !== '') $cmd .= ' ' . escapeshellarg($flag);
        $cmd .= ' 2>&1';

        $raw = @shell_exec($cmd);

        if ($raw === null) {
            @unlink($tmpUploaded);
            $phpUser = trim((string)@shell_exec('whoami'));
            throw new Exception('Erro ao executar comando privilegiado (shell_exec retornou null). Usuário PHP: ' . ($phpUser ?: 'desconhecido'));
        }

        $raw = trim($raw);

        if ($raw === '') {
            @unlink($tmpUploaded);
            $phpUser = trim((string)@shell_exec('whoami'));
            $sudoList = trim((string)@shell_exec('/usr/bin/sudo -n -l -U ' . escapeshellarg($phpUser) . ' 2>&1'));
            throw new Exception('sudo retornou vazio; verifique /etc/sudoers. Usuário PHP: ' . ($phpUser ?: 'desconhecido') . '. sudo -l: ' . substr($sudoList,0,400));
        }

        $first = explode("\n", $raw, 2)[0];

        if (strpos($first, 'OK|') === 0) {
            $parts = explode('|', $first, 2);
            $finalPath = $parts[1] ?? $fullDest;
            $results['success'] = true;
            $results['path'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($finalPath, strlen($safeRoot)));
            $results['message'] = 'Upload realizado com sucesso.';
            echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        } else {
            @unlink($tmpUploaded);
            if (strpos($first, 'ERR|') === 0) {
                $code = explode('|', $first, 2)[1] ?? 'unknown';
                throw new Exception('Erro do wrapper: ' . $code . ' — saída: ' . substr($raw,0,400));
            } else {
                throw new Exception('Resposta inesperada do wrapper: ' . substr($raw,0,400));
            }
        }
    } else {
        // wrapper não existe — fallback: tenta mover via PHP se possível
        if (!is_writable($canonicalParent)) {
            @unlink($tmpUploaded);
            throw new Exception('Wrapper indisponível e diretório alvo não gravável pelo PHP.');
        }
        if (!is_dir($canonicalParent)) {
            if (!mkdir($canonicalParent, 0755, true)) { @unlink($tmpUploaded); throw new Exception('Falha ao criar diretório pai.'); }
        }
        if (!rename($tmpUploaded, $fullDest)) { @unlink($tmpUploaded); throw new Exception('Falha ao mover arquivo para destino via PHP.'); }
        // ajustar owner para o owner do parent (se possível)
        $uid = @fileowner($canonicalParent);
        $gid = @filegroup($canonicalParent);
        if ($uid) @chown($fullDest, $uid);
        if ($gid) @chgrp($fullDest, $gid);
        $results['success'] = true;
        $results['path'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($fullDest, strlen($safeRoot)));
        $results['message'] = 'Arquivo salvo com sucesso (via PHP direto).';
        echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

} catch (Exception $e) {
    $results['success'] = false;
    $results['error'] = $e->getMessage();
    echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
?>
