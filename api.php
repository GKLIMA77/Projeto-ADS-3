<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/admin_auth.php';
include __DIR__ . '/conexao.php';

$acao = $_GET['acao'] ?? '';

// Tratamento para requisições POST com JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['acao'])) {
        $acao = $input['acao'];
    }
}

if ($acao !== 'produtos_publicos') {
    exigirAdmin();
}

function responder($sucesso, $mensagem, $dados = null) {
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function texto($valor, $padrao = '') {
    return trim((string)($valor ?? $padrao));
}

try {
    switch ($acao) {
        // CALL da Stored Procedure otimizada
        case 'indicadores':
            $res = $conexao->query("CALL sp_obter_indicadores_dashboard()");
            $indicadores = $res->fetch_assoc();
            $res->free();
            // Libera conexão para consultas subsequentes do MySQL
            while ($conexao->more_results() && $conexao->next_result()) {
                if ($r = $conexao->store_result()) $r->free();
            }
            responder(true, 'Indicadores obtidos com sucesso', $indicadores);
            break;

        // Leitura usando a View Analítica CTE
        case 'ranking':
            $res = $conexao->query("SELECT * FROM vw_relatorio_servicos ORDER BY total_agendamentos DESC");
            $ranking = [];
            while ($linha = $res->fetch_assoc()) {
                $ranking[] = [
                    'id' => (int)$linha['id'],
                    'nome' => $linha['nome'],
                    'preco' => (float)$linha['preco'],
                    'total_agendamentos' => (int)$linha['total_agendamentos'],
                    'faturamento' => (float)$linha['faturamento_gerado']
                ];
            }
            responder(true, 'Ranking obtido', $ranking);
            break;

        // Leitura usando a View de Centralização
        case 'agendamentos':
            $statusFiltro = $_GET['status'] ?? '';
            $sql = "SELECT * FROM vw_central_agendamentos";
            if (!empty($statusFiltro)) {
                $sql .= " WHERE status = '" . $conexao->real_escape_string($statusFiltro) . "'";
            }
            $sql .= " ORDER BY data_hora DESC";
            
            $res = $conexao->query($sql);
            $lista = [];
            while ($linha = $res->fetch_assoc()) {
                $lista[] = [
                    'id' => (int)$linha['agendamento_id'],
                    'cliente_nome' => $linha['cliente_nome'],
                    'servico_nome' => $linha['servico_nome'],
                    'servico_preco' => (float)$linha['servico_preco'],
                    'data_hora' => $linha['data_hora'],
                    'status' => $linha['status']
                ];
            }
            responder(true, 'Lista de agendamentos', $lista);
            break;

        // CRUD SERVIÇOS: Regra de exclusão tratada com mensagem clara
        case 'excluir_servico':
            $id = (int)($input['id'] ?? 0);
            $check = $conexao->query("SELECT id FROM agendamentos WHERE servico_id = $id");
            if ($check->num_rows > 0) {
                responder(false, 'Não é possível excluir este serviço pois existem agendamentos ativos vinculados a ele!');
            }
            $conexao->query("DELETE FROM servicos WHERE id = $id");
            responder(true, 'Serviço removido com sucesso.');
            break;

        case 'clientes':
            $res = $conexao->query("SELECT * FROM clientes ORDER BY nome ASC");
            $clientes = [];
            while ($linha = $res->fetch_assoc()) {
                $clientes[] = $linha;
            }
            responder(true, 'Lista de clientes', $clientes);
            break;

        case 'servicos':
            $res = $conexao->query("SELECT * FROM servicos ORDER BY nome ASC");
            $servicos = [];
            while ($linha = $res->fetch_assoc()) {
                $servicos[] = $linha;
            }
            responder(true, 'Lista de serviços', $servicos);
            break;

        case 'categorias':
            $res = $conexao->query("SELECT * FROM categorias ORDER BY nome ASC");
            $categorias = [];
            while ($linha = $res->fetch_assoc()) $categorias[] = $linha;
            responder(true, 'Lista de categorias', $categorias);
            break;

        case 'produtos':
            $res = $conexao->query("SELECT p.*, c.nome AS categoria_nome FROM produtos p JOIN categorias c ON c.id = p.categoria_id ORDER BY p.criado_em DESC");
            $produtos = [];
            while ($linha = $res->fetch_assoc()) $produtos[] = $linha;
            responder(true, 'Lista de produtos', $produtos);
            break;

        case 'produtos_publicos':
            $res = $conexao->query("SELECT p.*, c.nome AS categoria_nome FROM produtos p JOIN categorias c ON c.id = p.categoria_id WHERE p.status = 'aprovado' AND c.status = 'aprovada' ORDER BY c.nome, p.nome");
            $produtos = [];
            while ($linha = $res->fetch_assoc()) $produtos[] = $linha;
            responder(true, 'Produtos disponíveis', $produtos);
            break;

        case 'salvar_categoria':
            $id = (int)($input['id'] ?? 0);
            $nome = texto($input['nome']);
            $status = texto($input['status'], 'pendente');
            if ($nome === '' || !in_array($status, ['pendente', 'aprovada', 'cancelada'], true)) responder(false, 'Informe uma categoria e um status válido.');
            if ($id > 0) {
                $stmt = $conexao->prepare('UPDATE categorias SET nome = ?, status = ? WHERE id = ?');
                $stmt->bind_param('ssi', $nome, $status, $id);
            } else {
                $stmt = $conexao->prepare('INSERT INTO categorias (nome, status) VALUES (?, ?)');
                $stmt->bind_param('ss', $nome, $status);
            }
            if (!$stmt->execute()) responder(false, 'Não foi possível salvar a categoria: ' . $stmt->error);
            responder(true, 'Categoria salva com sucesso.');
            break;

        case 'excluir_categoria':
            $id = (int)($input['id'] ?? 0);
            $check = $conexao->query("SELECT id FROM produtos WHERE categoria_id = $id LIMIT 1");
            if ($check && $check->num_rows > 0) responder(false, 'Exclua ou mova os produtos desta categoria antes de removê-la.');
            $conexao->query("DELETE FROM categorias WHERE id = $id");
            responder(true, 'Categoria excluída.');
            break;

        case 'salvar_produto':
            $id = (int)($input['id'] ?? 0);
            $categoriaId = (int)($input['categoria_id'] ?? 0);
            $nome = texto($input['nome']);
            $descricao = texto($input['descricao']);
            $preco = (float)($input['preco'] ?? 0);
            $imagem = texto($input['imagem']);
            $status = texto($input['status'], 'pendente');
            if ($categoriaId <= 0 || $nome === '' || $preco <= 0 || !in_array($status, ['pendente', 'aprovado', 'cancelado'], true)) responder(false, 'Preencha nome, categoria, preço e status válido.');
            if ($id > 0) {
                $stmt = $conexao->prepare('UPDATE produtos SET categoria_id = ?, nome = ?, descricao = ?, preco = ?, imagem = ?, status = ? WHERE id = ?');
                $stmt->bind_param('issdssi', $categoriaId, $nome, $descricao, $preco, $imagem, $status, $id);
            } else {
                $stmt = $conexao->prepare('INSERT INTO produtos (categoria_id, nome, descricao, preco, imagem, status) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('issdss', $categoriaId, $nome, $descricao, $preco, $imagem, $status);
            }
            if (!$stmt->execute()) responder(false, 'Não foi possível salvar o produto: ' . $stmt->error);
            responder(true, 'Produto salvo com sucesso.');
            break;

        case 'excluir_produto':
            $id = (int)($input['id'] ?? 0);
            $conexao->query("DELETE FROM produtos WHERE id = $id");
            responder(true, 'Produto excluído.');
            break;

        default:
            responder(false, 'Ação não reconhecida.');
    }
} catch (Exception $e) {
    responder(false, 'Erro no servidor: ' . $e->getMessage());
}
?>