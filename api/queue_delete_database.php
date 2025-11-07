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

// Busca os nomes do DB e do Usuário para o worker saber o que apagar
$stmt = $pdo->prepare("SELECT db_name, db_user FROM managed_databases WHERE id = ?");
$stmt->execute([$db_id]);
$db_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_info) {
    echo json_encode(['success' => false, 'message' => 'Banco de dados não encontrado no painel.']);
    exit;
}

$payload = [
    'db_id'   => $db_id, // ID da tabela managed_databases
    'db_name' => $db_info['db_name'],
    'db_user' => $db_info['db_user']
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('delete_database', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Banco de dados enfileirado para exclusão!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>