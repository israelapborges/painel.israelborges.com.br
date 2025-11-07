<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

// --- Diretório de Upload Seguro ---
$upload_dir = __DIR__ . '/../inc/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// 1. Validação (Formulário e Arquivo)
$domain = $_POST['site_domain'] ?? null;
$root_path = $_POST['site_root_path'] ?? null;

if (empty($domain) || empty($root_path) || empty($_FILES['backup_file'])) {
    echo json_encode(['success' => false, 'message' => 'Domínio, caminho raiz ou arquivo não fornecido.']);
    exit;
}

$file = $_FILES['backup_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Erro no upload (Código: ' . $file['error'] . ').']);
    exit;
}

// Validação de extensão (tar.gz)
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$ext2 = pathinfo(pathinfo($file['name'], PATHINFO_FILENAME), PATHINFO_EXTENSION); // para .tar

if ($ext !== 'gz' || $ext2 !== 'tar') {
    echo json_encode(['success' => false, 'message' => 'Arquivo inválido. Apenas .tar.gz é permitido.']);
    exit;
}

// 3. Move o arquivo de upload
$temp_filename = 'restore_site_' . $domain . '_' . time() . '.tar.gz';
$safe_path = $upload_dir . $temp_filename;

if (!move_uploaded_file($file['tmp_name'], $safe_path)) {
    echo json_encode(['success' => false, 'message' => 'Falha ao mover o arquivo para o diretório de uploads.']);
    exit;
}

// 4. Enfileira a tarefa
$payload = [
    'domain' => $domain,
    'root_path' => $root_path,
    'uploaded_file_path' => $safe_path // O worker usará este caminho absoluto
];

try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('restore_site_upload', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Upload concluído! A restauração foi enfileirada.']);

} catch (PDOException $e) {
    unlink($safe_path);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>