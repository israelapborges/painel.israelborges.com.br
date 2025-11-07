<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

try {
    // 1. Encontra todos os sites que têm um banco de dados linkado
    $stmt_sites = $pdo->query("SELECT * FROM backup_sites WHERE linked_db_id IS NOT NULL");
    $sites = $stmt_sites->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sites)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum site encontrado com um banco de dados linkado.']);
        exit;
    }

    // 2. Prepara a query de inserção
    $stmt_task = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('full_backup', ?)");
    
    $count = 0;
    foreach ($sites as $site) {
        $payload = [
            'domain' => $site['conf_name'],
            'root_path' => $site['root_path'],
            'db_id' => $site['linked_db_id']
        ];
        $stmt_task->execute([json_encode($payload)]);
        $count++;
    }
    
    echo json_encode(['success' => true, 'message' => "$count tarefas de Full Backup foram enfileiradas com sucesso!"]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>