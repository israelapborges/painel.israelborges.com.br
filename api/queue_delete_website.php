<?php
require '../config/session_guard.php';
// Define que a saída é JSON
header('Content-Type: application/json');

// Inclui a conexão com o banco de dados
require '../config/db.php'; 

// 1. Pega os dados enviados pelo JavaScript (fetch)
$input = json_decode(file_get_contents('php://input'), true);

$domain = $input['domain'] ?? null;
$path = $input['path'] ?? null;

// 2. Validação
if (empty($domain) || empty($path)) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos (domínio ou caminho em falta).']);
    exit;
}

// 3. Prepara o "payload" (os dados para o worker)
$payload = [
    'domain' => $domain,
    'path' => $path,
];

// 4. Insere a tarefa na fila
try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('delete_vhost', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Site enfileirado para exclusão! A ação será executada em até 1 minuto.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>