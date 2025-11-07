<?php
// Inicia a sessão para verificar se o usuário JÁ está logado
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Se já logado, vai para o painel
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyPanel - Login</title>
    <link rel="stylesheet" href="css/style.css?t=<?php echo time(); ?>">
    <style>
        /* Estilos específicos para a página de login */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: var(--bg-color);
        }
        .login-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px var(--shadow-color);
            width: 100%;
            max-width: 400px;
            color: var(--text-color);
        }
        .login-card h1 {
            text-align: center;
            color: var(--accent-color);
            margin-top: 0;
        }
        /* Usando as classes de formulário que já existem no style.css */
        #login-form .form-group {
            margin-bottom: 20px;
        }
        #login-form .btn-primary {
            width: 100%;
            padding: 12px;
            font-size: 1.1em;
        }
        #login-error {
            color: #f44336; /* Vermelho */
            text-align: center;
            margin-top: 15px;
            display: none; /* Começa escondido */
        }
    </style>
</head>
<body class="dark-mode"> <div class="login-card">
        <h1>MyPanel</h1>
        <form id="login-form">
            <div class="form-group">
                <label for="login">Utilizador</label>
                <input type="text" id="login" name="login" required>
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn-primary" id="login-btn">Entrar</button>
            <p id="login-error"></p>
        </form>
    </div>

    <script>
        const form = document.getElementById('login-form');
        const errorMsg = document.getElementById('login-error');
        const loginBtn = document.getElementById('login-btn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMsg.style.display = 'none';
            loginBtn.disabled = true;
            loginBtn.textContent = 'A verificar...';

            const formData = new FormData(form);
            
            try {
                const response = await fetch('./api/login_check.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();

                if (result.success) {
                    window.location.href = 'index.php'; // Sucesso! Redireciona
                } else {
                    errorMsg.textContent = result.message;
                    errorMsg.style.display = 'block';
                }

            } catch (err) {
                errorMsg.textContent = 'Erro de conexão. Tente novamente.';
                errorMsg.style.display = 'block';
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Entrar';
            }
        });
    </script>
</body>
</html>