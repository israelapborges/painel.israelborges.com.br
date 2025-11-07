<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

// Esta API lê a tabela 'backup_sites' e junta (JOIN) a 'managed_databases'
// para encontrar os nomes dos bancos de dados linkados.

try {
    $stmt = $pdo->query("
        SELECT 
            bs.*, 
            md.db_name 
        FROM 
            backup_sites AS bs
        LEFT JOIN 
            managed_databases AS md ON bs.linked_db_id = md.id
        ORDER BY 
            bs.last_backup_date IS NULL ASC, 
            bs.last_backup_date DESC, 
            bs.conf_name ASC
    ");
    
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'sites' => $sites]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>