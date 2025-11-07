<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$domain = $input['domain'] ?? null;
$root_path = $input['root_path'] ?? null;

if (empty($domain) || empty($root_path)) {
    echo json_encode(['success' => false, 'message' => 'Domínio ou caminho raiz não fornecido.']);
    exit;
}

$payload = [
    'domain' => $domain,
    'root_path' => $root_path
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('backup_site', ?)");
    $stmt->execute([json_encode($payload)]);
    echo json_encode(['success' => true, 'message' => 'Backup de site enfileirado!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
?>