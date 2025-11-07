<?php
require '../config/session_guard.php';
require '../config/db.php'; // Precisa do DB para encontrar o nome do arquivo

$conf_name = $_GET['conf_name'] ?? null;

if (empty($conf_name)) {
    die('Nome do site (conf_name) não fornecido.');
}

// 1. Busca o nome do arquivo na tabela 'backup_sites'
$stmt = $pdo->prepare("SELECT last_backup_file FROM backup_sites WHERE conf_name = ?");
$stmt->execute([$conf_name]);
$file_name = $stmt->fetchColumn();

if (empty($file_name)) {
    die('Nenhum backup encontrado para este site.');
}

// 2. Define o caminho seguro (NÃO acessível pela web)
$backup_dir = __DIR__ . '/../inc/backups_sites/';
$full_path = realpath($backup_dir . $file_name);

// 3. Validação de Segurança (Impede 'Directory Traversal')
if ($full_path === false || strpos($full_path, realpath($backup_dir)) !== 0) {
    die('Acesso negado.');
}

if (!file_exists($full_path) || !is_readable($full_path)) {
    die('Erro: Arquivo de backup não encontrado no servidor.');
}

// 4. Força o Download (Stream)
header('Content-Description: File Transfer');
header('Content-Type: application/gzip'); // .tar.gz
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