<?php
require '../config/session_guard.php'; // Protege a API
require '../config/db.php'; // Inclui o DB
header('Content-Type: application/json');

// --- CONFIGURAÇÃO DE SEGURANÇA ---
// Padrões de arquivos permitidos
$allowed_patterns = [
    '/var/log/nginx/error.log',
    '/home/*/logs/nginx/*.log'
];
$linhas_para_ler = 200;

// O 'file' agora é um caminho absoluto (ex: /home/user/logs/nginx/access.log)
$file_path = $_GET['file'] ?? null;

if (empty($file_path)) {
    echo json_encode(['success' => false, 'content' => 'Nome do arquivo não fornecido.']);
    exit;
}


// --- LÓGICA DE LEITURA ---
try {
    $content = null;

    // --- 1. Verifica se é um log de Tarefa (task:ID) ---
    if (strpos($file_path, 'task:') === 0) {
        
        list($prefix, $id) = explode(':', $file_path);
        $task_id = (int)$id;

        if ($task_id > 0) {
            $stmt = $pdo->prepare("SELECT log FROM pending_tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            if ($task && !empty($task['log'])) {
                $content = $task['log'];
            } else {
                throw new Exception("Log da tarefa #$task_id não encontrado ou está vazio.");
            }
        }

    } else {
        // --- 2. Verifica se é um log de Arquivo ---
        
        // --- VALIDAÇÃO DE SEGURANÇA ---
        $is_allowed = false;
        foreach ($allowed_patterns as $pattern) {
            // fnmatch() verifica se o caminho corresponde ao padrão (ex: /home/*/logs...)
            if (fnmatch($pattern, $file_path)) {
                $is_allowed = true;
                break;
            }
        }

        // Verifica também se o arquivo é realmente legível (dupla checagem)
        if (!$is_allowed || !is_file($file_path) || !is_readable($file_path)) {
            throw new Exception("Acesso negado ao arquivo '$file_path'. Arquivo não permitido ou não legível.");
        }
        // --- Fim da Validação ---

        $command = 'tail -n ' . $linhas_para_ler . ' ' . escapeshellarg($file_path);
        $content = shell_exec($command);
        
        if ($content === null) {
            throw new Exception("Comando 'tail' falhou.");
        }
    }
    
    // IMPORTANTE: Escapa o HTML para prevenir ataques XSS
    $safe_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    echo json_encode(['success' => true, 'content' => $safe_content]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'content' => "Erro ao ler o log: " . $e->getMessage()]);
}
exit;
?>