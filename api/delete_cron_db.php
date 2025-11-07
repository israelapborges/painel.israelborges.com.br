<?php
require '../config/session_guard.php'; 
require '../config/db.php'; // $pdo
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

$results = ['success' => false, 'error' => null];

try {
    if (empty($input['id'])) {
        throw new Exception('ID da tarefa não especificado.');
    }
    
    $id = $input['id'];

    $stmt = $pdo->prepare("DELETE FROM crontab WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        $results['success'] = true;
        $results['message'] = 'Tarefa excluída do banco de dados. Sincronize para aplicar.';
    } else {
        throw new Exception('Tarefa não encontrada.');
    }

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
}

echo json_encode($results);
exit;
?>