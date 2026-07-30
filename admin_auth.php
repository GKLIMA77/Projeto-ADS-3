<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('ADMIN_USUARIO',    'adrian');
define('ADMIN_SENHA_HASH', password_hash('1234', PASSWORD_BCRYPT));
define('WHATSAPP_VENDAS',  '449973062201');

function adminAutenticado(): bool {
    return !empty($_SESSION['admin_autenticado']);
}

function exigirAdmin(): void {
    if (!adminAutenticado()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.']);
        exit;
    }
}