<?php
require '../config/session_guard.php'; 
header('Content-Type: application/json');

// O caminho COMPLETO para o seu worker.php
// DEVE ser idêntico ao que está no ficheiro /etc/sudoers.d/mypanel_worker
$worker_path = '/home/israelborges-painel/htdocs/painel.israelborges.com.br/worker.php';

// O comando que vamos executar (o 'sudo' foi permitido no Passo 1)
$command = 'sudo /usr/bin/php ' . $worker_path;

// Executa o comando em SEGUNDO PLANO
// O ' > /dev/null 2>&1 &' faz com que a API responda IMEDIATAMENTE,
// enquanto o worker corre "por trás", como você quer.
shell_exec($command . ' > /dev/null 2>&1 &');

echo json_encode([
    'success' => true,
    'message' => 'O worker foi acionado em segundo plano. As tarefas pendentes devem começar em breve.'
]);
exit;
?>