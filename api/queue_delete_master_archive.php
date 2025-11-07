<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

// O nome do arquivo é fixo, pois só existe um
$master_filename = '_MASTER_ARCHIVE.tar.bz2';

$payload = [
    'task' => 'delete_master_archive',
    'filename' => $master_filename
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('delete_master_archive', ?)");
    $stmt->execute([json_encode($payload)]);
    echo json_encode(['success' => true, 'message' => 'Tarefa para excluir o arquivo mestre foi enfileirada!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
?>