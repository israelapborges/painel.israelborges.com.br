<?php
require '../config/session_guard.php';
require '../config/db.php'; // $pdo
header('Content-Type: application/json');

$results = ['success' => false, 'jobs' => [], 'error' => null];

try {
    // Lê as tarefas do NOSSO banco de dados
    $stmt = $pdo->query("SELECT * FROM crontab ORDER BY id DESC");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results['success'] = true;
    $results['jobs'] = $jobs;

} catch (Exception $e) {
    $results['error'] = 'Falha ao ler tarefas do banco de dados: ' . $e->getMessage();
}

echo json_encode($results);
exit;
?>