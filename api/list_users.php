<?php
require '../config/session_guard.php';
header('Content-Type: application/json');

$results = [];
$users = [];
$groups = [];

// 1. Comando: Lista o conteúdo de /home
// Usamos 'sudo -n' (NOPASSWD), que já está configurado nas suas
// outras APIs (get_permissions.php, delete_file.php).
// -l : formato longo (para vermos dono/grupo)
$command = '/usr/bin/sudo -n /bin/ls -l /home 2>&1';
$output = @shell_exec($command);

if ($output === null) {
    // shell_exec está desativado ou falhou
    echo json_encode(['Erro: shell_exec desativado ou falhou.']);
    exit;
}

$lines = explode("\n", trim($output));

// Exemplo de linha que procuramos:
// drwxr-x---  8 israelborges-painel israelborges-painel 4096 Nov  5 12:19 israelborges-painel
// drwxr-x--- 11 user_site_A         user_site_A         4096 Aug  2 15:00 user_site_A

foreach ($lines as $line) {
    // Procura apenas por diretórios (linhas que começam com 'd')
    if (strpos($line, 'd') !== 0) {
        continue;
    }

    // Divide a linha por espaços (múltiplos espaços)
    $parts = preg_split('/\s+/', $line);

    if (count($parts) >= 9) {
        $owner = $parts[2];
        $group = $parts[3];
        $folder_name = $parts[8];

        // Adiciona o dono e o grupo às listas (sem duplicados)
        if (!empty($owner)) $users[$owner] = true;
        if (!empty($group)) $groups[$group] = true;
    }
}

// 2. Monta a lista final no formato que o modal espera ["usuario", "usuario:grupo"]
// (Conforme a documentação do seu permissions-modal.php)

$final_list = [];

// Adiciona todos os utilizadores (donos)
foreach (array_keys($users) as $user) {
    $final_list[] = $user;
}

// Adiciona as combinações "usuario:grupo"
foreach (array_keys($users) as $user) {
    foreach (array_keys($groups) as $group) {
        // Adiciona a combinação principal (ex: user_site_A:user_site_A)
        if ($user === $group) {
            $combination = $user . ':' . $group;
            if (!in_array($combination, $final_list)) {
                $final_list[] = $combination;
            }
        }
    }
}

// Remove duplicados e ordena
$final_list = array_unique($final_list);
sort($final_list);

echo json_encode($final_list);
exit;
?>