#!/usr/bin/php
<?php
date_default_timezone_set('America/Sao_Paulo');
// Este script deve ser executado pelo ROOT via CRON

chdir(dirname(__FILE__));
require 'config/db.php'; // $pdo (conexão do painel)

// --- CONFIGURAÇÃO DE ACESSO AO BANCO DE DADOS (ROOT) ---
define('DB_HOST', '127.0.0.1'); 
define('DB_ROOT_USER', 'root');
define('DB_ROOT_PASS', 'CDQeJrBFJgIpgr81'); 

// Caminhos do Nginx
$sites_available = '/etc/nginx/sites-available/';
$sites_enabled = '/etc/nginx/sites-enabled/';
// NOVO: Caminho do Backup
$backup_dir = __DIR__ . '/inc/backups/';
$site_backup_dir = __DIR__ . '/inc/backups_sites/';
$db_temp_backup_dir = __DIR__ . '/inc/bd/';
$upload_dir = __DIR__ . '/inc/uploads/';

// --- ATUALIZAÇÃO DE SINCRONIZAÇÃO (Executa a cada minuto, NO INÍCIO) ---
try {
    // Sincroniza os sites .conf com a tabela 'backup_sites'
    sync_nginx_configs_to_db($pdo, $sites_enabled);
} catch (Exception $e) {
    // ADICIONE ESTAS DUAS LINHAS QUE FALTAM:
    log_task(null, "Erro ao atualizar cache de sites: " . $e->getMessage(), $pdo);
}

// --- Funções de Segurança ---
function is_path_safe($path_to_check, $allowed_dirs) {
    $real_path = realpath($path_to_check);
    
    if ($real_path === false) {
        $real_path = realpath(dirname($path_to_check));
    }
    
    if ($real_path === false) return false;

    foreach ($allowed_dirs as $allowed) {
        $real_allowed = realpath($allowed);
        if ($real_allowed === false) continue;
        
        if (strpos($real_path, $real_allowed) === 0) {
            return true;
        }
    }
    return false;
}

// --- Funções do Worker (Tarefas Nginx) ---
// (handle_create_vhost, handle_delete_vhost, handle_edit_vhost ... sem alterações)
function handle_create_vhost($task, $payload, $pdo, $sites_available, $sites_enabled) {
    $domain = $payload['domain'];
    $root_path = $payload['root_path'];
    $vhost_file = $domain . '.conf';
    if (empty($root_path)) {
        $username = str_replace('.', '_', $domain);
        $root_path = "/home/$username/htdocs";
    }
    $vhost_content = get_vhost_template($domain, $root_path);
    if (!is_dir($root_path)) {
        mkdir($root_path, 0755, true);
    }
    $log_dir = dirname($root_path) . '/logs/nginx';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    file_put_contents($sites_available . $vhost_file, $vhost_content);
    $link = $sites_enabled . $vhost_file;
    if (file_exists($link)) unlink($link);
    symlink($sites_available . $vhost_file, $link);
    $output = shell_exec('/usr/sbin/nginx -t 2>&1');
    if (strpos($output, 'test is successful') === false) {
        throw new Exception("Configuração do Nginx falhou: " . $output);
    }
    shell_exec('/usr/bin/systemctl reload nginx');
    log_task($task['id'], "Site $domain criado com sucesso.", $pdo, true);
}
function handle_delete_vhost($task, $payload, $pdo, $sites_available, $sites_enabled) {
    $domain = $payload['domain'];
    $path = $payload['path'];
    $vhost_file_name = basename($path);
    $allowed_dirs = [$sites_available, $sites_enabled];
    if (!is_path_safe($path, $allowed_dirs) || strpos($vhost_file_name, '..') !== false) {
        throw new Exception("Tentativa de exclusão em diretório não autorizado. Acesso negado.");
    }
    $conf_available = $sites_available . $vhost_file_name;
    $conf_enabled = $sites_enabled . $vhost_file_name;
    if (file_exists($conf_enabled)) unlink($conf_enabled);
    if (file_exists($conf_available)) unlink($conf_available);
    $username = str_replace('.', '_', $domain);
    $log_dir = "/home/$username/logs/nginx/";
    if (is_dir($log_dir)) {
        $log_access = $log_dir . 'access.log';
        $log_error = $log_dir . 'error.log';
        if (file_exists($log_access)) unlink($log_access);
        if (file_exists($log_error)) unlink($log_error);
        @rmdir($log_dir);
    }
    $output = shell_exec('/usr/sbin/nginx -t 2>&1');
    if (strpos($output, 'test is successful') === false) {
        if (file_exists($conf_available)) {
            symlink($conf_available, $conf_enabled);
        }
        throw new Exception("Configuração do Nginx falhou após exclusão: " . $output);
    }
    shell_exec('/usr/bin/systemctl reload nginx');
    log_task($task['id'], "Site $domain excluído com sucesso.", $pdo, true);
}
function handle_edit_vhost($task, $payload, $pdo, $sites_available) {
    $config_file_path = $payload['config_file_path'];
    $allowed_dirs = [$sites_available];
    if (!is_path_safe($config_file_path, $allowed_dirs)) {
        throw new Exception("Acesso de escrita negado ao arquivo: " . $config_file_path);
    }
    if (!file_exists($config_file_path) || !is_readable($config_file_path) || !is_writable($config_file_path)) {
        throw new Exception("Arquivo de configuração não encontrado ou sem permissões: " . $config_file_path);
    }
    $original_content = file_get_contents($config_file_path);
    $content = $original_content;
    try {
        $content = preg_replace('/(^\s*server_name\s+)(.*)(;)/m', '$1' . $payload['server_name'] . '$3', $content);
        $content = preg_replace('/(^\s*root\s+)(.*)(;)/m', '$1' . $payload['root'] . '$3', $content);
        $content = preg_replace('/(^\s*index\s+)(.*)(;)/m', '$1' . $payload['index'] . '$3', $content);
        $socket = $payload['php_socket'];
        if ($socket === 'disabled') {
            $content = preg_replace('/(^\s*)(?!#)(\s*fastcgi_pass\s+)(.*)(;)/m', '$1#$2$3$4', $content);
        } else {
            $content = preg_replace('/(^\s*fastcgi_pass\s+)(.*)(;)/m', '$1' . $socket . '$3', $content, 1, $count);
            if ($count === 0) {
                 $content = preg_replace('/(^\s*#\s*fastcgi_pass\s+)(.*)(;)/m', '    fastcgi_pass ' . $socket . ';', $content, 1);
            }
        }
        file_put_contents($config_file_path, $content);
        $output = shell_exec('/usr/sbin/nginx -t 2>&1');
        if (strpos($output, 'test is successful') === false) {
            file_put_contents($config_file_path, $original_content);
            throw new Exception("Configuração do Nginx falhou, alterações desfeitas: " . $output);
        }
        shell_exec('/usr/bin/systemctl reload nginx');
        log_task($task['id'], "Configuração do Nginx salva com sucesso.", $pdo, true);
    } catch (Exception $e) {
        if (file_exists($config_file_path)) {
            file_put_contents($config_file_path, $original_content);
        }
        throw $e;
    }
}

