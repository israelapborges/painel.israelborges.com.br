<?php
require '../config/session_guard.php';

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

function run_command(string $command): string
{
    $output = @shell_exec($command);
    if ($output === null) {
        return '';
    }
    return trim($output);
}

function to_float($value, float $default = 0.0): float
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (float)$value;
}

function to_int($value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (int)$value;
}

function human_bytes(float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $bytes = max($bytes, 0);
    $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $power = min($power, count($units) - 1);
    $value = $bytes / pow(1024, $power);
    return round($value, 2) . ' ' . $units[$power];
}

function format_percentage(float $value): float
{
    return round($value, 1);
}

function get_load_information(): array
{
    $loadAvgRaw = run_command("LC_ALL=C uptime | awk -F'load average: |carga média: ' '{print \$2}' | cut -d, -f1 | tr ',' '.'");
    $loadAvg = to_float($loadAvgRaw);

    $coresRaw = run_command('nproc');
    $cores = max(1, to_int($coresRaw, 1));

    $loadPercent = $cores > 0 ? format_percentage(($loadAvg / $cores) * 100) : 0;

    return [
        'load_avg' => $loadAvg,
        'cpu_cores' => $cores,
        'load_percent' => $loadPercent,
    ];
}

function get_memory_information(): array
{
    $ramOutput = run_command("free -m | grep 'Mem:' | awk '{print \$3, \$2, \$7}'");
    $swapOutput = run_command("free -m | grep 'Swap:' | awk '{print \$3, \$2}'");

    $memory = [
        'ram_used_mb' => 0,
        'ram_total_mb' => 0,
        'ram_free_mb' => 0,
        'ram_percent' => 0.0,
        'swap_used_mb' => 0,
        'swap_total_mb' => 0,
        'swap_percent' => 0.0,
    ];

    if (!empty($ramOutput)) {
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim($ramOutput)));
        if (count($parts) >= 2) {
            $memory['ram_used_mb'] = to_int($parts[0]);
            $memory['ram_total_mb'] = to_int($parts[1]);
            $memory['ram_free_mb'] = isset($parts[2]) ? to_int($parts[2]) : 0;

            if ($memory['ram_total_mb'] > 0) {
                $memory['ram_percent'] = format_percentage(($memory['ram_used_mb'] / $memory['ram_total_mb']) * 100);
            }
        }
    }

    if (!empty($swapOutput)) {
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim($swapOutput)));
        if (count($parts) >= 2) {
            $memory['swap_used_mb'] = to_int($parts[0]);
            $memory['swap_total_mb'] = to_int($parts[1]);
            if ($memory['swap_total_mb'] > 0) {
                $memory['swap_percent'] = format_percentage(($memory['swap_used_mb'] / $memory['swap_total_mb']) * 100);
            }
        }
    }

    return $memory;
}

function get_filesystem_information(): array
{
    $filesystems = [];
    $dfOutput = run_command('df -P -k --output=target,used,size,pcent');

    if (!empty($dfOutput)) {
        $lines = explode("\n", trim($dfOutput));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'target') === 0) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if (count($parts) !== 4) {
                continue;
            }

            list($mount, $usedKb, $sizeKb, $percent) = $parts;
            $usedKb = to_int($usedKb);
            $sizeKb = to_int($sizeKb);
            $percentage = (int)str_replace('%', '', $percent);

            $filesystems[] = [
                'mount' => $mount,
                'used_gb' => round($usedKb / 1024 / 1024, 2),
                'total_gb' => round($sizeKb / 1024 / 1024, 2),
                'percent' => $percentage,
                'is_root' => $mount === '/',
            ];
        }
    }

    return $filesystems;
}

