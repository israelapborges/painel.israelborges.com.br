<?php
require '../config/session_guard.php';
// Não precisa do DB

$backup_dir = __DIR__ . '/../inc/backups_sites/';
$master_filename = '_MASTER_ARCHIVE.tar.bz2';
$full_path = realpath($backup_dir . $master_filename);

// Validação de Segurança
if ($full_path === false || strpos($full_path, realpath($backup_dir)) !== 0) {
    die('Acesso negado.');
}
if (!file_exists($full_path) || !is_readable($full_path)) {
    die('Erro: Arquivo mestre não encontrado.');
}

// Força o Download
header('Content-Description: File Transfer');
header('Content-Type: application/x-bzip2'); // .tar.bz2
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