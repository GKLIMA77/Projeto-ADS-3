<?php
ob_start();                       
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

include 'conexao.php';

// Pega os dados enviados pelo formulário
$nome    = trim($_POST['agenda-nome']    ?? '');
$data    = trim($_POST['agenda-data']    ?? '');
$horario = trim($_POST['agenda-horario'] ?? '');
$servico = trim($_POST['agenda-servico'] ?? '');

// Valida se todos os campos foram preenchidos
if (!$nome || !$data || !$horario || !$servico) {
    responder(false, 'Preencha todos os campos.');
}

// Busca o serviço pelo nome (só aceita serviços ativos)
$stmt = $conexao->prepare('SELECT id FROM servicos WHERE nome = ? AND ativo = 1');
$stmt->bind_param('s', $servico);
$stmt->execute();
$resultado = $stmt->get_result();
$stmt->close();

if ($resultado->num_rows === 0) {
    responder(false, 'Serviço inválido ou inativo.');
}

$servicoId = $resultado->fetch_assoc()['id'];
$dataHora  = $data . ' ' . $horario . ':00'; // Formata para DATETIME do MySQL

// Insere o agendamento com status "pendente"
$stmt = $conexao->prepare("
    INSERT INTO agendamentos (cliente_nome, servico_id, data_hora, status)
    VALUES (?, ?, ?, 'pendente')
");
$stmt->bind_param('sis', $nome, $servicoId, $dataHora);

if (!$stmt->execute()) {
    responder(false, 'Erro ao salvar: ' . $stmt->error);
}

responder(true, 'Agendamento realizado com sucesso!', [
    'agendamento_id' => $conexao->insert_id
]);

// Envia resposta JSON e encerra o script
function responder(bool $sucesso, string $mensagem, array $extras = []): void {
    ob_clean();
    echo json_encode(array_merge(['sucesso' => $sucesso, 'mensagem' => $mensagem], $extras));
    exit;
}
