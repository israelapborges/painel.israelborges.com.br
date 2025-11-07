<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$db_name = $input['db_name'] ?? null;
$db_user = $input['db_user'] ?? null;

// Validação
if (empty($db_name) || empty($db_user)) {
    echo json_encode(['success' => false, 'message' => 'Nome do banco e do usuário são obrigatórios.']);
    exit;
}
// Previne nomes que podem quebrar o worker
if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name) || !preg_match('/^[a-zA-Z0-9_]+$/', $db_user)) {
     echo json_encode(['success' => false, 'message' => 'Nomes de banco/usuário devem conter apenas letras, números e underscore (_).']);
    exit;
}

try {
    // Apenas insere na tabela de gerenciamento
    $stmt = $pdo->prepare("INSERT INTO managed_databases (db_name, db_user) VALUES (?, ?)");
    $stmt->execute([$db_name, $db_user]);
    
    echo json_encode(['success' => true, 'message' => 'Banco de dados registrado no painel com sucesso!']);
} catch (PDOException $e) {
    // Código '23000' é violação de duplicidade (UNIQUE key)
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'Erro: Este banco de dados ou usuário já está registrado no painel.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
}
?>