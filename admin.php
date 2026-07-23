<?php
require_once __DIR__ . '/admin_auth.php';

if (isset($_GET['sair'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

$erroLogin = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entrar'])) {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if (hash_equals(ADMIN_USUARIO, $usuario) && password_verify($senha, ADMIN_SENHA_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_autenticado'] = true;
        header('Location: admin.php');
        exit;
    }
    $erroLogin = 'Usuário ou senha inválidos.';
}

if (!adminAutenticado()):
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Barbearia Adrian Souza</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="style.css?v=2">
</head>
<body class="login-page">
  <main class="login-card">
    <div class="login-icon"><i class="fa-solid fa-lock"></i></div>
    <p class="mini-texto">ÁREA RESTRITA</p>
    <h1>Painel da barbearia</h1>
    <p class="login-subtitulo">Entre com seu usuário para gerenciar produtos, clientes e agendamentos.</p>
    <?php if ($erroLogin): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($erroLogin); ?></div><?php endif; ?>
    <form method="post" class="login-form">
      <label for="usuario">Usuário</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" required>
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" autocomplete="current-password" required>
      <button type="submit" name="entrar" class="btn btn-gold w-100 mt-3"><i class="fa-solid fa-right-to-bracket me-2"></i>Entrar no painel</button>
    </form>
    <a class="login-voltar" href="index.php"><i class="fa-solid fa-arrow-left me-1"></i>Voltar para o site</a>
  </main>
</body>
</html>
<?php exit; endif; ?>
<?php include __DIR__ . '/conexao.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Barbearia Adrian Souza</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=2">
</head>
<body>

<div id="loading-overlay"><div class="spinner-border" role="status"></div></div>

<div class="d-flex">
  <!-- SIDEBAR -->
  <div class="sidebar">
    <span class="brand">💈 Admin</span>
    <nav class="nav-side d-flex flex-column">
      <a href="#" class="nav-link active" data-tab="dashboard"><i class="fas fa-chart-bar me-2"></i>Dashboard</a>
      <a href="#" class="nav-link" data-tab="agendamentos"><i class="fas fa-calendar me-2"></i>Agendamentos</a>
      <a href="clientes.php" class="nav-link"><i class="fas fa-users me-2"></i>Clientes</a>
      <a href="#" class="nav-link" data-tab="servicos"><i class="fas fa-scissors me-2"></i>Serviços</a>
      <a href="#" class="nav-link" data-tab="loja"><i class="fas fa-store me-2"></i>Loja</a>
      <hr>
      <a href="index.php" class="nav-link"><i class="fas fa-home me-2"></i>Ver Site</a>
      <a href="admin.php?sair=1" class="nav-link"><i class="fas fa-right-from-bracket me-2"></i>Sair</a>
    </nav>
  </div>

  <!-- MAIN -->
  <div class="main-content flex-grow-1">

    <!-- DASHBOARD -->
    <div id="tab-dashboard" class="tab-section">
      <h2 class="section-title">Dashboard</h2>
      <div class="row g-3 mb-4" id="stats-container">
        <div class="col-md-3"><div class="card-stat"><h4 id="stat-total">—</h4><p>Total de Agendamentos</p></div></div>
        <div class="col-md-3"><div class="card-stat"><h4 id="stat-fat">—</h4><p>Faturamento (confirmados)</p></div></div>
        <div class="col-md-3"><div class="card-stat"><h4 id="stat-hoje">—</h4><p>Agendamentos Hoje</p></div></div>
        <div class="col-md-3"><div class="card-stat"><h4 id="stat-pend">—</h4><p>Pendentes</p></div></div>
      </div>
      <h3 class="section-title subtitulo">Ranking de Serviços</h3>
      <div id="ranking-container" class="row g-3"></div>
    </div>

    <!-- AGENDAMENTOS -->
    <div id="tab-agendamentos" class="tab-section d-none">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">Agendamentos</h2>
        <button class="btn btn-gold" onclick="abrirModalNovoAgendamento()"><i class="fas fa-plus me-2"></i>Novo</button>
      </div>
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <button class="tab-btn active" data-filtro="">Todos</button>
        <button class="tab-btn" data-filtro="pendente">Pendentes</button>
        <button class="tab-btn" data-filtro="confirmado">Confirmados</button>
        <button class="tab-btn" data-filtro="cancelado">Cancelados</button>
      </div>
      <div class="table-responsive">
        <table class="table table-dark table-hover">
          <thead><tr><th>#</th><th>Cliente</th><th>Serviço</th><th>Data/Hora</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody id="tabela-agendamentos"></tbody>
        </table>
        <div id="msg-sem-agendamentos" class="text-secondary text-center py-4 d-none">Nenhum agendamento encontrado.</div>
      </div>
    </div>

    <!-- CLIENTES -->
    <div id="tab-clientes" class="tab-section d-none">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">Clientes</h2>
        <button class="btn btn-gold" onclick="abrirModalCliente()"><i class="fas fa-plus me-2"></i>Novo Cliente</button>
      </div>
      <div class="table-responsive">
        <table class="table table-dark table-hover">
          <thead><tr><th>#</th><th>Nome</th><th>Telefone</th><th>E-mail</th><th>Ações</th></tr></thead>
          <tbody id="tabela-clientes"></tbody>
        </table>
        <div id="msg-sem-clientes" class="text-secondary text-center py-4 d-none">Nenhum cliente cadastrado.</div>
      </div>
    </div>

    <!-- SERVIÇOS -->
    <div id="tab-servicos" class="tab-section d-none">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">Serviços</h2>
        <button class="btn btn-gold" onclick="abrirModalServico()"><i class="fas fa-plus me-2"></i>Novo Serviço</button>
      </div>
      <div class="table-responsive">
        <table class="table table-dark table-hover">
          <thead><tr><th>#</th><th>Nome</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody id="tabela-servicos"></tbody>
        </table>
        <div id="msg-sem-servicos" class="text-secondary text-center py-4 d-none">Nenhum serviço cadastrado.</div>
      </div>
    </div>

    <!-- LOJA -->
    <div id="tab-loja" class="tab-section d-none">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div><h2 class="section-title mb-1">Loja</h2><p class="text-secondary mb-0">Aprove, cancele ou exclua categorias e produtos exibidos no site.</p></div>
        <div class="d-flex gap-2"><button class="btn btn-gold" onclick="abrirModalCategoria()"><i class="fas fa-folder-plus me-2"></i>Categoria</button><button class="btn btn-gold" onclick="abrirModalProduto()"><i class="fas fa-plus me-2"></i>Produto</button></div>
      </div>
      <h5 class="text-light mt-4">Categorias</h5>
      <div class="table-responsive"><table class="table table-dark table-hover"><thead><tr><th>#</th><th>Nome</th><th>Status</th><th>Ações</th></tr></thead><tbody id="tabela-categorias"></tbody></table><div id="msg-sem-categorias" class="text-secondary text-center py-3 d-none">Nenhuma categoria cadastrada.</div></div>
      <h5 class="text-light mt-5">Produtos</h5>
      <div class="table-responsive"><table class="table table-dark table-hover"><thead><tr><th>Imagem</th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead><tbody id="tabela-produtos"></tbody></table><div id="msg-sem-produtos" class="text-secondary text-center py-3 d-none">Nenhum produto cadastrado.</div></div>
    </div>

  </div>
