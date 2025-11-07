<?php
require '../config/session_guard.php';
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

$warnings = [];

$modules = [
    'websites' => [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
    ],
    'backups' => [
        'available' => false,
        'tracked_sites' => 0,
        'with_backup' => 0,
        'without_backup' => 0,
        'stale_backups' => 0,
        'latest' => null,
    ],
    'databases' => [
        'available' => false,
        'total' => 0,
        'with_backup' => 0,
        'without_backup' => 0,
        'stale_backups' => 0,
        'latest' => null,
    ],
    'full_backups' => [
        'has_archive' => false,
        'filename' => '_MASTER_ARCHIVE.tar.bz2',
        'last_modified' => null,
        'size_bytes' => 0,
    ],
    'cron' => [
        'available' => false,
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
    ],
    'security' => [
        'available' => false,
        'total_rules' => 0,
        'allow_rules' => 0,
        'deny_rules' => 0,
    ],
    'queue' => [
        'available' => false,
        'pending' => 0,
        'processing' => 0,
        'complete' => 0,
        'failed' => 0,
        'file_operations_pending' => 0,
        'oldest_pending' => null,
    ],
    'logs' => [
        'available' => true,
        'total_options' => 0,
        'task_entries' => 0,
        'file_entries' => 0,
    ],
    'settings' => [
        'available' => false,
        'panel_version' => null,
        'generated_at' => null,
    ],
];

$pdo = null;
$pdoAvailable = false;
try {
    require '../config/db.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdoAvailable = true;
    }
} catch (Throwable $e) {
    $warnings[] = 'Banco de dados indisponível: ' . $e->getMessage();
    $pdo = null;
}

// --- Websites (conta sites ativos/inativos) ---
$enabledPath = '/etc/nginx/sites-enabled/';
$availablePath = '/etc/nginx/sites-available/';
$sites = [];

$enabledList = @scandir($enabledPath);
if ($enabledList === false) {
    $warnings[] = 'Não foi possível ler ' . $enabledPath . '.';
} else {
    foreach ($enabledList as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        if (substr($file, -5) !== '.conf' || $file === 'default.conf') {
            continue;
        }
        $sites[$file] = 'active';
    }
}

$availableList = @scandir($availablePath);
if ($availableList === false) {
    $warnings[] = 'Não foi possível ler ' . $availablePath . '.';
} else {
    foreach ($availableList as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        if (substr($file, -5) !== '.conf' || $file === 'default.conf') {
            continue;
        }
        if (!isset($sites[$file])) {
            $sites[$file] = 'inactive';
        }
    }
}

$activeCount = 0;
foreach ($sites as $status) {
    if ($status === 'active') {
        $activeCount++;
    }
}
$totalSites = count($sites);
$modules['websites']['total'] = $totalSites;
$modules['websites']['active'] = $activeCount;
$modules['websites']['inactive'] = max(0, $totalSites - $activeCount);

// --- Backups de sites ---
if ($pdoAvailable) {
    $modules['backups']['available'] = true;
    try {
        $stmt = $pdo->query('SELECT conf_name, last_backup_date, last_backup_size FROM backup_sites');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $modules['backups']['tracked_sites'] = count($rows);

        $staleThreshold = new DateTimeImmutable('-24 hours', new DateTimeZone('UTC'));
        $latestRow = null;
        $latestDate = null;
        foreach ($rows as $row) {
            if (!empty($row['last_backup_date'])) {
                $modules['backups']['with_backup']++;
                try {
                    $dateObj = new DateTimeImmutable($row['last_backup_date'], new DateTimeZone('UTC'));
                    if ($dateObj < $staleThreshold) {
                        $modules['backups']['stale_backups']++;
                    }
                    if ($latestDate === null || $dateObj > $latestDate) {
                        $latestDate = $dateObj;
                        $latestRow = $row;
                    }
                } catch (Exception $ignored) {
                    // Ignora datas inválidas
                }
            }
        }
        $modules['backups']['without_backup'] = $modules['backups']['tracked_sites'] - $modules['backups']['with_backup'];
        if ($latestRow && $latestDate) {
            $modules['backups']['latest'] = [
                'site' => preg_replace('/\.conf$/', '', $latestRow['conf_name']),
                'date' => $latestDate->format(DATE_ATOM),
                'size_bytes' => isset($latestRow['last_backup_size']) ? (int)$latestRow['last_backup_size'] : null,
            ];
        }
    } catch (Throwable $e) {
        $warnings[] = 'Erro ao consultar backups de sites: ' . $e->getMessage();
    }
}

// --- Bancos de dados ---
if ($pdoAvailable) {
    $modules['databases']['available'] = true;
    try {
        $stmt = $pdo->query('SELECT db_name, last_backup_date, last_backup_size FROM managed_databases');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $modules['databases']['total'] = count($rows);

        $staleThreshold = new DateTimeImmutable('-24 hours', new DateTimeZone('UTC'));
        $latestRow = null;
        $latestDate = null;
        foreach ($rows as $row) {
            if (!empty($row['last_backup_date'])) {
                $modules['databases']['with_backup']++;
                try {
                    $dateObj = new DateTimeImmutable($row['last_backup_date'], new DateTimeZone('UTC'));
                    if ($dateObj < $staleThreshold) {
                        $modules['databases']['stale_backups']++;
                    }
                    if ($latestDate === null || $dateObj > $latestDate) {
                        $latestDate = $dateObj;
                        $latestRow = $row;
                    }
                } catch (Exception $ignored) {
                    // Ignora datas inválidas
                }
            }
        }
        $modules['databases']['without_backup'] = $modules['databases']['total'] - $modules['databases']['with_backup'];
        if ($latestRow && $latestDate) {
            $modules['databases']['latest'] = [
                'name' => $latestRow['db_name'],
                'date' => $latestDate->format(DATE_ATOM),
                'size_bytes' => isset($latestRow['last_backup_size']) ? (int)$latestRow['last_backup_size'] : null,
            ];
        }
    } catch (Throwable $e) {
        $warnings[] = 'Erro ao consultar bancos de dados: ' . $e->getMessage();
    }
}

