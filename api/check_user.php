<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnóstico de Acesso - Israel Borges</title>
<style>
/* ================= FUTURE SYSTEM PANEL ================= */
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --bg-dark: #05060a;
    --panel-bg: rgba(15, 18, 30, 0.9);
    --accent: #00b4ff;
    --accent-glow: 0 0 15px rgba(0,180,255,0.8);
    --text: #d6d8e1;
    --success: #00ffaa;
    --fail: #ff4f4f;
    --warn: #ffd000;
    --font: 'JetBrains Mono', monospace;
}

body {
    margin: 0;
    padding: 40px;
    background: radial-gradient(circle at 20% 20%, #0a0a12, #000);
    color: var(--text);
    font-family: var(--font);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.container {
    width: 85%;
    max-width: 950px;
    background: var(--panel-bg);
    border: 1px solid rgba(0,180,255,0.3);
    border-radius: 20px;
    box-shadow: 0 0 25px rgba(0,180,255,0.2);
    padding: 35px;
    backdrop-filter: blur(10px);
    animation: fadeIn 1.5s ease;
}

h1 {
    color: var(--accent);
    text-shadow: var(--accent-glow);
    text-align: center;
    margin-bottom: 25px;
    font-weight: 600;
    letter-spacing: 1px;
}

pre {
    background: rgba(10, 15, 25, 0.8);
    border: 1px solid rgba(0,180,255,0.2);
    border-radius: 12px;
    padding: 20px;
    font-size: 0.95rem;
    line-height: 1.5;
    overflow-x: auto;
    box-shadow: inset 0 0 10px rgba(0,180,255,0.1);
}

.status {
    text-align: center;
    margin-top: 15px;
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
}

.success {
    color: var(--success);
    text-shadow: 0 0 10px var(--success);
}

.fail {
    color: var(--fail);
    text-shadow: 0 0 10px var(--fail);
}

.warn {
    color: var(--warn);
    text-shadow: 0 0 10px var(--warn);
}

.footer {
    margin-top: 35px;
    text-align: center;
    font-size: 0.8rem;
    color: #999;
    border-top: 1px solid rgba(0,180,255,0.2);
    padding-top: 10px;
}

/* Efeito de entrada suave */
@keyframes fadeIn {
    from {opacity: 0; transform: translateY(20px);}
    to {opacity: 1; transform: translateY(0);}
}
</style>
</head>
<body>
<div class="container">
<h1>🚀 Diagnóstico de Acesso do Sistema</h1>
<pre>
<?php
echo "Utilizador atual (PHP):\n";
exec('whoami', $user);
print_r($user);

echo "\n\nGrupos do utilizador:\n";
exec('groups', $groups);
print_r($groups);

echo "\n\nTeste de acesso ao diretório:\n";
$path = '/home/israelborges-painel/htdocs/painel.israelborges.com.br/';

$status = '';

if (is_dir($path)) {
    echo "SUCESSO: Consegui aceder a $path.\n";

    if (is_readable($path)) {
        echo "SUCESSO: O diretório é legível.\n";
        $read = true;
    } else {
        echo "FALHA: Não consigo ler o diretório.\n";
        $read = false;
    }

    $test_file = $path . '/teste_permissao.txt';
    $write = false;

    if (@file_put_contents($test_file, "Teste de escrita executado com sucesso em " . date('Y-m-d H:i:s'))) {
        echo "SUCESSO: O diretório é gravável.\n";
        $write = true;
        unlink($test_file);
    } else {
        echo "FALHA: Não consigo gravar no diretório.\n";
    }

    if ($read && $write) {
        $status = '<p class="status success">✅ Acesso total (leitura e gravação confirmados)</p>';
    } elseif ($read && !$write) {
        $status = '<p class="status warn">⚠️ Somente leitura — sem permissão de gravação</p>';
    } else {
        $status = '<p class="status fail">❌ Falha de acesso — verificar permissões</p>';
    }

} else {
    echo "FALHA: NÃO consigo aceder a $path. (is_dir() falhou)\n";
    $status = '<p class="status fail">❌ Diretório inexistente ou inacessível</p>';
}
?>
</pre>
<?php echo $status; ?>
<div class="footer">© <?php echo date('Y'); ?> Painel de Diagnóstico - Israel Borges</div>
</div>
</body>
</html>
