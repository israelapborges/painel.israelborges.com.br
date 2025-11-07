<?php
require '../config/session_guard.php';
// Define que a saída é JSON
header('Content-Type: application/json');

// --- Diretórios Permitidos (Segurança) ---
// Para evitar ataques (Directory Traversal), SÓ permitimos ler
// ficheiros que estejam DENTRO destes diretórios.
$allowed_paths = [
    '/etc/nginx/sites-enabled/',
    '/etc/nginx/sites-available/'
];

$file_path = $_GET['path'] ?? null;

// --- Validação de Segurança Crítica ---
if (!$file_path) {
    echo json_encode(['success' => false, 'message' => 'Caminho do ficheiro não fornecido.']);
    exit;
}

// 1. Resolve o caminho real (ex: /etc/nginx/sites-available/meusite.com.conf)
$real_path = realpath($file_path);
$path_is_safe = false;

// 2. Verifica se o $real_path é válido e se começa com um dos $allowed_paths
if ($real_path !== false && is_readable($real_path)) {
    foreach ($allowed_paths as $allowed) {
        // realpath() no diretório permitido para garantir a comparação correta
        if (strpos($real_path, realpath($allowed)) === 0) {
            $path_is_safe = true;
            break;
        }
    }
}

if (!$path_is_safe) {
    error_log("Tentativa de acesso negada ao ficheiro: " . $file_path);
    echo json_encode(['success' => false, 'message' => 'Acesso negado ou ficheiro não encontrado.']);
    exit;
}

// --- Leitura e Parsing Simples ---
// Se a segurança passou, lemos o conteúdo
$content = file_get_contents($real_path);

$config = [
    'server_name' => '',
    'root' => '',
    'index' => '',
    'php_socket' => ''
];

// Usamos Regex simples para encontrar as diretivas.
// (isto não apanha diretivas dentro de 'includes', mas é um ótimo começo)

// Ex: server_name www.meusite.com meusite.com;
if (preg_match('/^\s*server_name\s+(.+);/m', $content, $matches)) {
    $config['server_name'] = $matches[1];
}
// Ex: root /var/www/meusite;
if (preg_match('/^\s*root\s+(.+);/m', $content, $matches)) {
    $config['root'] = $matches[1];
}
// Ex: index index.php index.html;
if (preg_match('/^\s*index\s+(.+);/m', $content, $matches)) {
    $config['index'] = $matches[1];
}
// Ex: fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
if (preg_match('/^\s*fastcgi_pass\s+(.+);/m', $content, $matches)) {
    // Vamos normalizar o valor para o nosso <select>
    $socket = $matches[1];
    if (strpos($socket, 'php8.1') !== false) $config['php_socket'] = 'unix:/var/run/php/php8.1-fpm.sock';
    elseif (strpos($socket, 'php8.0') !== false) $config['php_socket'] = 'unix:/var/run/php/php8.0-fpm.sock';
    elseif (strpos($socket, 'php7.4') !== false) $config['php_socket'] = 'unix:/var/run/php/php7.4-fpm.sock';
}

echo json_encode(['success' => true, 'config' => $config, 'file_path' => $real_path]);
?>