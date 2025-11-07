<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

// Inclui sua conexão PDO
require_once '../config/db.php'; 

ini_set('display_errors', 0);
error_reporting(0);

// ATENÇÃO: Permissão de Sudo ainda é necessária
define('UFW_CMD', 'sudo /usr/sbin/ufw');

header('Content-Type: application/json');

try {
    // Garante que a conexão PDO foi estabelecida
    if (!isset($pdo)) {
        throw new Exception('Falha ao carregar a conexão com o banco de dados.');
    }

    $request_method = $_SERVER['REQUEST_METHOD'];
    
    if ($request_method === 'GET' && isset($_GET['action'])) {
        
        if ($_GET['action'] === 'get_db_rules') {
            // 1. Obter Status do Servidor
            $output_status_raw = shell_exec(UFW_CMD . ' status');
            $status = 'unknown';
            if (strpos($output_status_raw, 'Status: active') !== false) {
                $status = 'active';
            } elseif (strpos($output_status_raw, 'Status: inactive') !== false) {
                $status = 'inactive';
            }

            // 2. Obter Regras do Banco de Dados
            $stmt = $pdo->query("SELECT * FROM ufw ORDER BY id ASC");
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'status' => $status, 'rules' => $rules]);
        }

    } elseif ($request_method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['action'])) {
            throw new Exception('Dados JSON inválidos ou ação não definida.');
        }

        $action = $data['action'];
        $message = '';
        $output = [];
        $return_var = 0;

        switch ($action) {
            
            // Ação que controla o servidor (não mexe no DB)
            case 'toggle':
                $toggle = $data['toggle'] ?? null; // 'enable' or 'disable'
                if ($toggle === 'enable') {
                    $cmd = UFW_CMD . ' --force enable';
                    $message = 'Firewall ativado com sucesso.';
                } elseif ($toggle === 'disable') {
                    $cmd = UFW_CMD . ' disable';
                    $message = 'Firewall desativado com sucesso.';
                } else {
                    throw new Exception('Ação de toggle inválida.');
                }
                exec($cmd . ' 2>&1', $output, $return_var);
                break;

            // Adiciona regra no BANCO DE DADOS
            case 'add_db_rule':
                $stmt = $pdo->prepare("INSERT INTO ufw (action, port, protocol, source, comment) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['rule_action'] ?? 'allow',
                    $data['port'] ?? 'any',
                    $data['protocol'] ?? 'any',
                    $data['source'] ?? 'any',
                    $data['comment'] ?? null
                ]);
                $message = 'Regra salva no banco de dados. Sincronize para aplicar.';
                break;

            // Deleta regra do BANCO DE DADOS
            case 'delete_db_rule':
                $rule_id = intval($data['id']);
                if ($rule_id <= 0) {
                    throw new Exception('ID da regra inválido.');
                }
                $stmt = $pdo->prepare("DELETE FROM ufw WHERE id = ?");
                $stmt->execute([$rule_id]);
                $message = 'Regra ' . $rule_id . ' excluída do banco de dados. Sincronize para aplicar.';
                break;

            // Sincroniza DB -> SERVIDOR
            case 'sync_to_server':
                // 1. Busca todas as regras do DB
                $stmt = $pdo->query("SELECT * FROM ufw ORDER BY id ASC");
                $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // 2. Reseta o firewall no servidor
                exec(UFW_CMD . ' --force reset 2>&1', $output, $return_var);
                if ($return_var !== 0) throw new Exception('Falha ao resetar o UFW: ' . implode("\n", $output));
                
                $output[] = "--- UFW Resetado ---";

                // 3. Aplica cada regra do DB no servidor
                foreach ($rules as $rule) {
                    $rule_action = escapeshellarg($rule['action']);
                    $port = escapeshellarg($rule['port']);
                    $protocol = escapeshellarg($rule['protocol']);
                    $source = $rule['source'] ?? 'any';
                    $comment = $rule['comment'] ?? null;

                    if ($source === '' || $source === 'any') {
                        $source_part = 'from any';
                    } else {
                        $source_part = 'from ' . escapeshellarg($source);
                    }
                    
                    $proto_part = ($protocol === 'any') ? '' : 'proto ' . $protocol;
                    
                    $cmd = UFW_CMD . " $rule_action $source_part to any port $port $proto_part";
                    
                    if ($comment) {
                        $cmd .= ' comment ' . escapeshellarg($comment);
                    }
                    
                    exec($cmd . ' 2>&1', $output, $return_var);
                    if ($return_var !== 0) {
                        $output[] = "AVISO: Falha ao aplicar regra ID {$rule['id']}: $cmd";
                    } else {
                        $output[] = "Regra ID {$rule['id']} aplicada.";
                    }
                }
                
                // 4. Reativa o firewall
                exec(UFW_CMD . ' --force enable 2>&1', $output, $return_var);
                if ($return_var !== 0) throw new Exception('Falha ao reativar o UFW: ' . implode("\n", $output));
                
                $output[] = "--- UFW Reativado ---";
                $message = 'Sincronização concluída! Regras do banco de dados aplicadas ao servidor.';
                break;

            default:
                throw new Exception('Ação POST desconhecida.');
        }

        if ($return_var !== 0 && $action == 'toggle') {
            throw new Exception("Erro ao executar UFW: " . implode("\n", $output));
        }
        
        echo json_encode(['success' => true, 'message' => $message, 'raw_output' => $output]);

    } else {
        throw new Exception('Método ou ação inválida.');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>