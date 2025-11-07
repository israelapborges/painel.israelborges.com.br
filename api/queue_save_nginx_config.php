<?php
// 1. PROTEGER A API
require '../config/session_guard.php';

// 2. Incluir DB e definir tipo de resposta
require '../config/db.php';
header('Content-Type: application/json');

// 3. Pega os dados enviados pelo JavaScript (fetch)
$input = json_decode(file_get_contents('php://input'), true);

// 4. Validação
if (empty($input['config_file_path']) || empty($input['server_name']) || empty($input['root'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos (caminho, domínio ou raiz em falta).']);
    exit;
}

// O 'payload' é simplesmente todos os dados que o JS enviou
$payload = $input;

// 5. Insere a tarefa na fila
try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('edit_vhost', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Configuração enfileirada para salvar! As alterações devem ser aplicadas em até 1 minuto.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e.getMessage()]);
}
?>