<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

// Define um nome fixo para o arquivo mestre (o worker irá sobrescrevê-lo)
$master_filename = '_MASTER_ARCHIVE.tar.bz2';

$payload = [
    'task' => 'generate_master_archive',
    'output_filename' => $master_filename
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('generate_master_archive', ?)");
    $stmt->execute([json_encode($payload)]);
    echo json_encode(['success' => true, 'message' => 'Tarefa para gerar o arquivo mestre foi enfileirada!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
?>