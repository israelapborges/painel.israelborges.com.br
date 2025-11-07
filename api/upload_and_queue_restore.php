<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

// --- Diretório de Upload Seguro ---
// NOTA: Crie esta pasta (inc/uploads/) e dê permissão de escrita para o 'www-data'
$upload_dir = __DIR__ . '/../inc/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 1. Validação (Formulário e Arquivo)
$db_id = $_POST['db_id'] ?? null;

if (empty($db_id) || empty($_FILES['backup_file'])) {
    echo json_encode(['success' => false, 'message' => 'Banco de dados ou arquivo não fornecido.']);
    exit;
}

$file = $_FILES['backup_file'];

// Erro no upload?
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo (Código: ' . $file['error'] . ').']);
    exit;
}

// Validação de extensão (segurança)
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if ($ext !== 'sql' && $ext !== 'gz') {
    echo json_encode(['success' => false, 'message' => 'Arquivo inválido. Apenas .sql ou .sql.gz são permitidos.']);
    exit;
}

// 2. Busca o nome do DB (para o worker)
$stmt = $pdo->prepare("SELECT db_name FROM managed_databases WHERE id = ?");
$stmt->execute([$db_id]);
$db_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$db_info) {
    echo json_encode(['success' => false, 'message' => 'Banco de dados de destino não encontrado.']);
    exit;
}

// 3. Move o arquivo de upload
// Cria um nome de arquivo único e seguro
$temp_filename = 'restore_' . $db_info['db_name'] . '_' . time() . '.' . $ext;
$safe_path = $upload_dir . $temp_filename;

if (!move_uploaded_file($file['tmp_name'], $safe_path)) {
    echo json_encode(['success' => false, 'message' => 'Falha ao mover o arquivo para o diretório de uploads.']);
    exit;
}

// 4. Enfileira a tarefa
$payload = [
    'db_id'   => $db_id,
    'db_name' => $db_info['db_name'],
    'uploaded_file_path' => $safe_path // O worker usará este caminho absoluto
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('restore_database_upload', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Upload concluído! A restauração foi enfileirada e começará em breve.']);

} catch (PDOException $e) {
    // Se falhar, apaga o arquivo que subimos
    unlink($safe_path);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>