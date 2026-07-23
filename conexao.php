<?php
// ============================================================
// conexao.php — Conexão com o banco de dados MySQL
// ============================================================

$conexao = new mysqli(
    'localhost',              // Endereço do servidor
    'Gabriel17',             // Usuário do MySQL
    '17092007',              // Senha do MySQL
    'barbearia_adrian_souza' // Nome do banco de dados
);

// Se der erro ao conectar, exibe a mensagem e para tudo
if ($conexao->connect_error) {
    die('Erro de conexão: ' . $conexao->connect_error);
}

// UTF-8 para suportar acentos e caracteres especiais
$conexao->set_charset('utf8mb4');
