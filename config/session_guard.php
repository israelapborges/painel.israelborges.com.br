<?php
// config/session_guard.php
// Este script verifica se o utilizador está logado.
// Se não estiver, bloqueia o acesso à API.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(403); // 403 Forbidden
    echo json_encode(['success' => false, 'message' => 'Acesso negado. A sua sessão pode ter expirado.']);
    exit;
}

// Se o script continuar, o utilizador está autenticado.
?>