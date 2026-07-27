//  ESTADO GLOBAL 
let agendamentosCache = [];
let servicosCache = [];
let filtroStatusAtual = "";
let categoriasCache = [];
let produtosCache = [];
//  HELPERS
function mostrarLoading(ativo) {
    const el = document.getElementById("loading-overlay");
    if (!el)
        return;
    if (ativo)
        el.classList.add("ativo");
    else
        el.classList.remove("ativo");
}
function formatarMoeda(valor) {
    return valor.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}
function formatarData(dataHora) {
    if (!dataHora)
        return "—";
    const d = new Date(dataHora.replace(" ", "T"));
    if (isNaN(d.getTime()))
        return "—";
    return d.toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
}
function badgeStatus(status) {
    var _a;
    const mapa = {
        confirmado: "success",
        pendente: "warning text-dark",
        cancelado: "danger",
    };
    const cor = (_a = mapa[status]) !== null && _a !== void 0 ? _a : "secondary";
    return `<span class="badge bg-${cor}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}
//  BUSCA GENÉRICA COM async/await + try/catch
async function buscarDados(endpoint, params = {}) {
    var _a;
    try {
        const qs = new URLSearchParams(params).toString();
        const url = `api.php?acao=${endpoint}${qs ? "&" + qs : ""}`;
        const resp = await fetch(url);
        if (!resp.ok)
            throw new Error(`HTTP ${resp.status}`);
        const json = await resp.json();
        if (!json.sucesso)
            throw new Error(json.mensagem);
        return (_a = json.dados) !== null && _a !== void 0 ? _a : null;
    }
    catch (err) {
        console.error(`Erro em [${endpoint}]:`, err);
        return null;
    }
}
async function enviarDados(payload) {
    try {
        const resp = await fetch("api.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });
        return await resp.json();
    }
    catch (err) {
        return { sucesso: false, mensagem: "Erro de conexão: " + err.message };
    }
}
// ── DASHBOARD ─────────────────────────────────────────────────────────────────
async function carregarDashboard() {
    var _a, _b, _c;
    mostrarLoading(true);
    const [indicadores, ranking] = await Promise.all([
        buscarDados("indicadores"),
        buscarDados("ranking"),
    ]);
    mostrarLoading(false);
    // Edge case: banco vazio
    if (!indicadores) {
        document.getElementById("stat-total").textContent = "0";
        document.getElementById("stat-fat").textContent = "R$ 0,00";
        document.getElementById("stat-hoje").textContent = "0";
        document.getElementById("stat-pend").textContent = "0";
        return;
    }
    document.getElementById("stat-total").textContent = (_a = indicadores.total_agendamentos) !== null && _a !== void 0 ? _a : "0";
    document.getElementById("stat-fat").textContent = formatarMoeda(parseFloat(indicadores.faturamento_total) || 0);
    document.getElementById("stat-hoje").textContent = (_b = indicadores.agendamentos_hoje) !== null && _b !== void 0 ? _b : "0";
    document.getElementById("stat-pend").textContent = (_c = indicadores.pendentes) !== null && _c !== void 0 ? _c : "0";
    const rc = document.getElementById("ranking-container");
    if (!rc)
        return;
    if (!ranking || ranking.length === 0) {
        rc.innerHTML = '<p class="text-secondary">Nenhum dado de ranking disponível.</p>';
        return;
    }
    // .map() — transforma array do banco em HTML estilizado
    rc.innerHTML = ranking
        .map((sv) => `
    <div class="col-md-3">
      <div class="card-stat">
        <h4 style="font-size:22px;">${sv.nome}</h4>
        <p style="color:#f3c800;">${sv.total_agendamentos} agendamento(s)</p>
        <p>Faturamento: ${formatarMoeda(parseFloat(sv.faturamento) || 0)}</p>
      </div>
    </div>`)
        .join("");
}
// ── AGENDAMENTOS ──────────────────────────────────────────────────────────────
async function carregarAgendamentos(filtro = "") {
    mostrarLoading(true);
    const dados = await buscarDados("agendamentos", { status: filtro });
    mostrarLoading(false);
    const tbody = document.getElementById("tabela-agendamentos");
    const msgVazio = document.getElementById("msg-sem-agendamentos");
    if (!tbody || !msgVazio)
        return;
    agendamentosCache = dados !== null && dados !== void 0 ? dados : [];
    // Edge case: lista vazia
    if (agendamentosCache.length === 0) {
        tbody.innerHTML = "";
        msgVazio.classList.remove("d-none");
        return;
    }
    msgVazio.classList.add("d-none");
    // .filter() — segmenta por status (regra de negócio)
    const lista = filtro
        ? agendamentosCache.filter((a) => a.status === filtro)
        : agendamentosCache;
    // .reduce() — faturamento acumulado
    const faturamento = lista.reduce((acc, a) => {
        return a.status === "confirmado" ? acc + parseFloat(a.servico_preco) : acc;
    }, 0);
    console.log("Faturamento filtrado:", formatarMoeda(faturamento));
    tbody.innerHTML = lista
        .map((a) => `
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
    </tr>`)
        .join("");
}
window["abrirModalNovoAgendamento"] = function () {
    document.getElementById("modal-ag-titulo").textContent = "Novo Agendamento";
    document.getElementById("ag-id").value = "";
    document.getElementById("ag-nome").value = "";
    document.getElementById("ag-datahora").value = "";
    document.getElementById("ag-status").value = "pendente";
    preencherSelectServicos();
    new bootstrap.Modal(document.getElementById("modalAgendamento")).show();
};
window["editarAgendamento"] = function (id) {
    const ag = agendamentosCache.find((a) => a.id === id);
    if (!ag)
        return;
    document.getElementById("modal-ag-titulo").textContent = "Editar Agendamento";
    document.getElementById("ag-id").value = String(ag.id);
    document.getElementById("ag-nome").value = ag.cliente_nome;
    document.getElementById("ag-datahora").value = ag.data_hora.replace(" ", "T").slice(0, 16);
    document.getElementById("ag-status").value = ag.status;
    preencherSelectServicos(ag.servico_nome);
    new bootstrap.Modal(document.getElementById("modalAgendamento")).show();
};
window["salvarAgendamento"] = async function () {
    var _a;
    const id = document.getElementById("ag-id").value;
    const nome = document.getElementById("ag-nome").value.trim();
    const servicoId = document.getElementById("ag-servico").value;
    const dataHora = document.getElementById("ag-datahora").value;
    const status = document.getElementById("ag-status").value;
    if (!nome || !servicoId || !dataHora) {
        alert("Preencha todos os campos.");
        return;
    }
    const resultado = await enviarDados({ acao: id ? "editar_agendamento" : "criar_agendamento", id, nome, servico_id: servicoId, data_hora: dataHora.replace("T", " ") + ":00", status });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(document.getElementById("modalAgendamento"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarAgendamentos(filtroStatusAtual);
    }
    else {
        alert("Erro: " + resultado.mensagem);
    }
};
window["excluirAgendamento"] = async function (id) {
    if (!confirm("Tem certeza que deseja excluir este agendamento? Esta ação não pode ser desfeita."))
        return;
    const resultado = await enviarDados({ acao: "excluir_agendamento", id });
    if (resultado.sucesso)
        carregarAgendamentos(filtroStatusAtual);
    else
        alert("Erro ao excluir: " + resultado.mensagem);
};
// ── CLIENTES ──────────────────────────────────────────────────────────────────
let clientesCache = [];
async function carregarClientes() {
    mostrarLoading(true);
    const dados = await buscarDados("clientes");
    mostrarLoading(false);
    const tbody = document.getElementById("tabela-clientes");
    const msgVazio = document.getElementById("msg-sem-clientes");
    if (!tbody || !msgVazio)
        return;
    clientesCache = dados !== null && dados !== void 0 ? dados : [];
    if (clientesCache.length === 0) {
        tbody.innerHTML = "";
        msgVazio.classList.remove("d-none");
        return;
    }
    msgVazio.classList.add("d-none");
    tbody.innerHTML = clientesCache
        .map((c) => { var _a, _b; return `
    <tr>
      <td>${c.id}</td>
      <td>${c.nome}</td>
      <td>${(_a = c.telefone) !== null && _a !== void 0 ? _a : "—"}</td>
      <td>${(_b = c.email) !== null && _b !== void 0 ? _b : "—"}</td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarCliente(${c.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirCliente(${c.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`; })
        .join("");
}
window["abrirModalCliente"] = function () {
    document.getElementById("modal-cli-titulo").textContent = "Novo Cliente";
    document.getElementById("cli-id").value = "";
    document.getElementById("cli-nome").value = "";
    document.getElementById("cli-telefone").value = "";
    document.getElementById("cli-email").value = "";
    new bootstrap.Modal(document.getElementById("modalCliente")).show();
};
window["editarCliente"] = function (id) {
    var _a, _b;
    const c = clientesCache.find((x) => x.id === id);
    if (!c)
        return;
    document.getElementById("modal-cli-titulo").textContent = "Editar Cliente";
    document.getElementById("cli-id").value = String(c.id);
    document.getElementById("cli-nome").value = c.nome;
    document.getElementById("cli-telefone").value = (_a = c.telefone) !== null && _a !== void 0 ? _a : "";
    document.getElementById("cli-email").value = (_b = c.email) !== null && _b !== void 0 ? _b : "";
    new bootstrap.Modal(document.getElementById("modalCliente")).show();
};
window["salvarCliente"] = async function () {
    var _a;
    const id = document.getElementById("cli-id").value;
    const nome = document.getElementById("cli-nome").value.trim();
    const telefone = document.getElementById("cli-telefone").value.trim();
    const email = document.getElementById("cli-email").value.trim();
    if (!nome) {
        alert("Informe o nome do cliente.");
        return;
    }
    const resultado = await enviarDados({ acao: id ? "editar_cliente" : "criar_cliente", id, nome, telefone, email });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(document.getElementById("modalCliente"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarClientes();
    }
    else {
        alert("Erro: " + resultado.mensagem);
    }
};
window["excluirCliente"] = async function (id) {
    if (!confirm("Excluir este cliente? Se ele tiver agendamentos vinculados, a exclusão não será permitida."))
        return;
    const resultado = await enviarDados({ acao: "excluir_cliente", id });
    if (resultado.sucesso)
        carregarClientes();
    else
        alert("Não foi possível excluir: " + resultado.mensagem);
};
// ── SERVIÇOS ──────────────────────────────────────────────────────────────────
async function carregarServicos() {
    mostrarLoading(true);
    const dados = await buscarDados("servicos");
    mostrarLoading(false);
    const tbody = document.getElementById("tabela-servicos");
    const msgVazio = document.getElementById("msg-sem-servicos");
    if (!tbody || !msgVazio)
        return;
    servicosCache = dados !== null && dados !== void 0 ? dados : [];
    if (servicosCache.length === 0) {
        tbody.innerHTML = "";
        msgVazio.classList.remove("d-none");
        return;
    }
    msgVazio.classList.add("d-none");
    tbody.innerHTML = servicosCache
        .map((s) => `
    <tr>
      <td>${s.id}</td>
      <td>${s.nome}</td>
      <td>${formatarMoeda(parseFloat(s.preco))}</td>
      <td><span class="badge bg-${s.ativo === "1" ? "success" : "secondary"}">${s.ativo === "1" ? "Ativo" : "Inativo"}</span></td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarServico(${s.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirServico(${s.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`)
        .join("");
}
function preencherSelectServicos(selecionado = "") {
    const sel = document.getElementById("ag-servico");
    sel.innerHTML = '<option value="">Selecione</option>';
    servicosCache.forEach((s) => {
        const opt = document.createElement("option");
        opt.value = String(s.id);
        opt.textContent = `${s.nome} — ${formatarMoeda(parseFloat(s.preco))}`;
        if (s.nome === selecionado)
            opt.selected = true;
        sel.appendChild(opt);
    });
}
window["abrirModalServico"] = function () {
    document.getElementById("modal-sv-titulo").textContent = "Novo Serviço";
    document.getElementById("sv-id").value = "";
    document.getElementById("sv-nome").value = "";
    document.getElementById("sv-preco").value = "";
    document.getElementById("sv-ativo").value = "1";
    new bootstrap.Modal(document.getElementById("modalServico")).show();
};
window["editarServico"] = function (id) {
    const s = servicosCache.find((x) => x.id === id);
    if (!s)
        return;
    document.getElementById("modal-sv-titulo").textContent = "Editar Serviço";
    document.getElementById("sv-id").value = String(s.id);
    document.getElementById("sv-nome").value = s.nome;
    document.getElementById("sv-preco").value = s.preco;
    document.getElementById("sv-ativo").value = s.ativo;
    new bootstrap.Modal(document.getElementById("modalServico")).show();
};
window["salvarServico"] = async function () {
    var _a;
    const id = document.getElementById("sv-id").value;
    const nome = document.getElementById("sv-nome").value.trim();
    const preco = parseFloat(document.getElementById("sv-preco").value);
    const ativo = document.getElementById("sv-ativo").value;
    if (!nome || isNaN(preco) || preco <= 0) {
        alert("Preencha nome e preço válido.");
        return;
    }
    const resultado = await enviarDados({ acao: id ? "editar_servico" : "criar_servico", id, nome, preco, ativo });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(document.getElementById("modalServico"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarServicos();
    }
    else {
        alert("Erro: " + resultado.mensagem);
    }
};
window["excluirServico"] = async function (id) {
    if (!confirm("Excluir este serviço? Só é possível excluir serviços sem agendamentos vinculados."))
        return;
    const resultado = await enviarDados({ acao: "excluir_servico", id });
    if (resultado.sucesso)
        carregarServicos();
    else
        alert("Não foi possível excluir: " + resultado.mensagem);
};
// ── LOJA: CATEGORIAS E PRODUTOS ──────────────────────────────────────────────
function badgeLojaStatus(status) {
    var _a;
    const mapa = { aprovado: "success", aprovada: "success", pendente: "warning text-dark", cancelado: "danger", cancelada: "danger" };
    return `<span class="badge bg-${(_a = mapa[status]) !== null && _a !== void 0 ? _a : "secondary"}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}
async function carregarLoja() {
    mostrarLoading(true);
    const [categorias, produtos] = await Promise.all([buscarDados("categorias"), buscarDados("produtos")]);
    mostrarLoading(false);
    categoriasCache = categorias !== null && categorias !== void 0 ? categorias : [];
    produtosCache = produtos !== null && produtos !== void 0 ? produtos : [];
    const tabelaCategorias = document.getElementById("tabela-categorias");
    const vazioCategorias = document.getElementById("msg-sem-categorias");
    if (tabelaCategorias && vazioCategorias) {
        vazioCategorias.classList.toggle("d-none", categoriasCache.length > 0);
        tabelaCategorias.innerHTML = categoriasCache.map((c) => `<tr><td>${c.id}</td><td>${c.nome}</td><td>${badgeLojaStatus(c.status)}</td><td><button class="btn btn-sm btn-outline-warning me-1" onclick="editarCategoria(${c.id})"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-outline-danger" onclick="excluirCategoria(${c.id})"><i class="fas fa-trash"></i></button></td></tr>`).join("");
    }
    const tabelaProdutos = document.getElementById("tabela-produtos");
    const vazioProdutos = document.getElementById("msg-sem-produtos");
    if (tabelaProdutos && vazioProdutos) {
        vazioProdutos.classList.toggle("d-none", produtosCache.length > 0);
        tabelaProdutos.innerHTML = produtosCache.map((p) => `<tr><td><img class="admin-produto-thumb" src="${p.imagem || "https://images.unsplash.com/photo-1622287162716-f311baa1a2b8?q=80&w=200"}" alt=""></td><td><strong>${p.nome}</strong><br><small class="text-secondary">${p.descricao || ""}</small></td><td>${p.categoria_nome}</td><td>${formatarMoeda(parseFloat(p.preco))}</td><td>${badgeLojaStatus(p.status)}</td><td><button class="btn btn-sm btn-outline-warning me-1" onclick="editarProduto(${p.id})"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-outline-danger" onclick="excluirProduto(${p.id})"><i class="fas fa-trash"></i></button></td></tr>`).join("");
    }
}
function preencherSelectCategorias(selecionada = "") {
    const select = document.getElementById("prod-categoria");
    select.innerHTML = categoriasCache.map((c) => `<option value="${c.id}" ${String(c.id) === selecionada ? "selected" : ""}>${c.nome}</option>`).join("");
}
window["abrirModalCategoria"] = function () {
    document.getElementById("modal-cat-titulo").textContent = "Nova Categoria";
    document.getElementById("cat-id").value = "";
    document.getElementById("cat-nome").value = "";
    document.getElementById("cat-status").value = "pendente";
    new bootstrap.Modal(document.getElementById("modalCategoria")).show();
};
window["editarCategoria"] = function (id) {
    const categoria = categoriasCache.find((c) => c.id === id);
    if (!categoria)
        return;
    document.getElementById("modal-cat-titulo").textContent = "Editar Categoria";
    document.getElementById("cat-id").value = String(categoria.id);
    document.getElementById("cat-nome").value = categoria.nome;
    document.getElementById("cat-status").value = categoria.status;
    new bootstrap.Modal(document.getElementById("modalCategoria")).show();
};
window["salvarCategoria"] = async function () {
    var _a;
    const id = document.getElementById("cat-id").value;
    const nome = document.getElementById("cat-nome").value.trim();
    const status = document.getElementById("cat-status").value;
    if (!nome) {
        alert("Informe o nome da categoria.");
        return;
    }
    const resultado = await enviarDados({ acao: "salvar_categoria", id, nome, status });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(document.getElementById("modalCategoria"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarLoja();
    }
    else
        alert("Erro: " + resultado.mensagem);
};
window["excluirCategoria"] = async function (id) {
    if (!confirm("Excluir esta categoria? Ela precisa estar sem produtos."))
        return;
    const resultado = await enviarDados({ acao: "excluir_categoria", id });
    if (resultado.sucesso)
        carregarLoja();
    else
        alert(resultado.mensagem);
};
window["abrirModalProduto"] = function () {
    document.getElementById("modal-prod-titulo").textContent = "Novo Produto";
    ["prod-id", "prod-nome", "prod-descricao", "prod-imagem", "prod-preco"].forEach((id) => document.getElementById(id).value = "");
    document.getElementById("prod-status").value = "pendente";
    preencherSelectCategorias();
    new bootstrap.Modal(document.getElementById("modalProduto")).show();
};
window["editarProduto"] = function (id) {
    const produto = produtosCache.find((p) => p.id === id);
    if (!produto)
        return;
    document.getElementById("modal-prod-titulo").textContent = "Editar Produto";
    document.getElementById("prod-id").value = String(produto.id);
    document.getElementById("prod-nome").value = produto.nome;
    document.getElementById("prod-descricao").value = produto.descricao || "";
    document.getElementById("prod-preco").value = produto.preco;
    document.getElementById("prod-imagem").value = produto.imagem || "";
    document.getElementById("prod-status").value = produto.status;
    preencherSelectCategorias(String(produto.categoria_id));
    new bootstrap.Modal(document.getElementById("modalProduto")).show();
};
window["salvarProduto"] = async function () {
    var _a;
    const id = document.getElementById("prod-id").value;
    const nome = document.getElementById("prod-nome").value.trim();
    const preco = parseFloat(document.getElementById("prod-preco").value);
    const categoria_id = document.getElementById("prod-categoria").value;
    const descricao = document.getElementById("prod-descricao").value.trim();
    const imagem = document.getElementById("prod-imagem").value.trim();
    const status = document.getElementById("prod-status").value;
    if (!nome || !categoria_id || isNaN(preco) || preco <= 0) {
        alert("Preencha nome, categoria e preço válido.");
        return;
    }
    const resultado = await enviarDados({ acao: "salvar_produto", id, nome, preco, categoria_id, descricao, imagem, status });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(document.getElementById("modalProduto"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarLoja();
    }
    else
        alert("Erro: " + resultado.mensagem);
};
window["excluirProduto"] = async function (id) {
    if (!confirm("Excluir este produto definitivamente?"))
        return;
    const resultado = await enviarDados({ acao: "excluir_produto", id });
    if (resultado.sucesso)
        carregarLoja();
    else
        alert(resultado.mensagem);
};
// ── NAVEGAÇÃO POR ABAS ────────────────────────────────────────────────────────
function ativarTab(nome) {
    document.querySelectorAll(".tab-section").forEach((s) => s.classList.add("d-none"));
    document.querySelectorAll(".nav-side .nav-link").forEach((l) => l.classList.remove("active"));
    const secao = document.getElementById(`tab-${nome}`);
    const link = document.querySelector(`.nav-side [data-tab="${nome}"]`);
    if (secao)
        secao.classList.remove("d-none");
    if (link)
        link.classList.add("active");
    if (nome === "dashboard")
        carregarDashboard();
    if (nome === "agendamentos") {
        carregarServicos();
        carregarAgendamentos();
    }
    if (nome === "clientes")
        carregarClientes();
    if (nome === "servicos")
        carregarServicos();
    if (nome === "loja")
        carregarLoja();
}
// ── INICIALIZAÇÃO ─────────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
    // Navegação lateral
    document.querySelectorAll(".nav-side [data-tab]").forEach((link) => {
        link.addEventListener("click", (e) => {
            var _a;
            e.preventDefault();
            ativarTab((_a = link.dataset["tab"]) !== null && _a !== void 0 ? _a : "dashboard");
        });
    });
    // Filtros de status
    document.querySelectorAll("[data-filtro]").forEach((btn) => {
        btn.addEventListener("click", () => {
            var _a;
            document.querySelectorAll("[data-filtro]").forEach((b) => b.classList.remove("active"));
            btn.classList.add("active");
            filtroStatusAtual = (_a = btn.dataset["filtro"]) !== null && _a !== void 0 ? _a : "";
            carregarAgendamentos(filtroStatusAtual);
        });
    });
    // Carga inicial
    carregarDashboard();
    carregarServicos(); // pré-carrega para o select de agendamentos
});
export {};
