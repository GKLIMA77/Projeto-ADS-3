<?php
require_once __DIR__ . '/admin_auth.php';

if (isset($_GET['sair'])) {
    $_SESSION = [];
    session_destroy();
    session_start();
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'admin.php';
    header('Location: ' . $redirect);
    exit;
}

$erroLogin = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entrar'])) {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';
    if (hash_equals(ADMIN_USUARIO, $usuario) && password_verify($senha, ADMIN_SENHA_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_autenticado'] = true;
        $_SESSION['admin_ultimo_acesso'] = time();
        header('Location: admin.php');
        exit;
    }
    $erroLogin = 'Usuário ou senha inválidos.';
}

if (adminAutenticado()) {
    $ultimo = $_SESSION['admin_ultimo_acesso'] ?? 0;
    if (time() - $ultimo > 1800) {
        $_SESSION = [];
        session_destroy();
        header('Location: admin.php');
        exit;
    }
    $_SESSION['admin_ultimo_acesso'] = time();
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
    <p class="login-subtitulo">Entre com seu usuário para gerenciar agendamentos e clientes.</p>
    <?php if ($erroLogin): ?>
      <div class="alert alert-danger py-2"><?php echo htmlspecialchars($erroLogin); ?></div>
    <?php endif; ?>
    <form method="post" class="login-form">
      <label for="usuario">Usuário</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" required>
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" autocomplete="current-password" required>
      <button type="submit" name="entrar" class="btn btn-gold w-100 mt-3">
        <i class="fa-solid fa-right-to-bracket me-2"></i>Entrar no painel
      </button>
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
      <a href="#" class="nav-link active" data-tab="agendamentos"><i class="fas fa-calendar me-2"></i>Agendamentos</a>
      <a href="#" class="nav-link" data-tab="clientes"><i class="fas fa-users me-2"></i>Clientes</a>
      <a href="#" class="nav-link" data-tab="loja"><i class="fas fa-store me-2"></i>Loja</a>
      <hr>
      <a href="admin.php?sair=1&redirect=index.php" class="nav-link"><i class="fas fa-home me-2"></i>Ver Site</a>
      <a href="admin.php?sair=1" class="nav-link"><i class="fas fa-right-from-bracket me-2"></i>Sair</a>
    </nav>
  </div>

  <!-- MAIN -->
  <div class="main-content flex-grow-1">

    <!-- AGENDAMENTOS -->
    <div id="tab-agendamentos" class="tab-section">
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
      <div id="ag-faturamento-total" class="mb-3 text-warning fw-bold" style="font-size:15px;"></div>
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

    <!-- LOJA -->
    <div id="tab-loja" class="tab-section d-none">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
          <h2 class="section-title mb-1">Loja</h2>
          <p class="text-secondary mb-0">Gerencie categorias e produtos exibidos no site.</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-gold" onclick="abrirModalProduto()"><i class="fas fa-plus me-2"></i>Novo Produto</button>
        </div>
      </div>

      <h5 class="text-light mt-2">Produtos</h5>
      <div class="table-responsive">
        <table class="table table-dark table-hover">
          <thead><tr><th>Imagem</th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody id="tabela-produtos"></tbody>
        </table>
        <div id="msg-sem-produtos" class="text-secondary text-center py-3 d-none">Nenhum produto cadastrado.</div>
      </div>
    </div>

  </div>
</div>

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

<!-- MODAL CATEGORIA -->
<div class="modal fade" id="modalCategoria" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header">
    <h5 class="modal-title" id="modal-cat-titulo">Categoria</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="cat-id">
    <div class="mb-3"><label class="form-label">Nome</label><input type="text" id="cat-nome" class="form-control" placeholder="Ex.: Finalização"></div>
    <div class="mb-3"><label class="form-label">Status</label>
      <select id="cat-status" class="form-select">
        <option value="pendente">Pendente</option>
        <option value="aprovada">Aprovada</option>
        <option value="cancelada">Cancelada</option>
      </select>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-gold" onclick="salvarCategoria()">Salvar</button>
  </div>
</div></div></div>

<!-- MODAL PRODUTO -->
<div class="modal fade" id="modalProduto" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header">
    <h5 class="modal-title" id="modal-prod-titulo">Produto</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <input type="hidden" id="prod-id">
    <div class="row g-3">
      <div class="col-md-8"><label class="form-label">Nome</label><input type="text" id="prod-nome" class="form-control" placeholder="Ex.: Pomada Modeladora Matte"></div>
      <div class="col-md-4"><label class="form-label">Preço (R$)</label><input type="number" id="prod-preco" class="form-control" step="0.01" min="0.01"></div>
      <div class="col-md-6"><label class="form-label">Categoria</label><select id="prod-categoria" class="form-select"></select></div>
      <div class="col-md-6"><label class="form-label">Status</label>
        <select id="prod-status" class="form-select">
          <option value="pendente">Pendente</option>
          <option value="aprovado">Aprovado</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>
      <div class="col-12"><label class="form-label">Descrição</label><textarea id="prod-descricao" class="form-control" rows="2"></textarea></div>
      <div class="col-12"><label class="form-label">URL da imagem</label><input type="url" id="prod-imagem" class="form-control" placeholder="https://..."></div>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-gold" onclick="salvarProduto()">Salvar</button>
  </div>
</div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
console.log("admin.js carregado");

// ── CACHE ─────────────────────────────────────────────────────
let agendamentosCache = [];
let servicosCache     = [];
let clientesCache     = [];
let categoriasCache   = [];
let produtosCache     = [];
let filtroStatusAtual = "";

// ── UTILS ─────────────────────────────────────────────────────
function getEl(id) {
    return document.getElementById(id);
}

function mostrarLoading(ativo) {
    const el = getEl("loading-overlay");
    if (el) el.style.display = ativo ? "flex" : "none";
}

function formatarData(dataHora) {
    if (!dataHora) return "—";
    const d = new Date(dataHora.replace(" ", "T"));
    return d.toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" });
}

function formatarMoeda(valor) {
    return Number(valor).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function badgeStatus(status) {
    const cores = { confirmado: "success", pendente: "warning text-dark", cancelado: "danger" };
    return `<span class="badge bg-${cores[status] || 'secondary'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

function badgeLojaStatus(status) {
    const cores = { aprovado: "success", aprovada: "success", pendente: "warning text-dark", cancelado: "danger", cancelada: "danger" };
    return `<span class="badge bg-${cores[status] || 'secondary'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

// ── API ───────────────────────────────────────────────────────
async function buscarDados(acao, params = {}) {
    try {
        const qs = new URLSearchParams(params).toString();
        const resp = await fetch(`api.php?acao=${acao}${qs ? "&" + qs : ""}`);
        const json = await resp.json();
        return json.sucesso ? json.dados : [];
    } catch (e) {
        console.error("Erro buscarDados:", e);
        return [];
    }
}

async function enviarDados(payload) {
    try {
        const resp = await fetch("api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });
        return await resp.json();
    } catch (e) {
        console.error("Erro enviarDados:", e);
        return { sucesso: false, mensagem: "Erro de rede." };
    }
}

// ── NAVEGAÇÃO ─────────────────────────────────────────────────
function ativarTab(nome) {
    document.querySelectorAll(".tab-section").forEach(s => s.classList.add("d-none"));
    document.querySelectorAll(".nav-side .nav-link").forEach(l => l.classList.remove("active"));
    const secao = getEl(`tab-${nome}`);
    const link  = document.querySelector(`.nav-side [data-tab="${nome}"]`);
    if (secao) secao.classList.remove("d-none");
    if (link)  link.classList.add("active");
    if (nome === "agendamentos") { carregarServicos(); carregarAgendamentos(); }
    if (nome === "clientes")     carregarClientes();
    if (nome === "loja")         carregarLoja();
}

// ── AGENDAMENTOS ──────────────────────────────────────────────
function preencherSelectServicos(selecionado = "") {
    const sel = getEl("ag-servico");
    if (!sel) return;
    sel.innerHTML = '<option value="">Selecione</option>';
    servicosCache.forEach(s => {
        const opt = document.createElement("option");
        opt.value = String(s.id);
        opt.textContent = `${s.nome} — ${formatarMoeda(s.preco)}`;
        if (s.nome === selecionado || String(s.id) === String(selecionado)) opt.selected = true;
        sel.appendChild(opt);
    });
}

async function carregarServicos() {
    const dados = await buscarDados("servicos");
    servicosCache = dados || [];
}

async function carregarAgendamentos(status = "") {
    mostrarLoading(true);
    const params = status ? { status } : {};
    const dados = await buscarDados("agendamentos", params);
    mostrarLoading(false);
    agendamentosCache = dados || [];

    const tbody    = getEl("tabela-agendamentos");
    const msgVazio = getEl("msg-sem-agendamentos");
    const fatEl    = getEl("ag-faturamento-total");

    if (!tbody) return;

    if (agendamentosCache.length === 0) {
        tbody.innerHTML = "";
        if (msgVazio) msgVazio.classList.remove("d-none");
        if (fatEl) fatEl.textContent = "";
        return;
    }

    if (msgVazio) msgVazio.classList.add("d-none");

    const confirmados = agendamentosCache.filter(a => a.status === "confirmado");
    const faturamento = confirmados.reduce((s, a) => s + Number(a.servico_preco || 0), 0);
    if (fatEl && faturamento > 0) fatEl.textContent = `Faturamento confirmado: ${formatarMoeda(faturamento)}`;

    tbody.innerHTML = agendamentosCache.map(a => `
        <tr>
            <td>${a.id}</td>
            <td>${a.cliente_nome}</td>
            <td>${a.servico_nome}</td>
            <td>${formatarData(a.data_hora)}</td>
            <td>${badgeStatus(a.status)}</td>
            <td>
                <button class="btn btn-sm btn-outline-warning me-1" onclick="editarAgendamento(${a.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="excluirAgendamento(${a.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join("");
}

function abrirModalNovoAgendamento() {
    getEl("ag-id").value = "";
    getEl("ag-nome").value = "";
    getEl("ag-datahora").value = "";
    getEl("ag-status").value = "pendente";
    getEl("modal-ag-titulo").textContent = "Novo Agendamento";
    preencherSelectServicos();
    new bootstrap.Modal(getEl("modalAgendamento")).show();
}

function editarAgendamento(id) {
    const ag = agendamentosCache.find(a => String(a.id) === String(id));
    if (!ag) { console.warn("Agendamento não encontrado:", id, agendamentosCache); return; }
    getEl("modal-ag-titulo").textContent = "Editar Agendamento";
    getEl("ag-id").value       = ag.id;
    getEl("ag-nome").value     = ag.cliente_nome;
    getEl("ag-datahora").value = ag.data_hora.replace(" ", "T").slice(0, 16);
    getEl("ag-status").value   = ag.status;
    preencherSelectServicos(ag.servico_nome);
    new bootstrap.Modal(getEl("modalAgendamento")).show();
}

async function salvarAgendamento() {
    const id        = getEl("ag-id").value;
    const nome      = getEl("ag-nome").value.trim();
    const servicoId = getEl("ag-servico").value;
    const dataHora  = getEl("ag-datahora").value.replace("T", " ");
    const status    = getEl("ag-status").value;
    if (!nome || !servicoId || !dataHora) { alert("Preencha todos os campos."); return; }
    const acao = id ? "editar_agendamento" : "criar_agendamento";
    const res  = await enviarDados({ acao, id, nome, servico_id: servicoId, data_hora: dataHora, status });
    if (res.sucesso) {
        bootstrap.Modal.getInstance(getEl("modalAgendamento"))?.hide();
        carregarAgendamentos(filtroStatusAtual);
    } else {
        alert(res.mensagem || "Erro ao salvar.");
    }
}

async function excluirAgendamento(id) {
    if (!confirm("Excluir este agendamento?")) return;
    const res = await enviarDados({ acao: "excluir_agendamento", id });
    if (res.sucesso) carregarAgendamentos(filtroStatusAtual);
    else alert(res.mensagem || "Erro ao excluir.");
}

// ── CLIENTES ──────────────────────────────────────────────────
async function carregarClientes() {
    mostrarLoading(true);
    const dados = await buscarDados("clientes");
    mostrarLoading(false);
    clientesCache = dados || [];

    const tbody    = getEl("tabela-clientes");
    const msgVazio = getEl("msg-sem-clientes");
    if (!tbody) return;

    if (clientesCache.length === 0) {
        tbody.innerHTML = "";
        if (msgVazio) msgVazio.classList.remove("d-none");
        return;
    }
    if (msgVazio) msgVazio.classList.add("d-none");

    tbody.innerHTML = clientesCache.map(c => `
        <tr>
            <td>${c.id}</td>
            <td>${c.nome}</td>
            <td>${c.telefone || "—"}</td>
            <td>${c.email || "—"}</td>
            <td>
                <button class="btn btn-sm btn-outline-warning me-1" onclick="editarCliente(${c.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="excluirCliente(${c.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join("");
}

function abrirModalCliente() {
    getEl("cli-id").value       = "";
    getEl("cli-nome").value     = "";
    getEl("cli-telefone").value = "";
    getEl("cli-email").value    = "";
    getEl("modal-cli-titulo").textContent = "Novo Cliente";
    new bootstrap.Modal(getEl("modalCliente")).show();
}

function editarCliente(id) {
    console.log("editarCliente chamado:", id, clientesCache);
    const c = clientesCache.find(x => String(x.id) === String(id));
    if (!c) { console.warn("Cliente não encontrado:", id); return; }
    getEl("modal-cli-titulo").textContent = "Editar Cliente";
    getEl("cli-id").value       = c.id;
    getEl("cli-nome").value     = c.nome;
    getEl("cli-telefone").value = c.telefone || "";
    getEl("cli-email").value    = c.email    || "";
    new bootstrap.Modal(getEl("modalCliente")).show();
}

async function salvarCliente() {
    const id       = getEl("cli-id").value;
    const nome     = getEl("cli-nome").value.trim();
    const telefone = getEl("cli-telefone").value.trim();
    const email    = getEl("cli-email").value.trim();
    if (!nome) { alert("Informe o nome do cliente."); return; }
    const acao = id ? "editar_cliente" : "criar_cliente";
    const res  = await enviarDados({ acao, id, nome, telefone, email });
    if (res.sucesso) {
        bootstrap.Modal.getInstance(getEl("modalCliente"))?.hide();
        carregarClientes();
    } else {
        alert(res.mensagem || "Erro ao salvar.");
    }
}

async function excluirCliente(id) {
    if (!confirm("Excluir este cliente?")) return;
    const res = await enviarDados({ acao: "excluir_cliente", id });
    if (res.sucesso) carregarClientes();
    else alert(res.mensagem || "Erro ao excluir.");
}

// ── LOJA ──────────────────────────────────────────────────────
function preencherSelectCategorias(selecionada = "") {
    const sel = getEl("prod-categoria");
    if (!sel) return;
    sel.innerHTML = categoriasCache.map(c =>
        `<option value="${c.id}" ${String(c.id) === String(selecionada) ? "selected" : ""}>${c.nome}</option>`
    ).join("");
}

async function carregarLoja() {
    mostrarLoading(true);
    const [cats, prods] = await Promise.all([buscarDados("categorias"), buscarDados("produtos")]);
    mostrarLoading(false);
    categoriasCache = cats  || [];
    produtosCache   = prods || [];

    const tbody    = getEl("tabela-produtos");
    const msgVazio = getEl("msg-sem-produtos");
    if (!tbody) return;

    if (produtosCache.length === 0) {
        tbody.innerHTML = "";
        if (msgVazio) msgVazio.classList.remove("d-none");
        return;
    }
    if (msgVazio) msgVazio.classList.add("d-none");

    tbody.innerHTML = produtosCache.map(p => `
        <tr>
            <td><img src="${p.imagem || 'https://via.placeholder.com/60'}" style="width:50px;height:50px;object-fit:cover;border-radius:6px" alt=""></td>
            <td><strong>${p.nome}</strong><br><small class="text-secondary">${p.descricao || ""}</small></td>
            <td>${p.categoria_nome || "—"}</td>
            <td>${formatarMoeda(p.preco)}</td>
            <td>${badgeLojaStatus(p.status)}</td>
            <td>
                <button class="btn btn-sm btn-outline-warning me-1" onclick="editarProduto(${p.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="excluirProduto(${p.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join("");
}

function abrirModalProduto() {
    getEl("prod-id").value        = "";
    getEl("prod-nome").value      = "";
    getEl("prod-descricao").value = "";
    getEl("prod-preco").value     = "";
    getEl("prod-imagem").value    = "";
    getEl("prod-status").value    = "pendente";
    getEl("modal-prod-titulo").textContent = "Novo Produto";
    preencherSelectCategorias();
    new bootstrap.Modal(getEl("modalProduto")).show();
}

function editarProduto(id) {
    console.log("editarProduto chamado:", id, produtosCache);
    const p = produtosCache.find(x => String(x.id) === String(id));
    if (!p) { console.warn("Produto não encontrado:", id); return; }
    getEl("modal-prod-titulo").textContent = "Editar Produto";
    getEl("prod-id").value        = p.id;
    getEl("prod-nome").value      = p.nome;
    getEl("prod-descricao").value = p.descricao || "";
    getEl("prod-preco").value     = p.preco;
    getEl("prod-imagem").value    = p.imagem   || "";
    getEl("prod-status").value    = p.status;
    preencherSelectCategorias(String(p.categoria_id));
    new bootstrap.Modal(getEl("modalProduto")).show();
}

async function salvarProduto() {
    const id          = getEl("prod-id").value;
    const nome        = getEl("prod-nome").value.trim();
    const preco       = parseFloat(getEl("prod-preco").value);
    const categoria_id = getEl("prod-categoria").value;
    const descricao   = getEl("prod-descricao").value.trim();
    const imagem      = getEl("prod-imagem").value.trim();
    const status      = getEl("prod-status").value;
    if (!nome || !categoria_id || isNaN(preco) || preco <= 0) {
        alert("Preencha nome, categoria e preço válido.");
        return;
    }
    const acao = "salvar_produto";
    const res  = await enviarDados({ acao, id, nome, preco, categoria_id, descricao, imagem, status });
    if (res.sucesso) {
        bootstrap.Modal.getInstance(getEl("modalProduto"))?.hide();
        carregarLoja();
    } else {
        alert(res.mensagem || "Erro ao salvar.");
    }
}

async function excluirProduto(id) {
    if (!confirm("Excluir este produto?")) return;
    const res = await enviarDados({ acao: "excluir_produto", id });
    if (res.sucesso) carregarLoja();
    else alert(res.mensagem || "Erro ao excluir.");
}

// ── INICIALIZAÇÃO ─────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
    // Navegação por abas
    document.querySelectorAll(".nav-side [data-tab]").forEach(link => {
        link.addEventListener("click", e => {
            e.preventDefault();
            ativarTab(link.dataset.tab);
        });
    });

    // Filtros de agendamento
    document.querySelectorAll("[data-filtro]").forEach(btn => {
        btn.addEventListener("click", () => {
            document.querySelectorAll("[data-filtro]").forEach(b => b.classList.remove("active"));
            btn.classList.add("active");
            filtroStatusAtual = btn.dataset.filtro || "";
            carregarAgendamentos(filtroStatusAtual);
        });
    });

    // Inicia na aba agendamentos
    carregarServicos();
    carregarAgendamentos();
});
</script>
</body>
</html>