// --- Funções do Worker (Tarefas Database) ---
function get_db_root_pdo() {
    try {
        $dsn = 'mysql:host=' . DB_HOST;
        $db_root_pdo = new PDO($dsn, DB_ROOT_USER, DB_ROOT_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $db_root_pdo;
    } catch (PDOException $e) {
        throw new Exception("Falha ao conectar no MySQL como root: " . $e->getMessage());
    }
}
function handle_create_database($task, $payload, $pdo_panel) {
    $db_name = $payload['db_name'];
    $db_user = $payload['db_user'];
    $db_pass = $payload['db_pass'];
    $db_root_pdo = get_db_root_pdo();
    $db_root_pdo->exec("CREATE DATABASE `$db_name`");
    $db_root_pdo->exec("CREATE USER '$db_user'@'localhost' IDENTIFIED BY '$db_pass'");
    $db_root_pdo->exec("GRANT ALL PRIVILEGES ON `$db_name`.* TO '$db_user'@'localhost'");
    $db_root_pdo->exec("FLUSH PRIVILEGES");
    $stmt = $pdo_panel->prepare("INSERT INTO managed_databases (db_name, db_user) VALUES (?, ?)");
    $stmt->execute([$db_name, $db_user]);
    log_task($task['id'], "Banco de dados '$db_name' e usuário '$db_user' criados.", $pdo_panel, true);
}
function handle_delete_database($task, $payload, $pdo_panel) {
    $db_id = $payload['db_id'];
    $db_name = $payload['db_name'];
    $db_user = $payload['db_user'];
    $db_root_pdo = get_db_root_pdo();
    $db_root_pdo->exec("DROP DATABASE IF EXISTS `$db_name`");
    $db_root_pdo->exec("DROP USER IF EXISTS '$db_user'@'localhost'");
    $db_root_pdo->exec("FLUSH PRIVILEGES");
    $stmt = $pdo_panel->prepare("DELETE FROM managed_databases WHERE id = ?");
    $stmt->execute([$db_id]);
    log_task($task['id'], "Banco de dados '$db_name' e usuário '$db_user' excluídos.", $pdo_panel, true);
}

// NOVO: Função para Backup de Database
function handle_backup_database($task, $payload, $pdo_panel, $backup_dir_path) {
    $db_id = $payload['db_id'];
    $db_name = $payload['db_name'];
    
    // 1. Cria o diretório e o .htaccess (se não existirem)
    if (!is_dir($backup_dir_path)) {
        mkdir($backup_dir_path, 0755, true);
    }
    $htaccess_file = $backup_dir_path . '.htaccess';
    if (!file_exists($htaccess_file)) {
        file_put_contents($htaccess_file, 'Deny from all');
    }
    
    // 2. Define o nome do arquivo
    $timestamp = date('Y-m-d-H_i');
    $file_name = 'backup_' . $db_name . '_' . $timestamp . '.sql.gz';
    $backup_full_path = $backup_dir_path . $file_name;

    // 3. Monta o comando de backup
    // Usamos as constantes DB_ROOT_USER/PASS para o mysqldump
    $command = sprintf(
        "mysqldump --user=%s --password=%s --host=%s %s | gzip > %s",
        escapeshellarg(DB_ROOT_USER),
        escapeshellarg(DB_ROOT_PASS),
        escapeshellarg(DB_HOST),
        escapeshellarg($db_name),
        escapeshellarg($backup_full_path)
    );

// 4. Executa o comando
    exec($command, $output, $return_var);

    if ($return_var !== 0) {
        // Se falhar, apaga o arquivo incompleto (se existir)
        if (file_exists($backup_full_path)) {
            unlink($backup_full_path);
        }
        throw new Exception("mysqldump falhou. Verifique as credenciais root do MySQL no worker.php.");
    }

    // 5. NOVO: Pega o tamanho e a data
    $file_size_bytes = filesize($backup_full_path);
    $current_time = gmdate('Y-m-d H:i:s'); // Pega a data/hora atual (UTC)

    // 6. Atualiza o nome, DATA E TAMANHO na tabela
    $stmt = $pdo_panel->prepare("UPDATE managed_databases SET last_backup_file = ?, last_backup_date = ?, last_backup_size = ? WHERE id = ?");
    $stmt->execute([$file_name, $current_time, $file_size_bytes, $db_id]);
    
    // 7. Loga o sucesso
    log_task($task['id'], "Backup do banco '$db_name' criado com sucesso.", $pdo_panel, true);
}

// NOVO: Função para Restaurar Database
function handle_restore_database($task, $payload, $pdo_panel, $backup_dir_path) {
    $db_name = $payload['db_name'];
    $backup_file = $payload['backup_file'];
    
    // 1. Define o caminho completo e faz a verificação de segurança
    $full_path = realpath($backup_dir_path . $backup_file);

    if ($full_path === false || strpos($full_path, realpath($backup_dir_path)) !== 0 || !file_exists($full_path)) {
        throw new Exception("Arquivo de backup '$backup_file' não encontrado ou acesso negado.");
    }

    // 2. Monta o comando de restauração
    // Ele descompacta o .gz (gunzip) e envia o .sql direto para o cliente mysql
    $command = sprintf(
        "gunzip < %s | mysql --user=%s --password=%s --host=%s %s",
        escapeshellarg($full_path),
        escapeshellarg(DB_ROOT_USER),
        escapeshellarg(DB_ROOT_PASS),
        escapeshellarg(DB_HOST),
        escapeshellarg($db_name)
    );

    // 3. Executa o comando
    exec($command, $output, $return_var);

    if ($return_var !== 0) {
        throw new Exception("Restauração falhou. O banco de dados pode estar corrompido ou as credenciais root do MySQL estão incorretas.");
    }
    
    // 4. Loga o sucesso
    log_task($task['id'], "Banco de dados '$db_name' restaurado com sucesso a partir de '$backup_file'.", $pdo_panel, true);
}

// NOVO: Função para Restaurar de um Upload
function handle_restore_database_upload($task, $payload, $pdo_panel, $upload_dir_path) {
    $db_name = $payload['db_name'];
    $uploaded_file_path = $payload['uploaded_file_path'];
    
    // 1. Validação de Segurança (SUPER CRÍTICA)
    // Garante que o worker só leia arquivos de dentro do diretório de upload
    $safe_upload_dir = realpath($upload_dir_path);
    $safe_file_path = realpath($uploaded_file_path);

    if ($safe_file_path === false || strpos($safe_file_path, $safe_upload_dir) !== 0) {
        // Se o arquivo já foi apagado (talvez por uma tarefa duplicada), não lance erro
        if (!file_exists($safe_file_path)) {
            log_task($task['id'], "Arquivo de upload '$uploaded_file_path' já processado/removido. Pulando.", $pdo_panel, true);
            return;
        }
        throw new Exception("Acesso negado. O arquivo de upload está fora do diretório permitido.");
    }
    if (!file_exists($safe_file_path)) {
        throw new Exception("Arquivo de upload não encontrado: $safe_file_path");
    }

    // 2. Determina o comando (se é .gz ou .sql puro)
    $ext = pathinfo($safe_file_path, PATHINFO_EXTENSION);
    $command = '';
    
    if ($ext === 'gz') {
        $command = sprintf(
            "gunzip < %s | mysql --user=%s --password=%s --host=%s %s",
            escapeshellarg($safe_file_path),
            escapeshellarg(DB_ROOT_USER),
            escapeshellarg(DB_ROOT_PASS),
            escapeshellarg(DB_HOST),
            escapeshellarg($db_name)
        );
    } elseif ($ext === 'sql') {
        $command = sprintf(
            "mysql --user=%s --password=%s --host=%s %s < %s",
            escapeshellarg(DB_ROOT_USER),
            escapeshellarg(DB_ROOT_PASS),
            escapeshellarg(DB_HOST),
            escapeshellarg($db_name),
            escapeshellarg($safe_file_path)
        );
    } else {
        // Apaga o arquivo inválido antes de falhar
        unlink($safe_file_path);
        throw new Exception("Formato de arquivo inválido para restauração.");
    }

    // 3. Executa o comando
    exec($command, $output, $return_var);
    
    // 4. Limpeza: Apaga o arquivo de upload, quer tenha falhado ou não
    unlink($safe_file_path);

    if ($return_var !== 0) {
        throw new Exception("Restauração falhou. O banco de dados pode estar corrompido ou o arquivo SQL é inválido.");
    }


    // 5. NOVO: Limpa o 'last_backup' pois o BD foi sobrescrito
	$stmt = $pdo_panel->prepare("UPDATE managed_databases SET last_backup_file = NULL, last_backup_date = NULL, last_backup_size = NULL WHERE id = ?");
    $stmt->execute([$payload['db_id']]);

    // 6. Loga o sucesso
    log_task($task['id'], "Banco de dados '$db_name' restaurado com sucesso a partir do arquivo enviado.", $pdo_panel, true);
}


function sync_nginx_configs_to_db($pdo_panel, $sites_enabled_path) {
    if (!is_readable($sites_enabled_path)) {
        log_task(null, "Worker: Não é possível ler $sites_enabled_path", $pdo_panel);
        return;
    }

    $files_in_dir = scandir($sites_enabled_path);
    $conf_files_found = [];
    
    foreach ($files_in_dir as $file) {
        if (strpos($file, '.conf') === false) continue;
        
        $conf_path = $sites_enabled_path . $file;
        if (!is_readable($conf_path)) continue;
        
        $content = file_get_contents($conf_path);
        
        if (!preg_match('/^\s*root\s+(.+);/m', $content, $matches)) {
            continue; // Pula sites sem 'root'
        }
        
        $root_path = trim($matches[1]);
        $conf_name = $file;
        $conf_files_found[] = $conf_name;

        // Sincroniza com o banco de dados
        $stmt = $pdo_panel->prepare(
            "INSERT INTO backup_sites (conf_name, root_path) 
             VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE root_path = ?"
        );
        $stmt->execute([$conf_name, $root_path, $root_path]);
    }
    
    // Limpeza: Remove sites da tabela que não existem mais no Nginx
    if (count($conf_files_found) > 0) {
        $placeholders = implode(',', array_fill(0, count($conf_files_found), '?'));
        $stmt_delete = $pdo_panel->prepare("DELETE FROM backup_sites WHERE conf_name NOT IN ($placeholders)");
        $stmt_delete->execute($conf_files_found);
    } else {
        // Se nenhum .conf foi encontrado, limpa a tabela
        $pdo_panel->query("DELETE FROM backup_sites");
    }
}

function handle_backup_site($task, $payload, $pdo_panel, $site_backup_dir) {
    $conf_name = $payload['domain']; // Recebe 'painel.israelborges.com.br.conf'
    $root_path = $payload['root_path'];
    
    // CORREÇÃO DO NOME: Cria o nome limpo
    $clean_domain = str_replace('.conf', '', $conf_name); // vira 'painel.israelborges.com.br'
    
    if (!is_dir($site_backup_dir)) mkdir($site_backup_dir, 0755, true);
    $htaccess_file = $site_backup_dir . '.htaccess';
    if (!file_exists($htaccess_file)) file_put_contents($htaccess_file, 'Deny from all');

    // CORREÇÃO DO NOME: Usa o $clean_domain para o nome do arquivo
    $timestamp = date('Y-m-d-H_i');
    $file_name = $clean_domain . '_' . $timestamp . '.tar.gz';
    $backup_full_path = $site_backup_dir . $file_name;
    
    // Comando com --exclude
    $command = sprintf(
        "sudo tar --exclude='./bd' --exclude='./inc/bd' --exclude='./inc/cache' --exclude='./inc/uploads' --exclude='./inc/backups_sites' --exclude='./inc/backups' -czf %s -C %s .",
        escapeshellarg($backup_full_path),
        escapeshellarg($root_path)
    );
    
    exec($command . ' 2>&1', $output_array, $return_var);
    
    if ($return_var !== 0) {
        if (file_exists($backup_full_path)) unlink($backup_full_path);
        $error_output = implode("\n", $output_array);
        throw new Exception("Comando 'tar' falhou (Code: $return_var). Erro: " . $error_output);
    }
    
    // NOVO: Pega dados e atualiza a tabela 'backup_sites'
    $file_size_bytes = filesize($backup_full_path);
    $current_time = gmdate('Y-m-d H:i:s');
    $stmt = $pdo_panel->prepare(
        "UPDATE backup_sites SET last_backup_file = ?, last_backup_date = ?, last_backup_size = ? 
         WHERE conf_name = ?"
    );
    $stmt->execute([$file_name, $current_time, $file_size_bytes, $conf_name]);
    
    log_task($task['id'], "Backup do site '$clean_domain' criado com sucesso.", $pdo_panel, true);
}

function handle_restore_site($task, $payload, $pdo_panel, $site_backup_dir) {
    $root_path = $payload['root_path'];
    $backup_file = $payload['backup_file'];
    
    $full_path = realpath($site_backup_dir . $backup_file);
    if ($full_path === false || strpos($full_path, realpath($site_backup_dir)) !== 0) {
        throw new Exception("Arquivo de backup '$backup_file' não encontrado ou acesso negado.");
    }

    // --- DANGER ZONE ---
    // 1. Apaga tudo dentro do diretório root
    shell_exec("sudo rm -rf " . escapeshellarg($root_path) . "/*");
    // 2. Restaura o backup
    $command = sprintf(
        "sudo tar -xzf %s -C %s",
        escapeshellarg($full_path),
        escapeshellarg($root_path)
    );
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        throw new Exception("Restauração (tar) falhou. O arquivo pode estar corrompido.");
    }
    
    log_task($task['id'], "Site restaurado com sucesso a partir de '$backup_file'.", $pdo_panel, true);
}

