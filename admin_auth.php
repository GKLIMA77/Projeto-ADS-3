<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Credenciais do painel completo (admin.php)
const ADMIN_USUARIO    = 'adrian';
const ADMIN_SENHA_HASH = '$2y$10$.Qp1ylPYlfiL6bReApNx9uQjjUcvAMLpiT8TwOqhcdrd5WcJE02WS';

const WHATSAPP_VENDAS = '449973062201';

// Retorna true se o admin estiver logado
function adminAutenticado(): bool {
    return !empty($_SESSION['admin_autenticado']);
}

// Bloqueia acesso não autorizado via API (responde JSON e encerra)
function exigirAdmin(): void {
    if (!adminAutenticado()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.']);
        exit;
    }
}