// --- Full Backup (arquivo mestre) ---
$backupDir = realpath(__DIR__ . '/../inc/backups_sites');
if ($backupDir === false) {
    $warnings[] = 'Diretório de backups completo não encontrado.';
} else {
    $masterPath = $backupDir . DIRECTORY_SEPARATOR . $modules['full_backups']['filename'];
    if (is_file($masterPath) && is_readable($masterPath)) {
        $modules['full_backups']['has_archive'] = true;
        $modules['full_backups']['size_bytes'] = (int)filesize($masterPath);
        $modified = filemtime($masterPath);
        if ($modified !== false) {
            $modules['full_backups']['last_modified'] = gmdate(DATE_ATOM, $modified);
        }
    }
}

// --- Cron ---
if ($pdoAvailable) {
    $modules['cron']['available'] = true;
    try {
        $stmt = $pdo->query('SELECT is_active, COUNT(*) AS total FROM crontab GROUP BY is_active');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count = (int)$row['total'];
            if ((int)$row['is_active'] === 1) {
                $modules['cron']['active'] += $count;
            } else {
                $modules['cron']['inactive'] += $count;
            }
            $modules['cron']['total'] += $count;
        }
    } catch (Throwable $e) {
        $warnings[] = 'Erro ao consultar tarefas Cron: ' . $e->getMessage();
    }
}

// --- Segurança (UFW) ---
if ($pdoAvailable) {
    $modules['security']['available'] = true;
    try {
        $stmt = $pdo->query('SELECT action, COUNT(*) AS total FROM ufw GROUP BY action');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count = (int)$row['total'];
            $action = strtolower($row['action']);
            if ($action === 'allow') {
                $modules['security']['allow_rules'] += $count;
            } elseif ($action === 'deny' || $action === 'reject') {
                $modules['security']['deny_rules'] += $count;
            }
            $modules['security']['total_rules'] += $count;
        }
    } catch (Throwable $e) {
        $warnings[] = 'Erro ao consultar regras UFW: ' . $e->getMessage();
    }
}

// --- Fila de tarefas ---
if ($pdoAvailable) {
    $modules['queue']['available'] = true;
    try {
        $stmt = $pdo->query('SELECT status, COUNT(*) AS total FROM pending_tasks GROUP BY status');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = $row['status'];
            $count = (int)$row['total'];
            if (isset($modules['queue'][$status])) {
                $modules['queue'][$status] += $count;
            }
        }

        $stmtOldest = $pdo->query("SELECT MIN(created_at) AS created_at FROM pending_tasks WHERE status = 'pending'");
        $oldest = $stmtOldest->fetchColumn();
        if ($oldest) {
            try {
                $modules['queue']['oldest_pending'] = (new DateTimeImmutable($oldest, new DateTimeZone('UTC')))->format(DATE_ATOM);
            } catch (Exception $ignored) {
                // Ignora datas inválidas
            }
        }

        $stmtFiles = $pdo->query("SELECT COUNT(*) FROM pending_tasks WHERE status = 'pending' AND task_type IN ('compress_file','copy_file','move_file','extract_file')");
        $modules['queue']['file_operations_pending'] = (int)$stmtFiles->fetchColumn();
    } catch (Throwable $e) {
        $warnings[] = 'Erro ao consultar fila de tarefas: ' . $e->getMessage();
    }
}

// --- Logs ---
try {
    $logPatterns = [
        '/var/log/nginx/*.log',
        '/home/*/logs/nginx/*.log',
    ];
    $foundFiles = [];
    foreach ($logPatterns as $pattern) {
        $files = glob($pattern);
        if ($files === false) {
            continue;
        }
        foreach ($files as $file) {
            if (is_file($file) && is_readable($file)) {
                $foundFiles[$file] = true;
            }
        }
    }
    $modules['logs']['file_entries'] = count($foundFiles);
    $modules['logs']['total_options'] += $modules['logs']['file_entries'];
} catch (Throwable $e) {
    $warnings[] = 'Erro ao analisar logs do sistema: ' . $e->getMessage();
}

if ($pdoAvailable) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM pending_tasks WHERE status IN ('complete','failed') AND log IS NOT NULL AND log != ''");
        $modules['logs']['task_entries'] = (int)$stmt->fetchColumn();
        $modules['logs']['total_options'] += $modules['logs']['task_entries'];
    } catch (Throwable $e) {
        $warnings[] = 'Erro ao consultar logs do worker: ' . $e->getMessage();
    }
}

// --- Configurações ---
if ($pdoAvailable) {
    $modules['settings']['available'] = true;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM panel_settings WHERE setting_name = 'panel_version' LIMIT 1");
        $stmt->execute();
        $modules['settings']['panel_version'] = $stmt->fetchColumn() ?: null;
    } catch (Throwable $e) {
        $warnings[] = 'Erro ao ler configurações do painel: ' . $e->getMessage();
    }
}

$generatedAt = gmdate(DATE_ATOM);
$modules['settings']['generated_at'] = $generatedAt;

$response = [
    'success' => true,
    'modules' => $modules,
    'warnings' => $warnings,
    'generated_at' => $generatedAt,
];

echo json_encode($response);
exit;