function handle_delete_site_backup($task, $payload, $pdo_panel, $site_backup_dir) {
    $backup_file = $payload['backup_file'];
    $conf_name = $payload['conf_name']; // Recebe o .conf
    
    $full_path = realpath($site_backup_dir . $backup_file);
    if ($full_path === false || strpos($full_path, realpath($site_backup_dir)) !== 0) {
        throw new Exception("Arquivo de backup '$backup_file' não encontrado ou acesso negado.");
    }
    
    unlink($full_path);
    
    // NOVO: Limpa os dados na tabela 'backup_sites'
    $stmt = $pdo_panel->prepare(
        "UPDATE backup_sites SET last_backup_file = NULL, last_backup_date = NULL, last_backup_size = NULL 
         WHERE conf_name = ?"
    );
    $stmt->execute([$conf_name]);
    
    log_task($task['id'], "Arquivo de backup '$backup_file' excluído.", $pdo_panel, true);
}



function handle_restore_site_upload($task, $payload, $pdo_panel, $upload_dir_path) {
    $root_path = $payload['root_path'];
    $uploaded_file_path = $payload['uploaded_file_path'];
    $conf_name = $payload['domain']; // Recebe o .conf

    $safe_upload_dir = realpath($upload_dir_path);
    $safe_file_path = realpath($uploaded_file_path);
    if ($safe_file_path === false || strpos($safe_file_path, $safe_upload_dir) !== 0) {
        throw new Exception("Acesso negado. O arquivo de upload está fora do diretório permitido.");
    }
    
    shell_exec("rm -rf " . escapeshellarg($root_path) . "/*");
    $command = sprintf( "sudo tar -xzf %s -C %s", escapeshellarg($safe_file_path), escapeshellarg($root_path) );
    exec($command . ' 2>&1', $output_array, $return_var);
    unlink($safe_file_path);

    if ($return_var !== 0) {
        $error_output = implode("\n", $output_array);
        throw new Exception("Restauração (tar) falhou (Code: $return_var). Erro: " . $error_output);
    }
    
    // NOVO: Limpa os dados de backup antigo na tabela
    $stmt = $pdo_panel->prepare(
        "UPDATE backup_sites SET last_backup_file = NULL, last_backup_date = NULL, last_backup_size = NULL 
         WHERE conf_name = ?"
    );
    $stmt->execute([$conf_name]);
    
    log_task($task['id'], "Site restaurado com sucesso a partir de arquivo enviado.", $pdo_panel, true);
}

