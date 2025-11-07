<?php
// api/run_command.php  (substitua seu endpoint atual por este arquivo)
// Requer autenticação / sessão do seu painel
require '../config/session_guard.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

// lê input JSON
$input = json_decode(file_get_contents('php://input'), true);
$command = isset($input['command']) ? trim($input['command']) : '';
$clientCwd = isset($input['cwd']) ? trim($input['cwd']) : null;

// inicializa cwd na sessão se necessário
if (!isset($_SESSION['cwd'])) $_SESSION['cwd'] = '/';

// helper: normaliza caminhos tipo shell (resolve . .. sem realpath)
function normalize_path_string($cwd, $path) {
    if ($path === '' || $path === null) return $cwd;
    if (strpos($path, '/') === 0) {
        $parts = explode('/', $path);
    } else {
        $base = rtrim($cwd, '/');
        $parts = array_merge($base === '' ? [] : explode('/', ltrim($base, '/')), explode('/', $path));
    }
    $stack = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') {
            if (count($stack) > 0) array_pop($stack);
            continue;
        }
        $stack[] = $part;
    }
    $resolved = '/' . implode('/', $stack);
    if ($resolved === '') $resolved = '/';
    return $resolved;
}

// init resultado
$results = [
    'success' => false,
    'output' => '',
    'cwd' => $_SESSION['cwd'],
    'exit_code' => null,
    'error' => null,
    'raw_exec_line' => null,
];

try {
    if ($command === '') {
        throw new Exception('Nenhum comando fornecido.');
    }

    // Se cliente enviou cwd, sincronize (opcional)
    if ($clientCwd !== null && $clientCwd !== '') {
        // você pode optar por validar isso; aqui aceita para consistência com frontend
        $_SESSION['cwd'] = $clientCwd;
    }

    $trimmed = ltrim($command);

    // Trata 'cd' localmente (builtin)
    if (preg_match('/^cd(\s+|$)/', $trimmed)) {
        $arg = trim(substr($trimmed, 2));
        if ($arg === '' || $arg === '~') {
            $target = getenv('HOME') ?: '/';
        } elseif ($arg === '.') {
            $target = $_SESSION['cwd'];
        } elseif ($arg === '..') {
            $tmp = rtrim($_SESSION['cwd'], '/');
            $target = ($tmp === '') ? '/' : dirname($tmp);
            if ($target === '') $target = '/';
        } else {
            $target = normalize_path_string($_SESSION['cwd'], $arg);
        }

        // Atualiza sessão sem checar permissões (permitindo /root mesmo quando PHP não lê)
        $_SESSION['cwd'] = $target;
        $results['cwd'] = $_SESSION['cwd'];
        $results['output'] = '';
        $results['exit_code'] = 0;
        $results['success'] = true;
        echo json_encode($results);
        exit;
    }

    // Resolve cwd para execução (se realpath possível, use-o; se não, use string)
    $resolvedCwd = realpath($_SESSION['cwd']);
    if ($resolvedCwd === false) {
        $resolvedCwd = $_SESSION['cwd'];
    }

// --- montagem robusta do comando para suportar programas "interativos" ---
$sudoPath = '/usr/bin/sudo'; // ajuste se necessário

// remove um 'sudo' inicial enviado pelo cliente pra evitar "sudo sudo"
$commandNoLeadingSudo = preg_replace('/^\s*sudo\s+/', '', $command, 1);

// identifica o binário principal (primeiro token)
$firstToken = strtok($commandNoLeadingSudo, " \t") ?: '';

// lista de comandos que normalmente precisam de tty / são interativos
$interactiveBins = ['top','htop','less','more','vim','nano','man','watch','screen','tmux'];

// se for top/htop, tente versão "batch" preferível
if ($firstToken === 'top') {
    // usa top em modo batch (uma iteração)
    $commandNoLeadingSudo = preg_replace('/^top\b/', 'top -b -n 1', $commandNoLeadingSudo);
} elseif ($firstToken === 'htop') {
    // muitos htop suportam -b (batch), tente isto primeiro
    $commandNoLeadingSudo = preg_replace('/^htop\b/', 'htop -b -n 1', $commandNoLeadingSudo);
} elseif (in_array($firstToken, $interactiveBins, true)) {
    // fallback genérico: força um pseudo-tty com 'script -q -c'
    // script cria um PTY e executa o comando, enviando a saída para /dev/null (no host)
    // o output do comando ficará capturado pelo proc_open
    $commandNoLeadingSudo = 'script -q -c ' . escapeshellarg($commandNoLeadingSudo) . ' /dev/null';
}

// Monta o inner command que será executado como root
$inner = 'cd ' . escapeshellarg($resolvedCwd) . ' && ' . $commandNoLeadingSudo;

// Executa tudo como root (faz cd como root dentro do shell)
// utilizamos bash -lc para preservar pipes, redirecionamentos, etc.
$execCmd = $sudoPath . ' bash -lc ' . escapeshellarg($inner);

// grava para debug
$results['raw_exec_line'] = $execCmd;



    // Fechamos a sessão para evitar bloqueio durante exec (escreve cwd atualizado)
    session_write_close();

    // Executa comando com proc_open para capturar stdout e stderr separadamente
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    $process = proc_open($execCmd, $descriptorspec, $pipes, null, null);
    $stdout = '';
    $stderr = '';
    $exitCode = 127;

    if (is_resource($process)) {
        // fecha stdin
        fclose($pipes[0]);
        // lê stdout e stderr
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        // fecha processo e pega exit code
        $exitCode = proc_close($process);
    } else {
        throw new Exception('Falha ao iniciar o processo.');
    }

    // combine stderr e stdout para enviar ao client (preserva ordem)
    $combined = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));

    $results['output'] = (string)$combined;
    $results['exit_code'] = is_int($exitCode) ? $exitCode : intval($exitCode);
    $results['cwd'] = $resolvedCwd;
    $results['success'] = ($results['exit_code'] === 0);

    // Se houve falha ou saída vazia, registre para diagnóstico
    if ($results['exit_code'] !== 0 || $results['output'] === '') {
        $log = sprintf(
            "[%s] user=%s cwd=%s exit=%s exec=%s\nOUTPUT:\n%s\n---\n",
            date('Y-m-d H:i:s'),
            isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : (isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : 'unknown'),
            $results['cwd'],
            $results['exit_code'],
            $results['raw_exec_line'],
            $results['output'] === '' ? '[no output]' : $results['output']
        );
        @file_put_contents('/tmp/exec_debug.log', $log, FILE_APPEND | LOCK_EX);
    }

} catch (Exception $e) {
    $results['success'] = false;
    $results['error'] = $e->getMessage();
    if (!isset($results['output']) || $results['output'] === null) $results['output'] = '';
    // registra exceção
    @file_put_contents('/tmp/exec_debug.log', "[".date('c')."] EXCEPTION: ".$e->getMessage()."\n", FILE_APPEND | LOCK_EX);
}

// retorna JSON
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
exit;
?>
