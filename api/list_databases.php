<?php
require '../config/session_guard.php';
require '../config/db.php';
header('Content-Type: application/json');

try {
    // ATUALIZADO: Seleciona a nova coluna 'last_backup_file'
$stmt = $pdo->query("SELECT id, db_name, db_user, last_backup_file, last_backup_date, last_backup_size FROM managed_databases ORDER BY 
                        last_backup_date IS NULL ASC, 
                        last_backup_date DESC, 
                        db_name ASC");
  $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'databases' => $databases]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>