function get_network_information(): array
{
    $interfaces = [];
    $totalRx = 0;
    $totalTx = 0;

    $lines = @file('/proc/net/dev');
    if ($lines !== false) {
        foreach ($lines as $index => $line) {
            if ($index < 2) {
                continue;
            }
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }

            list($interface, $stats) = array_map('trim', explode(':', $line, 2));
            if ($interface === 'lo') {
                continue;
            }

            $parts = preg_split('/\s+/', trim($stats));
            if (count($parts) < 16) {
                continue;
            }

            $rxBytes = to_float($parts[0]);
            $txBytes = to_float($parts[8]);

            $interfaces[] = [
                'name' => $interface,
                'rx_bytes' => $rxBytes,
                'tx_bytes' => $txBytes,
                'rx_human' => human_bytes($rxBytes),
                'tx_human' => human_bytes($txBytes),
            ];

            $totalRx += $rxBytes;
            $totalTx += $txBytes;
        }
    }

    return [
        'interfaces' => $interfaces,
        'total_rx_bytes' => $totalRx,
        'total_tx_bytes' => $totalTx,
        'total_rx_human' => human_bytes($totalRx),
        'total_tx_human' => human_bytes($totalTx),
    ];
}

function parse_sensor_payload(array $payload, string $fallbackLabel): ?array
{
    foreach ($payload as $key => $value) {
        if (substr($key, -6) === '_input' && is_numeric($value)) {
            $base = substr($key, 0, -6);
            $label = $payload['label'] ?? $fallbackLabel;
            $high = $payload[$base . '_max'] ?? ($payload[$base . '_high'] ?? null);
            $critical = $payload[$base . '_crit'] ?? null;

            return [
                'label' => $label,
                'value' => round((float)$value, 1),
                'high' => $high !== null ? round((float)$high, 1) : null,
                'critical' => $critical !== null ? round((float)$critical, 1) : null,
            ];
        }
    }

    return null;
}

function get_temperature_information(): array
{
    $temperatures = [];
    $sensorsJson = run_command('sensors -j 2>/dev/null');

    if (!empty($sensorsJson)) {
        $decoded = json_decode($sensorsJson, true);
        if (is_array($decoded)) {
            foreach ($decoded as $chip => $entries) {
                if (!is_array($entries)) {
                    continue;
                }

                foreach ($entries as $name => $payload) {
                    if (!is_array($payload)) {
                        continue;
                    }

                    $info = parse_sensor_payload($payload, $name);
                    if ($info !== null) {
                        $info['chip'] = $chip;
                        $temperatures[] = $info;
                    }
                }
            }
        }
    }

    if (empty($temperatures)) {
        $thermalPaths = glob('/sys/class/thermal/thermal_zone*/temp');
        if ($thermalPaths !== false) {
            foreach ($thermalPaths as $path) {
                $tempValue = @file_get_contents($path);
                if ($tempValue === false) {
                    continue;
                }
                $tempValue = trim($tempValue);
                if ($tempValue === '') {
                    continue;
                }

                $temp = ((float)$tempValue) / 1000;
                $labelPath = str_replace('temp', 'type', $path);
                $label = @file_get_contents($labelPath);
                $label = $label !== false ? trim($label) : basename(dirname($path));

                $temperatures[] = [
                    'label' => $label,
                    'value' => round($temp, 1),
                    'high' => null,
                    'critical' => null,
                    'chip' => 'thermal_zone',
                ];
            }
        }
    }

    return $temperatures;
}

function get_top_processes(): array
{
    $processes = [];
    $processOutput = run_command("ps -eo pid,comm,%cpu,%mem --sort=-%cpu | head -n 6");
    if (!empty($processOutput)) {
        $lines = explode("\n", trim($processOutput));
        if (!empty($lines)) {
            array_shift($lines);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $parts = preg_split('/\s+/', $line);
                if (count($parts) < 4) {
                    continue;
                }

                $pid = array_shift($parts);
                $command = array_shift($parts);
                $cpu = array_shift($parts);
                $mem = array_shift($parts);

                $processes[] = [
                    'pid' => (int)$pid,
                    'command' => $command,
                    'cpu' => round((float)$cpu, 1),
                    'memory' => round((float)$mem, 1),
                ];
            }
        }
    }

    return $processes;
}