function handle_generate_master_archive($task, $payload, $pdo_panel, $site_backup_dir) {
    $output_filename = $payload['output_filename']; // ex: _MASTER_ARCHIVE.tar.bz2
    
    // --- CORREÇÃO: Salva o arquivo em um diretório TEMPORÁRIO ---
    // Salva o arquivo um nível acima (em /inc/) para evitar o erro "file changed as we read it"
    $temp_output_path = dirname($site_backup_dir) . '/' . $output_filename;
    $final_output_path = $site_backup_dir . $output_filename;

    // Agora só precisamos excluir o .htaccess de dentro do diretório de origem
    $tar_exclude_params = " --exclude=" . escapeshellarg('./.htaccess');

    // Comando: -c (criar), -j (bzip2), -f (arquivo), -C (mudar dir), . (tudo)
    $command = sprintf(
        "sudo tar %s -cjf %s -C %s .",
        $tar_exclude_params,
        escapeshellarg($temp_output_path), // Cria o arquivo em /inc/
        escapeshellarg($site_backup_dir)  // Lê o conteúdo de /inc/backups_sites/
    );
    
    exec($command . ' 2>&1', $output_array, $return_var);
    
    if ($return_var !== 0) {
        // Se falhou, apaga o arquivo temporário (se existir)
        if (file_exists($temp_output_path)) unlink($temp_output_path);
        throw new Exception("Falha ao criar o arquivo mestre: " . implode("\n", $output_array));
    }
    
    // SUCESSO: Move o arquivo temporário para o local final (sobrescreve o antigo, se houver)
    rename($temp_output_path, $final_output_path);
    // --- FIM DA CORREÇÃO ---
    
    log_task($task['id'], "Arquivo mestre '$output_filename' criado com sucesso.", $pdo_panel, true);
}

