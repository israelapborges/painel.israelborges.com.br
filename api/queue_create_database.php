<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$db_name = $input['db_name'] ?? null;
$db_user = $input['db_user'] ?? null;
$db_pass = $input['db_pass'] ?? null;

// Validação (simplificada)
if (empty($db_name) || empty($db_user) || empty($db_pass)) {
    echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
    exit;
}
if (strlen($db_pass) < 8) {
    echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 8 caracteres.']);
    exit;
}
// Previne nomes com hífens, espaços, etc., que podem quebrar o SQL
if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name) || !preg_match('/^[a-zA-Z0-9_]+$/', $db_user)) {
     echo json_encode(['success' => false, 'message' => 'Nomes de banco/usuário devem conter apenas letras, números e underscore (_).']);
    exit;
}

$payload = [
    'db_name' => $db_name,
    'db_user' => $db_user,
    'db_pass' => $db_pass
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('create_database', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Banco de dados enfileirado para criação!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>