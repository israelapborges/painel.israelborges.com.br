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

// 1. Busca os dados do banco, incluindo o nome do arquivo de backup
$stmt = $pdo->prepare("SELECT db_name, last_backup_file FROM managed_databases WHERE id = ?");
$stmt->execute([$db_id]);
$db_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_info) {
    echo json_encode(['success' => false, 'message' => 'Banco de dados não encontrado.']);
    exit;
}

if (empty($db_info['last_backup_file'])) {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo de backup associado a este banco. Crie um backup primeiro.']);
    exit;
}

// 2. Define o caminho seguro e verifica se o arquivo existe
$backup_dir = __DIR__ . '/../inc/backups/';
$full_path = realpath($backup_dir . $db_info['last_backup_file']);

if ($full_path === false || strpos($full_path, realpath($backup_dir)) !== 0 || !file_exists($full_path)) {
    echo json_encode(['success' => false, 'message' => 'Erro: O arquivo de backup não foi encontrado no servidor.']);
    exit;
}

// 3. Enfileira a tarefa
$payload = [
    'db_id'   => $db_id,
    'db_name' => $db_info['db_name'],
    'backup_file' => $db_info['last_backup_file'] // Envia o nome do arquivo para o worker
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('restore_database', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Restauração enfileirada! O banco de dados será sobrescrito em até 1 minuto.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>