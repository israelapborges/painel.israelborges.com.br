<?php
// 1. PROTEGER A API
require '../config/session_guard.php'; // Garante que o utilizador está logado

// 2. Incluir DB e definir tipo de resposta
require '../config/db.php';
header('Content-Type: application/json');

// 3. Obter dados do POST (JSON)
$input = json_decode(file_get_contents('php://input'), true);
$senha_antiga = $input['senha_antiga'] ?? null;
$nova_senha = $input['nova_senha'] ?? null;
$confirmar_senha = $input['confirmar_senha'] ?? null;

// 4. Obter o ID do utilizador da SESSÃO (seguro)
$user_id = $_SESSION['user_id'];

// 5. Validações
if (empty($senha_antiga) || empty($nova_senha) || empty($confirmar_senha)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
    exit;
}

if (strlen($nova_senha) < 6) {
    echo json_encode(['success' => false, 'message' => 'A nova senha deve ter no mínimo 6 caracteres.']);
    exit;
}

if ($nova_senha !== $confirmar_senha) {
    echo json_encode(['success' => false, 'message' => 'A nova senha e a confirmação não correspondem.']);
    exit;
}

if ($nova_senha === $senha_antiga) {
    echo json_encode(['success' => false, 'message' => 'A nova senha deve ser diferente da antiga.']);
    exit;
}

try {
    // 6. Verificar a Senha Antiga
    $stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($senha_antiga, $user['senha_hash'])) {
        echo json_encode(['success' => false, 'message' => 'A senha antiga está incorreta.']);
        exit;
    }

    // 7. Se tudo estiver OK, criar o novo hash e atualizar
    $novo_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
    
    $stmt_update = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
    $stmt_update->execute([$novo_hash, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso!']);

} catch (PDOException $e) {
    error_log("Erro ao alterar senha: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados. Tente novamente.']);
}
?>