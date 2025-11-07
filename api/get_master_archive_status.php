<?php
require '../config/session_guard.php';
header('Content-Type: application/json');

$backup_dir = __DIR__ . '/../inc/backups_sites/';
$master_filename = '_MASTER_ARCHIVE.tar.bz2';
$full_path = $backup_dir . $master_filename;

if (file_exists($full_path) && is_readable($full_path)) {
    echo json_encode([
        'success' => true,
        'file_exists' => true,
        'filename' => $master_filename,
        'size_bytes' => filesize($full_path),
        'last_modified' => filemtime($full_path) // Data de modificação (timestamp)
    ]);
} else {
    echo json_encode(['success' => true, 'file_exists' => false]);
}
exit;
?>