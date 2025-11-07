<?php
require '../config/session_guard.php';
// FIX 1: Suprime todos os erros de PHP (Warnings, Notices)
// Isso garante que a saída seja *apenas* JSON.
ini_set('display_errors', 0);
error_reporting(0);

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');

// Inicializa o array de dados com valores padrão
$stats = [
    'load_avg' => 0,
    'cpu_cores' => 1,
    'ram_used_mb' => 0,
    'ram_total_mb' => 0,
    'ram_percent' => 0,
    'disk_root_gb' => 0,
    'disk_root_total_gb' => 0,
    'disk_root_percent' => 0,
    'load_percent' => 0,
    'overview' => [
        'sites' => 105, // Placeholder
        'ftp' => 83,   // Placeholder
        'db' => 107     // Placeholder
    ]
];

// --- 1. Carga do Sistema (Load) e Núcleos de CPU ---
try {
    // FIX 2: Adiciona "carga média" (Português) e substitui vírgula por ponto
    $load_avg_raw = shell_exec("uptime | awk -F'load average: |carga média: ' '{print $2}' | cut -d, -f1 | tr ',' '.'");
    $stats['load_avg'] = (float)trim($load_avg_raw ?? 0);

    $cores_raw = shell_exec('nproc');
    $stats['cpu_cores'] = (int)trim($cores_raw ?? 1);

    if ($stats['cpu_cores'] > 0) {
        $stats['load_percent'] = round(($stats['load_avg'] / $stats['cpu_cores']) * 100, 1);
    }
} catch (Exception $e) {
    // Silencioso
}


// --- 2. Uso de RAM ---
try {
    $ram_output = shell_exec("free -m | grep 'Mem:' | awk '{print $3, $2}'"); // "Usado Total"
    
    // FIX 3: Verifica se a saída é válida antes de tentar 'explode'
    if (!empty($ram_output) && strpos(trim($ram_output), ' ') !== false) {
        list($ram_used, $ram_total) = explode(' ', trim($ram_output));
        $stats['ram_used_mb'] = (int)$ram_used;
        $stats['ram_total_mb'] = (int)$ram_total;
        if ($ram_total > 0) {
            $stats['ram_percent'] = round(($ram_used / $ram_total) * 100, 1);
        }
    }
} catch (Exception $e) {
    // Silencioso
}

// --- 3. Uso de Disco (para /) ---
try {
    $disk_output = shell_exec("df -P / | tail -n 1 | awk '{print $3, $2, $5}'"); // "Usado Total Porcentagem"
    
    // FIX 4: Verifica se a saída tem 3 partes antes de 'explode'
    if (!empty($disk_output) && count(explode(' ', trim($disk_output))) === 3) {
        list($used_kb, $total_kb, $percent) = explode(' ', trim($disk_output));
        
        $stats['disk_root_gb'] = round(((int)$used_kb / 1024 / 1024), 2);
        $stats['disk_root_total_gb'] = round(((int)$total_kb / 1024 / 1024), 2);
        $stats['disk_root_percent'] = (int)rtrim($percent, '%');
    }
} catch (Exception $e) {
    // Silencioso
}

// --- Envia a resposta ---
// Converte o array PHP em uma string JSON e a imprime
echo json_encode($stats);
exit;
?>