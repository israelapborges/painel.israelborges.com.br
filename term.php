<?php
// Exemplo de como reiniciar o Nginx
// (Ainda precisa de validação de entrada!!)
$action = 'restart'; // Isso viria de um botão POST

// Validação CRÍTICA
if ($action === 'restart' || $action === 'stop' || $action === 'start') {
    // O comando 'sudo' é adicionado
    $command = "sudo /usr/sbin/service nginx " . escapeshellarg($action);
    shell_exec($command);
    echo "Nginx foi reiniciado com sucesso!";
}