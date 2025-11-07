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

    // leitura raw body (JSON) para suportar Content-Type: application/json
    $rawInput = @file_get_contents('php://input') ?: '';
    $maybeJson = null;
    if ($rawInput !== '') {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) $maybeJson = $decoded;
    }

    // popular $_POST a partir do JSON (sem sobrescrever)
    if (is_array($maybeJson)) {
        foreach (array_keys($maybeJson) as $k) {
            if (!isset($_POST[$k])) $_POST[$k] = $maybeJson[$k];
        }

        // suporte específico: se cliente enviou old_path + new_name, converte para src/dst
        if (empty($_POST['src']) && empty($_POST['source']) && !empty($maybeJson['old_path']) && !empty($maybeJson['new_name'])) {
            $old = (string)$maybeJson['old_path'];
            $newname = (string)$maybeJson['new_name'];
            // normaliza barras e remove null bytes
            $old = str_replace("\0", '', preg_replace('#/{2,}#', '/', trim($old)));
            $newname = basename(str_replace("\0", '', $newname));
            // garante leading slash on old for consistent processing (we'll handle later)
            if ($old !== '' && $old[0] !== '/') $old = '/' . ltrim($old, '/');
            // destino = dirname(old) + '/' + newname
            $dstComputed = rtrim(dirname($old), '/') . '/' . $newname;
            // remova duplicidade de slashes
            $dstComputed = preg_replace('#/{2,}#', '/', $dstComputed);
            // setar em $_POST para a lógica normal abaixo
            if (!isset($_POST['src'])) $_POST['src'] = $old;
            if (!isset($_POST['dst'])) $_POST['dst'] = $dstComputed;
        }
    }

    // debug log (remova em produção)
    $log = [
        'time' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNK',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
        'get' => $_GET,
        'post' => $_POST,
        'raw_input_first_2048' => substr($rawInput, 0, 2048),
    ];
    @file_put_contents('/tmp/rename_file_debug.log', json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);

    // aceitar múltiplos nomes possíveis do cliente (src/dst preferidos)
    $srcParam = $_POST['src'] ?? $_POST['source'] ?? $_POST['old'] ?? $_POST['old_path'] ?? $_GET['src'] ?? $_GET['source'] ?? $_GET['old'] ?? $_GET['old_path'] ?? '';
    $dstParam = $_POST['dst'] ?? $_POST['target'] ?? $_POST['new'] ?? $_POST['new_name'] ?? $_GET['dst'] ?? $_GET['target'] ?? $_GET['new'] ?? $_GET['new_name'] ?? '';

    if (!is_string($srcParam) || trim($srcParam) === '' || !is_string($dstParam) || trim($dstParam) === '') {
        throw new Exception('Parâmetros "src" e "dst" são necessários.');
    }

    // sanitize input (remove null bytes, normaliza //)
    $srcParam = preg_replace('#/{2,}#', '/', str_replace("\0", '', trim($srcParam)));
    $dstParam = preg_replace('#/{2,}#', '/', str_replace("\0", '', trim($dstParam)));
    if ($srcParam === '') throw new Exception('Parâmetro "src" inválido.');
    if ($dstParam === '') throw new Exception('Parâmetro "dst" inválido.');

    // se o cliente enviou caminho relativo (sem / no começo), prefixa '/'
    if ($srcParam[0] !== '/') $srcParam = '/' . ltrim($srcParam, '/');
    if ($dstParam[0] !== '/') $dstParam = '/' . ltrim($dstParam, '/');

    $safeRoot = rtrim(SAFE_ROOT_PATH, DIRECTORY_SEPARATOR);

    // monta full paths (aceita src/dst absolutos começando por /home/ ou relativos)
    $fullSrc = (strpos($srcParam, $safeRoot) === 0 || strpos($srcParam, '/home/') === 0) ? $srcParam : $safeRoot . '/' . ltrim($srcParam, '/');
    $fullDst = (strpos($dstParam, $safeRoot) === 0 || strpos($dstParam, '/home/') === 0) ? $dstParam : $safeRoot . '/' . ltrim($dstParam, '/');

    // normalize
    $fullSrc = preg_replace('#/{2,}#', '/', $fullSrc);
    $fullDst = preg_replace('#/{2,}#', '/', $fullDst);

// canonicalize src (src deve existir) com fallback sudo
$canonicalSrc = realpath($fullSrc);

if ($canonicalSrc === false || $canonicalSrc === null) {
    // Tentativa de fallback: usa realpath via sudo (útil quando PHP não tem permissão para resolver)
    $sudoCmdSrc = '/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($fullSrc) . ' 2>/dev/null';
    $sudoOutSrc = trim(@shell_exec($sudoCmdSrc));
    if ($sudoOutSrc !== '') {
        $canonicalSrc = explode("\n", $sudoOutSrc, 2)[0];
    }
}