function handle_delete_master_archive($task, $payload, $pdo_panel, $site_backup_dir) {
    $filename = $payload['filename'];
    $full_path = realpath($site_backup_dir . $filename);
    
    // Validação de segurança (para garantir que só apaga dentro do dir de backups)
    if ($full_path === false || strpos($full_path, realpath($site_backup_dir)) !== 0) {
        throw new Exception("Arquivo mestre '$filename' não encontrado ou acesso negado.");
    }
    
    if (file_exists($full_path)) {
        unlink($full_path);
    }
    
    log_task($task['id'], "Arquivo mestre '$filename' excluído com sucesso.", $pdo_panel, true);
}

function handle_full_backup($task, $payload, $pdo_panel, $site_backup_dir) {
    $conf_name = $payload['domain']; 
    $root_path = $payload['root_path'];
    $db_id = $payload['db_id']; // ID do banco linkado

    // --- 1. Busca o nome do banco de dados ---
    $stmt_db = $pdo_panel->prepare("SELECT db_name FROM managed_databases WHERE id = ?");
    $stmt_db->execute([$db_id]);
    $db_info = $stmt_db->fetch();
    
    if (!$db_info) {
        throw new Exception("Banco de dados linkado (ID: $db_id) não encontrado.");
    }
    $db_name = $db_info['db_name'];
    
    // --- 2. Prepara os diretórios ---
    $db_temp_dir = __DIR__ . '/inc/bd/'; // Diretório temporário para o .sql.gz
    if (!is_dir($db_temp_dir)) mkdir($db_temp_dir, 0755, true);
    if (!is_dir($site_backup_dir)) mkdir($site_backup_dir, 0755, true);
    $htaccess_file = $site_backup_dir . '.htaccess';
    if (!file_exists($htaccess_file)) file_put_contents($htaccess_file, 'Deny from all');
    
    // Nome do arquivo de banco (sobrescrito)
    $db_sql_file_name = $db_name . '.sql.gz';
    $db_sql_full_path = $db_temp_dir . $db_sql_file_name;

    // --- 3. Executa o mysqldump ---
    $command_db = sprintf(
        "mysqldump --user=%s --password=%s --host=%s %s | gzip > %s",
        escapeshellarg(DB_ROOT_USER),
        escapeshellarg(DB_ROOT_PASS),
        escapeshellarg(DB_HOST),
        escapeshellarg($db_name),
        escapeshellarg($db_sql_full_path)
    );
    exec($command_db . ' 2>&1', $output_db, $return_var_db);
    if ($return_var_db !== 0) {
        throw new Exception("mysqldump falhou: " . implode("\n", $output_db));
    }

    // --- 4. Executa o backup de arquivos (tar) ---
    $clean_domain = str_replace('.conf', '', $conf_name);
    $timestamp = date('Y-m-d-H_i');
    $site_backup_file_name = $clean_domain . '_' . $timestamp . '_full.tar.gz';
    $site_backup_full_path = $site_backup_dir . $site_backup_file_name;
    
    // Caminho relativo do .sql.gz DENTRO do /inc/bd/ (para o tar)
    $db_file_relative_path_for_tar = $db_sql_file_name;

    // Comando 'tar'
    $command_tar = sprintf(
        "sudo tar --exclude='./bd' --exclude='./inc/bd' --exclude='./inc/cache' --exclude='./inc/uploads' --exclude='./inc/backups_sites' --exclude='./inc/backups' -czf %s -C %s . -C %s %s",
        escapeshellarg($site_backup_full_path),
        escapeshellarg($root_path), // -C (Muda para o diretório root do site) e adiciona '.' (tudo)
        escapeshellarg($db_temp_dir), // -C (Muda para o diretório do BD)
        escapeshellarg($db_file_relative_path_for_tar) // Adiciona o 'banco.sql.gz'
    );
    
    exec($command_tar . ' 2>&1', $output_tar, $return_var_tar);
    
    if ($return_var_tar !== 0) {
        if (file_exists($site_backup_full_path)) unlink($site_backup_full_path);
        throw new Exception("Comando 'tar' falhou: " . implode("\n", $output_tar));
    }
    
    // 5. Atualiza a tabela 'backup_sites'
    $file_size_bytes = filesize($site_backup_full_path);
    $current_time = gmdate('Y-m-d H:i:s');
    $stmt = $pdo_panel->prepare(
        "UPDATE backup_sites SET last_backup_file = ?, last_backup_date = ?, last_backup_size = ? 
         WHERE conf_name = ?"
    );
    $stmt->execute([$site_backup_file_name, $current_time, $file_size_bytes, $conf_name]);
    
    log_task($task['id'], "Full Backup (Site+BD) '$clean_domain' criado com sucesso.", $pdo_panel, true);
}

