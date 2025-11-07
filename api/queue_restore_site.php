<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$domain = $input['domain'] ?? null;
$root_path = $input['root_path'] ?? null;
$backup_file = $input['backup_file'] ?? null;

if (empty($domain) || empty($root_path) || empty($backup_file)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$payload = [
    'domain' => $domain,
    'root_path' => $root_path,
    'backup_file' => $backup_file
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('restore_site', ?)");
    $stmt->execute([json_encode($payload)]);
    echo json_encode(['success' => true, 'message' => 'Restauração de site enfileirada!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
?>