</div>

<!-- MODAL CATEGORIA -->
<div class="modal fade" id="modalCategoria" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="modal-cat-titulo">Categoria</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" id="cat-id"><div class="mb-3"><label class="form-label">Nome</label><input type="text" id="cat-nome" class="form-control" placeholder="Ex.: Finalização"></div><div class="mb-3"><label class="form-label">Status</label><select id="cat-status" class="form-select"><option value="pendente">Pendente</option><option value="aprovada">Aprovada</option><option value="cancelada">Cancelada</option></select></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-gold" onclick="salvarCategoria()">Salvar</button></div>
</div></div></div>

<!-- MODAL PRODUTO -->
<div class="modal fade" id="modalProduto" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="modal-prod-titulo">Produto</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" id="prod-id"><div class="row g-3"><div class="col-md-8"><label class="form-label">Nome</label><input type="text" id="prod-nome" class="form-control" placeholder="Ex.: Pomada Modeladora Matte"></div><div class="col-md-4"><label class="form-label">Preço (R$)</label><input type="number" id="prod-preco" class="form-control" step="0.01" min="0.01"></div><div class="col-md-6"><label class="form-label">Categoria</label><select id="prod-categoria" class="form-select"></select></div><div class="col-md-6"><label class="form-label">Status</label><select id="prod-status" class="form-select"><option value="pendente">Pendente</option><option value="aprovado">Aprovado</option><option value="cancelado">Cancelado</option></select></div><div class="col-12"><label class="form-label">Descrição</label><textarea id="prod-descricao" class="form-control" rows="2"></textarea></div><div class="col-12"><label class="form-label">URL da imagem</label><input type="url" id="prod-imagem" class="form-control" placeholder="https://..."></div></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-gold" onclick="salvarProduto()">Salvar</button></div>
</div></div></div>

<!-- MODAL AGENDAMENTO -->
<div class="modal fade" id="modalAgendamento" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="modal-ag-titulo">Agendamento</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ag-id">
      <div class="mb-3"><label class="form-label">Cliente</label><input type="text" id="ag-nome" class="form-control" placeholder="Nome do cliente"></div>
      <div class="mb-3"><label class="form-label">Serviço</label>
        <select id="ag-servico" class="form-select"><option value="">Selecione</option></select>
      </div>
      <div class="mb-3"><label class="form-label">Data/Hora</label><input type="datetime-local" id="ag-datahora" class="form-control"></div>
      <div class="mb-3"><label class="form-label">Status</label>
        <select id="ag-status" class="form-select">
          <option value="pendente">Pendente</option>
          <option value="confirmado">Confirmado</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" class="btn btn-gold" onclick="salvarAgendamento()">Salvar</button>
    </div>
  </div></div>
</div>

<!-- MODAL CLIENTE -->
<div class="modal fade" id="modalCliente" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="modal-cli-titulo">Cliente</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="cli-id">
      <div class="mb-3"><label class="form-label">Nome</label><input type="text" id="cli-nome" class="form-control"></div>
      <div class="mb-3"><label class="form-label">Telefone</label><input type="text" id="cli-telefone" class="form-control"></div>
      <div class="mb-3"><label class="form-label">E-mail</label><input type="email" id="cli-email" class="form-control"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" class="btn btn-gold" onclick="salvarCliente()">Salvar</button>
    </div>
  </div></div>
</div>

<!-- MODAL SERVIÇO -->
<div class="modal fade" id="modalServico" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="modal-sv-titulo">Serviço</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="sv-id">
      <div class="mb-3"><label class="form-label">Nome</label><input type="text" id="sv-nome" class="form-control"></div>
      <div class="mb-3"><label class="form-label">Preço (R$)</label><input type="number" id="sv-preco" class="form-control" step="0.01" min="0"></div>
      <div class="mb-3"><label class="form-label">Ativo</label>
        <select id="sv-ativo" class="form-select"><option value="1">Sim</option><option value="0">Não</option></select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" class="btn btn-gold" onclick="salvarServico()">Salvar</button>
    </div>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="admin.js"></script>
</body>
</html>