function collect_alerts(array $stats): array
{
    $alerts = [];

    if (($stats['load_percent'] ?? 0) > 90) {
        $alerts[] = [
            'type' => 'danger',
            'message' => 'Carga da CPU muito alta',
        ];
    } elseif (($stats['load_percent'] ?? 0) > 75) {
        $alerts[] = [
            'type' => 'warning',
            'message' => 'Carga da CPU elevada',
        ];
    }

    if (($stats['ram_percent'] ?? 0) > 85) {
        $alerts[] = [
            'type' => 'warning',
            'message' => 'Uso de memória acima de 85%' . (($stats['ram_percent'] ?? 0) > 95 ? ' (possível saturação)' : ''),
        ];
    }

    if (!empty($stats['filesystem'])) {
        foreach ($stats['filesystem'] as $fs) {
            if (($fs['percent'] ?? 0) >= 95) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Volume ' . ($fs['mount'] ?? '?') . ' está praticamente cheio (' . ($fs['percent'] ?? 0) . '%)',
                ];
            } elseif (($fs['percent'] ?? 0) >= 85) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => 'Volume ' . ($fs['mount'] ?? '?') . ' com uso elevado (' . ($fs['percent'] ?? 0) . '%)',
                ];
            }
        }
    }

    if (!empty($stats['temperatures'])) {
        foreach ($stats['temperatures'] as $temp) {
            $value = $temp['value'] ?? 0;
            $label = $temp['label'] ?? 'sensor';
            if ($value >= 95) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Temperatura crítica em ' . $label . ' (' . $value . 'ºC)',
                ];
            } elseif ($value >= 80) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => 'Temperatura alta em ' . $label . ' (' . $value . 'ºC)',
                ];
            }
        }
    }

    return $alerts;
}

$system = get_load_information();
$memory = get_memory_information();
$filesystem = get_filesystem_information();
$network = get_network_information();
$temperatures = get_temperature_information();
$processes = get_top_processes();

$stats = array_merge(
    [
        'hostname' => run_command('hostname'),
        'kernel' => run_command('uname -r'),
        'architecture' => run_command('uname -m'),
        'uptime_pretty' => run_command('uptime -p'),
        'uptime_seconds' => to_float(run_command("cut -d' ' -f1 /proc/uptime")),
        'last_boot' => run_command("who -b | awk '{print \$3 \" \" \$4}'"),
        'timestamp' => date(DATE_ATOM),
        'overview' => [
            'sites' => 105,
            'ftp' => 83,
            'db' => 107,
        ],
    ],
    $system,
    $memory
);

$stats['filesystem'] = $filesystem;
$stats['network'] = $network;
$stats['temperatures'] = $temperatures;
$stats['top_processes'] = $processes;
$stats['alerts'] = collect_alerts(array_merge($stats, [
    'filesystem' => $filesystem,
    'temperatures' => $temperatures,
]));

$rootFilesystem = array_filter($filesystem, function ($item) {
    return ($item['mount'] ?? '') === '/';
});

if (!empty($rootFilesystem)) {
    $root = array_values($rootFilesystem)[0];
    $stats['disk_root_gb'] = $root['used_gb'];
    $stats['disk_root_total_gb'] = $root['total_gb'];
    $stats['disk_root_percent'] = $root['percent'];
} else {
    $stats['disk_root_gb'] = 0;
    $stats['disk_root_total_gb'] = 0;
    $stats['disk_root_percent'] = 0;
}

// Ajuste de compatibilidade
$stats['load_avg'] = format_percentage($stats['load_avg'] ?? 0);

$stats['network']['interfaces'] = array_values($stats['network']['interfaces'] ?? []);
$stats['temperatures'] = array_values($stats['temperatures']);
$stats['top_processes'] = array_values($stats['top_processes']);
$stats['filesystem'] = array_values($stats['filesystem']);
$stats['alerts'] = array_values($stats['alerts']);

echo json_encode($stats, JSON_UNESCAPED_SLASHES);
exit;