function handle_compress_file($task, $payload, $pdo_panel) {
    $source_path = $payload['source_path'];
    $archive_path = $payload['archive_path'];
    $source_dir = $payload['source_dir'];
    $source_item = $payload['source_item'];
    $format = $payload['format'] ?? 'zip'; // Padrão é zip

    // ... (Validação de Segurança - $safe_root, etc.) ...

    // Monta o comando (COM LÓGICA DE FORMATO)
    if ($format === 'tar.gz') {
        // tar -czf (c=create, z=gzip, f=file)
        $command = sprintf(
            "cd %s && tar -czf %s %s",
            escapeshellarg($source_dir),
            escapeshellarg($archive_path),
            escapeshellarg($source_item)
        );
        $tool = 'tar';
    } else {
        // Padrão é ZIP
        $command = sprintf(
            "cd %s && zip -r %s %s",
            escapeshellarg($source_dir),
            escapeshellarg($archive_path),
            escapeshellarg($source_item)
        );
        $tool = 'zip';
    }

    exec($command . ' 2>&1', $output_array, $return_var);

    if ($return_var !== 0) {
        $error_output = implode("\n", $output_array);
        if (file_exists($archive_path)) unlink($archive_path);
        throw new Exception("Comando '$tool' falhou (Code: $return_var). Erro: " . $error_output);
    }

    // --- CORREÇÃO DE DONO E PERMISSÃO ---
    try {
        // 1. Obtém o Dono (UID) e Grupo (GID) do diretório pai
        $uid = fileowner($source_dir);
        $gid = filegroup($source_dir);

        // 2. Altera o Dono e Grupo do novo arquivo .zip
        if ($uid !== false) {
            chown($archive_path, $uid);
        }
        if ($gid !== false) {
            chgrp($archive_path, $gid);
        }

        // 3. Define permissões seguras (rw-r--r--)
        chmod($archive_path, 0644); 
        
    } catch (Exception $e) {
        // Se falhar (o que é improvável como root), apenas loga e continua
        log_task(null, "Worker: Falha ao corrigir permissões em $archive_path. " . $e->getMessage(), $pdo_panel);
    }
    // --- FIM DA CORREÇÃO ---

    log_task($task['id'], "Compressão de '$source_item' para '$archive_path' concluída.", $pdo_panel, true);
}

