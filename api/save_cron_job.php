<?php
// save_cron_job.php
// Saves a cron job to DB and synchronizes all saved jobs to root's crontab.
// Minimal changes from original: accepts JSON { schedule, command, title?, id? }
// Appends redirection to /dev/null 2>&1 if command does not include any redirection.
// Requires that the web user can run 'sudo -n crontab -u root <file>' (NOPASSWD).
require '../config/session_guard.php';
require '../config/db.php'; // provides $pdo
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$res = ['success'=>false, 'error'=>null, 'sync_output'=>null];

try {
    if (!is_array($input)) throw new Exception('JSON inválido.');

    $schedule = trim((string)($input['schedule'] ?? ''));
    $command  = trim((string)($input['command'] ?? ''));
    $title    = trim((string)($input['title'] ?? ''));
    $id       = isset($input['id']) ? intval($input['id']) : null;

    if ($schedule === '' || $command === '') {
        throw new Exception('Agenda ou comando ausente.');
    }

    // ensure command ends with a redirection. If it already contains > or 2>&, assume ok.
    if (!preg_match('/[<>]|2>&1/', $command)) {
        // append to discard output by default
        $command = rtrim($command, " \t;") . ' > /dev/null 2>&1';
    }

    // store into DB: if id present update, else insert
    if ($id && $id > 0) {
        $stmt = $pdo->prepare("UPDATE crontab SET schedule = ?, command = ?, title = ? WHERE id = ?");
        $stmt->execute([$schedule, $command, $title, $id]);
        $message = 'Tarefa atualizada no banco de dados.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO crontab (schedule, command, title) VALUES (?, ?, ?)");
        $stmt->execute([$schedule, $command, $title]);
        $message = 'Tarefa salva no banco de dados.';
    }

    // Now rebuild crontab text from DB rows and write to root crontab
    $rows = $pdo->query("SELECT id, schedule, command, COALESCE(title,'') as title FROM crontab ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $lines = [];
    foreach ($rows as $r) {
        $tid = intval($r['id']);
        $t = trim((string)$r['title']);
        if ($t !== '') {
            // sanitize title: remove newlines and leading "# MyPanel" to avoid collisions
            $t = preg_replace("/[\r\n]/", ' ', $t);
            $t = preg_replace('/\s+/', ' ', $t);
            $lines[] = "# MyPanel ID-{$tid} " . $t;
        } else {
            $lines[] = "# MyPanel ID-{$tid}";
        }
        // ensure schedule and command do not contain CR/LF in the middle
        $sched = trim(preg_replace("/[\r\n]+/", ' ', $r['schedule']));
        $cmd   = rtrim(preg_replace("/[\r\n]+/", ' ', $r['command']));
        $lines[] = "{$sched} {$cmd}";
    }

    $new_crontab = implode("\n", $lines) . "\n";

    // write new crontab to a temp file and install with sudo
    $tmp = tempnam(sys_get_temp_dir(), 'crontab_');
    if ($tmp === false) throw new Exception('Falha ao criar temporário.');

    if (file_put_contents($tmp, $new_crontab) === false) {
        @unlink($tmp);
        throw new Exception('Falha ao gravar temporário.');
    }

    // attempt to install as root's crontab
    $cmd = '/usr/bin/sudo -n /usr/bin/crontab -u root ' . escapeshellarg($tmp) . ' 2>&1';
    exec($cmd, $out, $exitCode);
    $outStr = implode("\n", $out);
    @unlink($tmp);

    if ($exitCode !== 0) {
        // do not treat fatal: return success saving to DB but inform user sync failed
        $res['success'] = true;
        $res['message'] = $message . ' Porém houve erro ao sincronizar com crontab root.';
        $res['sync_output'] = $outStr;
        echo json_encode($res);
        exit;
    }

    $res['success'] = true;
    $res['message'] = $message . ' Sincronizado com crontab do root.';
    $res['sync_output'] = $outStr;
    echo json_encode($res);
    exit;

} catch (Exception $e) {
    $res['error'] = $e->getMessage();
    echo json_encode($res);
    exit;
}
