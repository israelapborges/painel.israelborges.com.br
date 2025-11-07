<?php
// api/sync_cron_to_server.php
require '../config/session_guard.php';
require '../config/db.php'; // assume $pdo
header('Content-Type: application/json');

$results = ['success' => false, 'error' => null];

try {
    // 1) Busca todas as tarefas ativas no banco (ajuste nome da tabela/campo se necessário)
    $stmt = $pdo->query("SELECT * FROM crontab WHERE is_active = 1 ORDER BY id ASC");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Monta o conteúdo do crontab
    $crontab_content = "";
    $crontab_content .= "# --- MyPanel managed crontab (generated at " . date('c') . ") ---\n";
    $crontab_content .= "# Lines and titles here are managed by MyPanel. Manual edits may be overwritten.\n\n";

    foreach ($jobs as $job) {
        $schedule = trim($job['schedule'] ?? '');
        $command  = trim($job['command'] ?? '');
        $title    = trim($job['title'] ?? '');

        if ($schedule === '' || $command === '') {
            // skip invalid entries (optionally log)
            continue;
        }

        // 2a) ensure command ends with a redirection if it doesn't already have one
        // consider it has redirection if contains '>' or '2>' or '2>&1' or '>>' patterns
        if (!preg_match('/(\>\>|\>\s*\/|\>\s*\S+|2>\&1|2>\s|\>\s*2>|\>\s*>\s*)/', $command)) {
            // append default silent redirection
            $command .= ' > /dev/null 2>&1';
        }

        // 2b) add optional title line (comment) above the crontab entry
        if ($title !== '') {
            // sanitize title to avoid newlines or # injection
            $safeTitle = preg_replace("/[\r\n]+/", ' ', $title);
            $safeTitle = str_replace('#', '', $safeTitle);
            $crontab_content .= '# ' . trim($safeTitle) . "\n";
        }

        // 2c) append the schedule + command
        $crontab_content .= $schedule . ' ' . $command . "\n\n";
    }

    // 3) Escreve para ficheiro temporário
    $temp_file = tempnam(sys_get_temp_dir(), 'cron_sync_');
    if ($temp_file === false) {
        throw new Exception('Não foi possível criar ficheiro temporário.');
    }
    file_put_contents($temp_file, $crontab_content);

    // 4) Tentativas de instalar o crontab (tenta como root via sudo primeiro)
    $cmds = [
        '/usr/bin/sudo -n /usr/bin/crontab -u root ' . escapeshellarg($temp_file) . ' 2>&1',
        '/usr/bin/sudo -n /usr/bin/crontab ' . escapeshellarg($temp_file) . ' 2>&1',
        '/usr/bin/crontab ' . escapeshellarg($temp_file) . ' 2>&1'
    ];

    $last_output = '';
    $last_exit = 1;
    foreach ($cmds as $cmd) {
        exec($cmd, $out_lines, $exitCode);
        $last_output = implode("\n", $out_lines);
        $last_exit = (int)$exitCode;
        if ($last_exit === 0) break;
        $out_lines = [];
    }

    // remove temp file
    @unlink($temp_file);

    if ($last_exit !== 0) {
        throw new Exception('Erro ao carregar crontab. Saída: ' . trim($last_output));
    }

    $results['success'] = true;
    $results['message'] = 'Sincronização concluída! O crontab do servidor foi atualizado com ' . count($jobs) . ' tarefa(s).';

} catch (Exception $e) {
    $results['error'] = $e->getMessage();
}

echo json_encode($results, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
exit;