// --- FUNÇÃO DE EXTRAÇÃO (NOVA) ---
function handle_extract_file($task, $payload, $pdo_panel) {
    $archive_path = $payload['archive_path'];
    $dest_dir = $payload['dest_dir'];

    $safe_root = realpath('/home/israelborges-painel/htdocs/painel.israelborges.com.br/');
    if (strpos($archive_path, $safe_root) !== 0 || strpos($dest_dir, $safe_root) !== 0) {
        throw new Exception("Segurança: Tentativa de extração fora do diretório permitido.");
    }
    if (!file_exists($archive_path)) {
        throw new Exception("Arquivo .zip/.tar.gz não encontrado.");
    }

    // Determina o comando pela extensão
    $ext = strtolower(pathinfo($archive_path, PATHINFO_EXTENSION));
    $command = '';
    $tool = '';

    if ($ext === 'zip') {
        // unzip -o (overwrite) -d (destination)
        $command = sprintf(
            "unzip -o %s -d %s",
            escapeshellarg($archive_path),
            escapeshellarg($dest_dir)
        );
        $tool = 'unzip';
    } elseif ($ext === 'gz' || $ext === 'tgz') {
        // tar -xzf (extract, gzip, file) -C (change dir)
        $command = sprintf(
            "tar -xzf %s -C %s",
            escapeshellarg($archive_path),
            escapeshellarg($dest_dir)
        );
        $tool = 'tar';
    } else {
        throw new Exception("Formato de arquivo não suportado para extração: .$ext");
    }

    exec($command . ' 2>&1', $output_array, $return_var);

    if ($return_var !== 0) {
        $error_output = implode("\n", $output_array);
        throw new Exception("Comando '$tool' falhou (Code: $return_var). Erro: " . $error_output);
    }

    // --- CORREÇÃO DE DONO (CRUCIAL) ---
    // Muda o dono de TODOS os ficheiros extraídos para o dono da pasta
    try {
        $uid = fileowner($dest_dir);
        $gid = filegroup($dest_dir);

        // ATENÇÃO: Isto pode ser lento em arquivos grandes.
        // Corre 'chown -R' no diretório de destino.
        exec(sprintf("chown -R %s:%s %s", escapeshellarg($uid), escapeshellarg($gid), escapeshellarg($dest_dir)));

    } catch (Exception $e) {
        log_task(null, "Worker: Falha ao corrigir permissões na extração em $dest_dir. " . $e->getMessage(), $pdo_panel);
    }

    log_task($task['id'], "Extração de '$archive_path' concluída.", $pdo_panel, true);
}

function handle_copy_file($task, $payload, $pdo_panel) {
    $source_path = $payload['source_path'];
    $dest_path = $payload['dest_path'];
    $dest_dir = $payload['dest_dir'];

    $safe_root = realpath('/home/israelborges-painel/htdocs/painel.israelborges.com.br/');

    // Validação de Segurança
    if (strpos($source_path, $safe_root) !== 0 || strpos($dest_path, $safe_root) !== 0) {
        throw new Exception("Segurança: Tentativa de cópia fora do diretório permitido.");
    }

    // Comando 'cp -r' (recursivo para pastas)
    $command = sprintf(
        "cp -r %s %s",
        escapeshellarg($source_path),
        escapeshellarg($dest_path)
    );

    exec($command . ' 2>&1', $output_array, $return_var);

    if ($return_var !== 0) {
        $error_output = implode("\n", $output_array);
        throw new Exception("Comando 'cp' falhou: " . $error_output);
    }

    // --- CORREÇÃO DE DONO E PERMISSÃO (Igual ao 'compress') ---
    try {
        $uid = fileowner($dest_dir); // Pega o dono da pasta de destino
        $gid = filegroup($dest_dir); // Pega o grupo da pasta de destino

        // Precisamos aplicar o chown recursivamente se for uma pasta
        if (is_dir($dest_path)) {
            exec(sprintf("chown -R %s:%s %s", escapeshellarg($uid), escapeshellarg($gid), escapeshellarg($dest_path)));
            exec(sprintf("chmod -R u=rwX,g=rX,o=rX %s", escapeshellarg($dest_path))); // 755 para pastas, 644 para ficheiros
        } else {
            chown($dest_path, $uid);
            chgrp($dest_path, $gid);
            chmod($dest_path, 0644);
        }
    } catch (Exception $e) {
        log_task(null, "Worker: Falha ao corrigir permissões em $dest_path. " . $e->getMessage(), $pdo_panel);
    }
    // --- FIM DA CORREÇÃO ---

    log_task($task['id'], "Cópia de '$source_path' para '$dest_path' concluída.", $pdo_panel, true);
}


function handle_move_file($task, $payload, $pdo_panel) {
    $source_path = $payload['source_path'];
    $dest_path = $payload['dest_path'];

    $safe_root = realpath('/home/israelborges-painel/htdocs/painel.israelborges.com.br/');

    // Validação de Segurança
    if (strpos($source_path, $safe_root) !== 0 || strpos($dest_path, $safe_root) !== 0) {
        throw new Exception("Segurança: Tentativa de mover fora do diretório permitido.");
    }
    if (!file_exists($source_path)) {
         throw new Exception("Arquivo de origem não existe (talvez já tenha sido movido).");
    }

    // Comando 'mv'
    $command = sprintf(
        "mv %s %s",
        escapeshellarg($source_path),
        escapeshellarg($dest_path)
    );

    exec($command . ' 2>&1', $output_array, $return_var);

    if ($return_var !== 0) {
        $error_output = implode("\n", $output_array);
        throw new Exception("Comando 'mv' falhou: " . $error_output);
    }

    // 'mv' preserva o dono, não precisamos de chown/chmod.

    log_task($task['id'], "Movido de '$source_path' para '$dest_path' concluído.", $pdo_panel, true);
}
// --- FIM DO NOVO BLOCO ---

