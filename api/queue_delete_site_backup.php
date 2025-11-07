<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$backup_file = $input['backup_file'] ?? null;
$conf_name = $input['conf_name'] ?? null;

if (empty($backup_file) || empty($conf_name)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos (arquivo ou conf_name).']);
    exit;
}

$payload = [ 
    'backup_file' => $backup_file,
    'conf_name' => $conf_name
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('delete_site_backup', ?)");
    $stmt->execute([json_encode($payload)]);
    echo json_encode(['success' => true, 'message' => 'Exclusão de backup enfileirada!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
?>