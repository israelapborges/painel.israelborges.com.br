<?php
require '../config/session_guard.php';
// Define que a saída é JSON
header('Content-Type: application/json');

// Inclui a conexão com o banco de dados
// NOTA: O 'config/db.php' deve estar acessível
require '../config/db.php'; 

// 1. Pega os dados enviados pelo JavaScript (fetch)
$input = json_decode(file_get_contents('php://input'), true);

$domain = $input['domain'] ?? null;
$root_path = $input['root'] ?? null;

// 2. Validação (CRÍTICA!)
// Isso impede Injeção de Comando (ex: "meusite.com; rm -rf /")
if (empty($domain) || !preg_match('/^(?!\-)[a-z0-9\-\.]{1,253}(?<!\-)$/i', $domain)) {
    echo json_encode(['success' => false, 'message' => 'Nome de domínio inválido.']);
    exit;
}

// 3. Define o caminho raiz padrão (como seu vhost)
if (empty($root_path)) {
    // IMPORTANTE: Altere isso para o seu caminho padrão de sites
    $root_path = '/home/' . str_replace('.', '_', $domain) . '/htdocs'; 
}

// 4. Prepara o "payload" (os dados para o worker)
$payload = [
    'domain' => $domain,
    'root_path' => $root_path,
    'php_version' => '8.1' // Fixo por enquanto, podemos adicionar no formulário depois
];

// 5. Insere a tarefa na fila
try {
    $stmt = $pdo->prepare("INSERT INTO pending_tasks (task_type, payload) VALUES ('create_vhost', ?)");
    $stmt->execute([json_encode($payload)]);
    
    echo json_encode(['success' => true, 'message' => 'Site enfileirado para criação! Deve estar pronto em até 1 minuto.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>