// Se ainda não encontramos canonicalSrc, incluir info adicional no erro
if ($canonicalSrc === false || $canonicalSrc === null || $canonicalSrc === '') {
    // coletar possíveis pistas para debug
    $attempts = [
        'provided_fullSrc' => $fullSrc,
        'php_realpath_result' => ($canonicalSrc === false || $canonicalSrc === null) ? 'false_or_null' : $canonicalSrc,
    ];
    // opcional: verificar se o arquivo existe sem resolver (stat via sudo)
    $existsViaSudo = trim(@shell_exec('/usr/bin/sudo /bin/test -e ' . escapeshellarg($fullSrc) . ' && echo "yes" || echo "no"'));
    $msg = 'Arquivo/fonte não encontrado: ' . htmlspecialchars($srcParam) . '. Tentativas: ' . json_encode($attempts) . '. exists_via_sudo=' . $existsViaSudo;
    throw new Exception($msg);
}


    // canonicalize dst parent (dst pode não existir; canonicalizamos o pai)
    $dstParent = dirname($fullDst);
    $canonicalDstParent = realpath($dstParent);
    if ($canonicalDstParent === false || $canonicalDstParent === null) {
        // tentar via sudo
        $sudoCmd = '/usr/bin/sudo /bin/realpath -- ' . escapeshellarg($dstParent) . ' 2>/dev/null';
        $sudoOut = trim(@shell_exec($sudoCmd));
        if ($sudoOut !== '') $canonicalDstParent = explode("\n", $sudoOut, 2)[0];
    }
    if ($canonicalDstParent === false || $canonicalDstParent === null) {
        throw new Exception('Diretório de destino não encontrado ou inacessível: ' . htmlspecialchars($dstParent));
    }

    // reforçar jaula: ambos (src e dst parent) devem ficar dentro de SAFE_ROOT
    $safeSlash = $safeRoot . DIRECTORY_SEPARATOR;
    $canonSrcSlash = rtrim($canonicalSrc, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $canonDstParentSlash = rtrim($canonicalDstParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (strpos($canonSrcSlash, $safeSlash) !== 0) {
        throw new Exception('Acesso negado: fonte fora da raiz permitida.');
    }
    if (strpos($canonDstParentSlash, $safeSlash) !== 0) {
        throw new Exception('Acesso negado: destino fora da raiz permitida.');
    }

    // monta canonicalDst final (não usamos realpath pois pode não existir ainda)
    $finalDst = rtrim($canonicalDstParent, DIRECTORY_SEPARATOR) . '/' . basename($fullDst);

    // se finalDst existir e for igual ao src -> nada a fazer
    if ($finalDst === $canonicalSrc) {
        $results['success'] = true;
        $results['src'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($canonicalSrc, strlen($safeRoot)));
        $results['dst'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($finalDst, strlen($safeRoot)));
        $results['message'] = 'Fonte e destino iguais, sem alterações.';
        echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    // tentamos renomear via PHP se tivermos permissão
    $canRenameViaPhp = is_writable(dirname($canonicalSrc)) && is_writable($canonicalDstParent);
    if ($canRenameViaPhp) {
        if (@rename($canonicalSrc, $finalDst)) {
            $results['success'] = true;
            $results['src'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($canonicalSrc, strlen($safeRoot)));
            $results['dst'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($finalDst, strlen($safeRoot)));
            $results['message'] = 'Renomeado (via PHP).';
            echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        } else {
            // se falhar com PHP, coletar erro e tentar wrapper
            $phpErr = error_get_last();
        }
    }

    // se não pôde renomear via PHP, usar wrapper
    $wrapper = '/usr/local/bin/safe_rename.sh';
    if (!file_exists($wrapper)) {
        throw new Exception('Wrapper de rename não encontrado e PHP não tem permissão para renomear. Erro PHP: ' . ($phpErr['message'] ?? 'nenhum detalhe'));
    }

    // montar comando sudo -n (não pede senha)
    $cmd = '/usr/bin/sudo -n ' . escapeshellarg($wrapper) . ' ' . escapeshellarg($canonicalSrc) . ' ' . escapeshellarg($finalDst) . ' 2>&1';
    $raw = @shell_exec($cmd);

    if ($raw === null) {
        $phpUser = trim((string)@shell_exec('whoami'));
        throw new Exception('Erro ao executar comando privilegiado (shell_exec retornou null). Usuário PHP: ' . ($phpUser ?: 'desconhecido'));
    }

    $raw = trim($raw);
    if ($raw === '') {
        $phpUser = trim((string)@shell_exec('whoami'));
        $sudoList = trim((string)@shell_exec('/usr/bin/sudo -n -l -U ' . escapeshellarg($phpUser) . ' 2>&1'));
        throw new Exception('sudo retornou vazio; verifique /etc/sudoers. Usuário PHP: ' . ($phpUser ?: 'desconhecido') . '. sudo -l: ' . substr($sudoList,0,400));
    }

    $first = explode("\n", $raw, 2)[0];
    if (strpos($first, 'OK|') === 0) {
        $parts = explode('|', $first, 2);
        $moved = $parts[1] ?? $finalDst;
        $results['success'] = true;
        $results['src'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($canonicalSrc, strlen($safeRoot)));
        $results['dst'] = str_replace(DIRECTORY_SEPARATOR, '/', substr($moved, strlen($safeRoot)));
        $results['message'] = 'Renomeado com sucesso.';
    } else {
        if (strpos($first, 'ERR|') === 0) {
            $code = explode('|', $first, 2)[1] ?? 'unknown';
            $map = [
                'missing' => 'Parâmetro ausente',
                'src_notfound' => 'Fonte não encontrada',
                'dst_parent_notfound' => 'Pai do destino não existe',
                'forbidden' => 'Destino fora da raiz permitida',
                'mv_failed' => 'Falha ao mover/renomear',
                'exists' => 'Destino já existe'
            ];
            $msg = $map[$code] ?? ('Erro do wrapper: ' . $code);
            throw new Exception($msg . ' — saída: ' . substr($raw,0,400));
        } else {
            throw new Exception('Resposta inesperada do wrapper: ' . substr($raw,0,400));
        }
    }

} catch (Exception $e) {
    $results['success'] = false;
    $results['error'] = $e->getMessage();
}

echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;