// --- LOOP PRINCIPAL DO WORKER ---

try {
    $pdo->beginTransaction();
    $stmt = $pdo->query("SELECT * FROM pending_tasks WHERE status = 'pending' LIMIT 5 FOR UPDATE SKIP LOCKED");
    $tasks = $stmt->fetchAll();
    if (empty($tasks)) {
        $pdo->commit();
        exit;
    }
    $task_ids = array_map(fn($task) => $task['id'], $tasks);
    $pdo->query("UPDATE pending_tasks SET status = 'processing' WHERE id IN (" . implode(',', $task_ids) . ")");
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    log_task(null, "Erro ao buscar tarefas: " . $e->getMessage(), $pdo);
    exit;
}

foreach ($tasks as $task) {
    $payload = json_decode($task['payload'], true);
    
    try {
        // Roteador de Tarefas
        if ($task['task_type'] === 'create_vhost') {
            $domain = $payload['domain'] ?? 'N/A';
            handle_create_vhost($task, $payload, $pdo, $sites_available, $sites_enabled);
        
        } elseif ($task['task_type'] === 'delete_vhost') {
            $domain = $payload['domain'] ?? 'N/A';
            handle_delete_vhost($task, $payload, $pdo, $sites_available, $sites_enabled);
        
        } elseif ($task['task_type'] === 'edit_vhost') {
            $domain = $payload['server_name'] ?? 'N/A';
            handle_edit_vhost($task, $payload, $pdo, $sites_available);
        
        } elseif ($task['task_type'] === 'create_database') {
            handle_create_database($task, $payload, $pdo);
        
        } elseif ($task['task_type'] === 'delete_database') {
            handle_delete_database($task, $payload, $pdo);
            
        } elseif ($task['task_type'] === 'backup_database') {
            // NOVO: Rota para backup de DB
            handle_backup_database($task, $payload, $pdo, $backup_dir);
            
} elseif ($task['task_type'] === 'restore_database') {
            handle_restore_database($task, $payload, $pdo, $backup_dir);
            
} elseif ($task['task_type'] === 'restore_database_upload') {
            handle_restore_database_upload($task, $payload, $pdo, $upload_dir);

// --- ADICIONE ESTE BLOCO 'ELSEIF' ---

        // NOVAS TAREFAS DE BACKUP DE SITE:
        } elseif ($task['task_type'] === 'backup_site') {
            handle_backup_site($task, $payload, $pdo, $site_backup_dir);
            
        } elseif ($task['task_type'] === 'restore_site') {
            handle_restore_site($task, $payload, $pdo, $site_backup_dir);

        } elseif ($task['task_type'] === 'delete_site_backup') {
            handle_delete_site_backup($task, $payload, $pdo, $site_backup_dir);

        } elseif ($task['task_type'] === 'restore_site_upload') {
            handle_restore_site_upload($task, $payload, $pdo, $upload_dir);
            
        } elseif ($task['task_type'] === 'generate_master_archive') {
            handle_generate_master_archive($task, $payload, $pdo, $site_backup_dir);
        
        } elseif ($task['task_type'] === 'delete_master_archive') {
            handle_delete_master_archive($task, $payload, $pdo, $site_backup_dir);

        } elseif ($task['task_type'] === 'full_backup') {
            handle_full_backup($task, $payload, $pdo, $site_backup_dir);

        
        } elseif ($task['task_type'] === 'compress_file') {
            handle_compress_file($task, $payload, $pdo);
            
        } elseif ($task['task_type'] === 'copy_file') {
            handle_copy_file($task, $payload, $pdo);
        } elseif ($task['task_type'] === 'move_file') {
            handle_move_file($task, $payload, $pdo);
            
        } elseif ($task['task_type'] === 'extract_file') {
        handle_extract_file($task, $payload, $pdo);    
		
        } else {
            throw new Exception("Tipo de tarefa desconhecido: " . $task['task_type']);
        }

    } catch (Exception $e) {
        $domain_log = $payload['domain'] ?? $payload['db_name'] ?? 'N/A';
        log_task($task['id'], "Erro ao processar ($domain_log): " . $e->getMessage(), $pdo, false);
    }
}

// --- Fim do novo bloco ---

// --- Funções Auxiliares ---

function log_task($id, $message, $pdo, $success = null) {
    if ($id === null) {
        echo $message . "\n";
        return;
    }
    $status = $success ? 'complete' : 'failed';
    $stmt = $pdo->prepare("UPDATE pending_tasks SET status = ?, `log` = ? WHERE id = ?");
    $stmt->execute([$status, $message, $id]);
}

function get_vhost_template($domain, $root_path) {
    $log_path = dirname($root_path) . '/logs/nginx';
    return "
server {
    listen 80;
    server_name $domain;
    root $root_path;
    access_log $log_path/access.log;
    error_log $log_path/error.log;
    index index.php index.html;
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
";
}
?>
