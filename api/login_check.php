<?php
// Define que a saída é JSON
header('Content-Type: application/json');

// 1. Inicia a sessão
session_start();

// 2. Inclui o banco de dados
require '../config/db.php'; 

// 3. Pega os dados do formulário (usamos POST, não JSON)
$login = $_POST['login'] ?? null;
$senha = $_POST['senha'] ?? null;

if (empty($login) || empty($senha)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
    exit;
}

// 4. Busca o utilizador no banco
try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. Verifica se o utilizador existe E se a senha está correta
    if ($user && password_verify($senha, $user['senha_hash'])) {
        // Sucesso!
        
        // 6. Armazena os dados na sessão
        session_regenerate_id(true); // Segurança extra
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $user['login'];
        
        echo json_encode(['success' => true]);

    } else {
        // Falha!
        echo json_encode(['success' => false, 'message' => 'Utilizador ou senha inválidos.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados.']);
}
?>