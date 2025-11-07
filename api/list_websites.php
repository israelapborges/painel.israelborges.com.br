<?php
require '../config/session_guard.php';
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

$nginx_enabled_path = '/etc/nginx/sites-enabled/';
$nginx_available_path = '/etc/nginx/sites-available/';

$results = [
    'sites' => [],
    'error' => null
];
$found_sites = []; 

// 1. Tenta ler Nginx Enabled
if (is_readable($nginx_enabled_path)) {
    $files = @scandir($nginx_enabled_path);
    if ($files) {
        foreach ($files as $file) {
            if (strpos($file, '.conf') === false) continue;
            if ($file === 'default.conf') continue; 
            
            $found_sites[$file] = [
                'domain' => $file,
                'webserver' => 'Nginx',
                'status' => 'Ativo',
                'path' => $nginx_enabled_path . $file
            ];
        }
    }
} else {
    $results['error'] = 'Não foi possível ler ' . $nginx_enabled_path . '. Verifique as permissões.';
    echo json_encode($results);
    exit;
}

// 2. Tenta ler Nginx Available (para sites inativos)
if (is_readable($nginx_available_path)) {
    $files = @scandir($nginx_available_path);
    if ($files) {
        foreach ($files as $file) {
            if (strpos($file, '.conf') === false) continue;
            if ($file === 'default.conf') continue; 

            if (!isset($found_sites[$file])) {
                $found_sites[$file] = [
                    'domain' => $file,
                    'webserver' => 'Nginx',
                    'status' => 'Inativo',
                    'path' => $nginx_available_path . $file
                ];
            }
        }
    }
}

$results['sites'] = array_values($found_sites);

echo json_encode($results);
exit;
?>