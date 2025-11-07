<?php
require '../config/session_guard.php';
require '../config/db.php';

$db_id = $_GET['db_id'] ?? null;

if (empty($db_id)) {
    die('ID do banco não fornecido.');
}

// 1. Busca o nome do arquivo no DB
$stmt = $pdo->prepare("SELECT last_backup_file FROM managed_databases WHERE id = ?");
$stmt->execute([$db_id]);
$file_name = $stmt->fetchColumn();

if (empty($file_name)) {
    die('Nenhum backup encontrado para este banco de dados.');
}

// 2. Define o caminho seguro (NÃO acessível pela web)
$backup_dir = __DIR__ . '/../inc/backups/';
$full_path = realpath($backup_dir . $file_name);

// 3. Validação de Segurança
// Garante que o arquivo está DENTRO do diretório de backups
if ($full_path === false || strpos($full_path, realpath($backup_dir)) !== 0) {
    die('Acesso negado. Tentativa de "Directory Traversal".');
}

if (!file_exists($full_path) || !is_readable($full_path)) {
    die('Erro: Arquivo de backup não encontrado no servidor.');
}

// 4. Força o Download (Stream)
header('Content-Description: File Transfer');
header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . basename($full_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($full_path));
ob_clean();
flush();
readfile($full_path);
exit;
?>