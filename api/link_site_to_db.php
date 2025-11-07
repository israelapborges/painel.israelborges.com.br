<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$conf_name = $input['conf_name'] ?? null;
$db_id = $input['db_id'] ?? null;

if (empty($conf_name)) {
    echo json_encode(['success' => false, 'message' => 'conf_name do site não fornecido.']);
    exit;
}

// Se o $db_id for "none" (string), definimo-lo como NULL
$db_id_to_save = ($db_id === 'none') ? null : $db_id;

try {
    $stmt = $pdo->prepare("UPDATE backup_sites SET linked_db_id = ? WHERE conf_name = ?");
    $stmt->execute([$db_id_to_save, $conf_name]);
    
    echo json_encode(['success' => true, 'message' => 'Link do banco de dados atualizado!']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>