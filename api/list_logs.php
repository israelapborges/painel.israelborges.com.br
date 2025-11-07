<?php
require '../config/session_guard.php'; // Protege a API
require '../config/db.php'; // Inclui o DB
header('Content-Type: application/json');

// --- CONFIGURAÇÃO DE SEGURANÇA ---
// Padrões de arquivos permitidos
$allowed_patterns = [
    '/var/log/nginx/error.log',       // Log de erro global do Nginx
    '/home/*/logs/nginx/*.log'      // Logs de todos os usuários
];

$logs_list = [];

// --- 1. LER LOGS DE TAREFAS (do Banco de Dados) ---
try {
    $stmt = $pdo->query("SELECT id, task_type, payload, status, log FROM pending_tasks 
                         WHERE status IN ('complete', 'failed') 
                         AND log IS NOT NULL AND log != '' 
                         ORDER BY id DESC LIMIT 50");
    $tasks = $stmt->fetchAll();
    
    foreach ($tasks as $task) {
        $payload_data = json_decode($task['payload'], true);
        $domain_name = $payload_data['domain'] ?? ($payload_data['server_name'] ?? 'indefinido');
        $text = "Tarefa #{$task['id']} [{$task['status']}] - {$task['task_type']} @ {$domain_name}";
        $value = "task:{$task['id']}";
        $logs_list[] = ['value' => $value, 'text' => $text];
    }
    
    if (count($tasks) > 0) {
         $logs_list[] = ['value' => '', 'text' => '--- Logs de Arquivo (Servidor) ---', 'disabled' => true];
    }

} catch (PDOException $e) {
    // Falha em silêncio
}


// --- 2. LER LOGS DE ARQUIVO (do Filesystem) ---
$found_files = [];
foreach ($allowed_patterns as $pattern) {
    // glob() encontra todos os arquivos que correspondem ao padrão
    foreach (glob($pattern) as $filename) {
        // Adiciona apenas se for um arquivo e se o www-data puder ler
        if (is_file($filename) && is_readable($filename)) {
            $found_files[$filename] = $filename; // Usar a chave previne duplicatas
        }
    }
}

// Formata os arquivos encontrados para o <select>
foreach ($found_files as $full_path) {
    // Cria um nome "amigável", removendo o /home/ ou /var/log/
    $friendly_name = $full_path;
    if (strpos($full_path, '/home/') === 0) {
        // Transforma /home/user/logs/nginx/error.log em user/logs/nginx/error.log
        $friendly_name = preg_replace('|^/home/([^/]+)/|', '$1/', $full_path);
    } elseif (strpos($full_path, '/var/log/') === 0) {
        // Transforma /var/log/nginx/error.log em nginx/error.log
        $friendly_name = str_replace('/var/log/', '', $full_path);
    }

    // O 'value' é o caminho absoluto, 'text' é o nome amigável
    $logs_list[] = ['value' => $full_path, 'text' => $friendly_name];
}

echo json_encode(['success' => true, 'logs' => $logs_list]);
exit;
?>