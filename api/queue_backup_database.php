<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$db_id = $input['db_id'] ?? null;

if (empty($db_id)) {
    echo json_encode(['success' => false, 'message' => 'ID do banco não fornecido.']);
    exit;
}

// Busca o nome do DB para o worker
$stmt = $pdo->prepare("SELECT db_name FROM managed_databases WHERE id = ?");
$stmt->execute([$db_id]);
$db_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_info) {
    echo json_encode(['success' => false, 'message' => 'Banco de dados não encontrado.']);
    exit;
}

$payload = [
    'db_id'   => $db_id, // ID da tabela managed_databases
    'db_name' => $db_info['db_name']
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('backup_database', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Backup enfileirado! O arquivo estará pronto para download em breve.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>