<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

// A lógica de listagem agora é:
// 1. O Worker (root) atualiza a tabela 'backup_sites' a cada minuto.
// 2. Esta API (www-data) apenas lê essa tabela de forma segura.
// Isso é rápido, seguro e não dá erro de timeout.

try {
    // Lê a nova tabela que o worker gerencia
    $stmt = $pdo->query("SELECT * FROM backup_sites ORDER BY 
                            last_backup_date IS NULL ASC, 
                            last_backup_date DESC, 
                            conf_name ASC");
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'sites' => $sites]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>