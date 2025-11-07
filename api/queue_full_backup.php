<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$domain = $input['domain'] ?? null;
$root_path = $input['root_path'] ?? null;
$db_id = $input['db_id'] ?? null;

if (empty($domain) || empty($root_path) || empty($db_id)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos (domínio, root ou db_id).']);
    exit;
}

$payload = [
    'domain' => $domain,
    'root_path' => $root_path,
    'db_id' => $db_id
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('full_backup', ?)");
    $stmt->execute([json_encode($payload)]);
    echo json_encode(['success' => true, 'message' => 'Full Backup (site+BD) enfileirado!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
?>