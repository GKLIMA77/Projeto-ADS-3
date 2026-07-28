// ── ESTADO GLOBAL ─────────────────────────────────────────────────────────────
let agendamentosCache = [];
let servicosCache = [];
let clientesCache = [];
let categoriasCache = [];
let produtosCache = [];
let filtroStatusAtual = "";
// ── HELPERS ───────────────────────────────────────────────────────────────────
function mostrarLoading(ativo) {
    const el = document.getElementById("loading-overlay");
    if (!el)
        return;
    el.classList.toggle("ativo", ativo);
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
    const cores = { confirmado: "success", pendente: "warning text-dark", cancelado: "danger" };
    return `<span class="badge bg-${(_a = cores[status]) !== null && _a !== void 0 ? _a : "secondary"}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}
function badgeLojaStatus(status) {
    var _a;
    const cores = { aprovado: "success", aprovada: "success", pendente: "warning text-dark", cancelado: "danger", cancelada: "danger" };
    return `<span class="badge bg-${(_a = cores[status]) !== null && _a !== void 0 ? _a : "secondary"}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}
function getEl(id) {
    return document.getElementById(id);
}
// ── API ───────────────────────────────────────────────────────────────────────
async function buscarDados(acao, params = {}) {
    var _a;
    try {
        const qs = new URLSearchParams(params).toString();
        const resp = await fetch(`api.php?acao=${acao}${qs ? "&" + qs : ""}`);
        if (!resp.ok)
            throw new Error(`HTTP ${resp.status}`);
        const json = await resp.json();
        if (!json.sucesso)
            throw new Error(json.mensagem);
        return (_a = json.dados) !== null && _a !== void 0 ? _a : null;
    }
    catch (err) {
        console.error(`Erro [${acao}]:`, err);
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
    mostrarLoading(true);
    const [indicadores, ranking] = await Promise.all([
        buscarDados("indicadores"),
        buscarDados("ranking"),
    ]);
    mostrarLoading(false);
    // Edge case: sem dados
    const statTotal = getEl("stat-total");
    const statFat = getEl("stat-fat");
    const statHoje = getEl("stat-hoje");
    const statPend = getEl("stat-pend");
    if (statTotal)
        statTotal.textContent = indicadores ? indicadores.total_agendamentos : "0";
    if (statFat)
        statFat.textContent = indicadores ? formatarMoeda(parseFloat(indicadores.faturamento_total) || 0) : "R$ 0,00";
    if (statHoje)
        statHoje.textContent = indicadores ? indicadores.agendamentos_hoje : "0";
    if (statPend)
        statPend.textContent = indicadores ? indicadores.pendentes : "0";
    const rc = getEl("ranking-container");
    if (!rc)
        return;
    if (!ranking || ranking.length === 0) {
        rc.innerHTML = '<p class="text-secondary">Nenhum dado de ranking disponível.</p>';
        return;
    }
    // Algoritmo de destaque: encontra o campeão com reduce
    const maxAg = ranking.reduce((max, sv) => Math.max(max, Number(sv.total_agendamentos)), 0);
    // .map() — transforma cada serviço em card HTML
    rc.innerHTML = ranking.map((sv) => {
        const total = Number(sv.total_agendamentos);
        const fat = parseFloat(sv.faturamento) || 0;
        const campeao = total === maxAg && maxAg > 0;
        const borda = campeao ? "border:2px solid #f3c800;position:relative;" : "";
        const trofeu = campeao ? '<span style="position:absolute;top:8px;right:10px;font-size:18px;">🏆</span>' : "";
        return `
      <div class="col-md-3">
        <div class="card-stat" style="${borda}">
          ${trofeu}
          <h4 style="font-size:20px;">${sv.nome}</h4>
          <p style="color:#f3c800;">${total} agendamento(s)</p>
          <p>Faturamento: ${formatarMoeda(fat)}</p>
        </div>
      </div>`;
    }).join("");
}
// ── AGENDAMENTOS ──────────────────────────────────────────────────────────────
async function carregarAgendamentos(filtro = "") {
    mostrarLoading(true);
    const dados = await buscarDados("agendamentos", { status: filtro });
    mostrarLoading(false);
    const tbody = getEl("tabela-agendamentos");
    const msgVazio = getEl("msg-sem-agendamentos");
    if (!tbody || !msgVazio)
        return;
    agendamentosCache = dados !== null && dados !== void 0 ? dados : [];
    if (agendamentosCache.length === 0) {
        tbody.innerHTML = "";
        msgVazio.classList.remove("d-none");
        return;
    }
    msgVazio.classList.add("d-none");
    // .filter() — separa por status
    const lista = filtro ? agendamentosCache.filter((a) => a.status === filtro) : agendamentosCache;
    // .reduce() — calcula faturamento dos confirmados e exibe na tela
    const faturamento = lista.reduce((acc, a) => {
        return a.status === "confirmado" ? acc + a.servico_preco : acc;
    }, 0);
    const elFat = getEl("ag-faturamento-total");
    if (elFat)
        elFat.textContent = `Faturamento confirmado: ${formatarMoeda(faturamento)}`;
    // .map() — transforma cada agendamento em linha de tabela
    tbody.innerHTML = lista.map((a) => `
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
function preencherSelectServicos(selecionado = "") {
    const sel = getEl("ag-servico");
    if (!sel)
        return;
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
window["abrirModalNovoAgendamento"] = function () {
    const titulo = getEl("modal-ag-titulo");
    if (titulo)
        titulo.textContent = "Novo Agendamento";
    (getEl("ag-id")).value = "";
    (getEl("ag-nome")).value = "";
    (getEl("ag-datahora")).value = "";
    (getEl("ag-status")).value = "pendente";
    preencherSelectServicos();
    new bootstrap.Modal(getEl("modalAgendamento")).show();
};
window["editarAgendamento"] = function (id) {
    const ag = agendamentosCache.find((a) => a.id === id);
    if (!ag)
        return;
    const titulo = getEl("modal-ag-titulo");
    if (titulo)
        titulo.textContent = "Editar Agendamento";
    (getEl("ag-id")).value = String(ag.id);
    (getEl("ag-nome")).value = ag.cliente_nome;
    (getEl("ag-datahora")).value = ag.data_hora.replace(" ", "T").slice(0, 16);
    (getEl("ag-status")).value = ag.status;
    preencherSelectServicos(ag.servico_nome);
    new bootstrap.Modal(getEl("modalAgendamento")).show();
};
window["salvarAgendamento"] = async function () {
    var _a;
    const id = (getEl("ag-id")).value;
    const nome = (getEl("ag-nome")).value.trim();
    const servicoId = (getEl("ag-servico")).value;
    const dataHora = (getEl("ag-datahora")).value;
    const status = (getEl("ag-status")).value;
    if (!nome || !servicoId || !dataHora) {
        alert("Preencha todos os campos.");
        return;
    }
    const resultado = await enviarDados({ acao: id ? "editar_agendamento" : "criar_agendamento", id, nome, servico_id: servicoId, data_hora: dataHora.replace("T", " ") + ":00", status });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(getEl("modalAgendamento"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarAgendamentos(filtroStatusAtual);
    }
    else {
        alert("Erro: " + resultado.mensagem);
    }
};
window["excluirAgendamento"] = async function (id) {
    if (!confirm("Excluir este agendamento?"))
        return;
    const resultado = await enviarDados({ acao: "excluir_agendamento", id });
    if (resultado.sucesso)
        carregarAgendamentos(filtroStatusAtual);
    else
        alert("Erro: " + resultado.mensagem);
};
// ── CLIENTES ──────────────────────────────────────────────────────────────────
async function carregarClientes() {
    mostrarLoading(true);
    const dados = await buscarDados("clientes");
    mostrarLoading(false);
    const tbody = getEl("tabela-clientes");
    const msgVazio = getEl("msg-sem-clientes");
    if (!tbody || !msgVazio)
        return;
    clientesCache = dados !== null && dados !== void 0 ? dados : [];
    if (clientesCache.length === 0) {
        tbody.innerHTML = "";
        msgVazio.classList.remove("d-none");
        return;
    }
    msgVazio.classList.add("d-none");
    tbody.innerHTML = clientesCache.map((c) => { var _a, _b; return `
    <tr>
      <td>${c.id}</td>
      <td>${c.nome}</td>
      <td>${(_a = c.telefone) !== null && _a !== void 0 ? _a : "—"}</td>
      <td>${(_b = c.email) !== null && _b !== void 0 ? _b : "—"}</td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarCliente(${c.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirCliente(${c.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`; }).join("");
}
window["abrirModalCliente"] = function () {
    const titulo = getEl("modal-cli-titulo");
    if (titulo)
        titulo.textContent = "Novo Cliente";
    (getEl("cli-id")).value = "";
    (getEl("cli-nome")).value = "";
    (getEl("cli-telefone")).value = "";
    (getEl("cli-email")).value = "";
    new bootstrap.Modal(getEl("modalCliente")).show();
};
window["editarCliente"] = function (id) {
    var _a, _b;
    const c = clientesCache.find((x) => x.id === id);
    if (!c)
        return;
    const titulo = getEl("modal-cli-titulo");
    if (titulo)
        titulo.textContent = "Editar Cliente";
    (getEl("cli-id")).value = String(c.id);
    (getEl("cli-nome")).value = c.nome;
    (getEl("cli-telefone")).value = (_a = c.telefone) !== null && _a !== void 0 ? _a : "";
    (getEl("cli-email")).value = (_b = c.email) !== null && _b !== void 0 ? _b : "";
    new bootstrap.Modal(getEl("modalCliente")).show();
};
window["salvarCliente"] = async function () {
    var _a;
    const id = (getEl("cli-id")).value;
    const nome = (getEl("cli-nome")).value.trim();
    const telefone = (getEl("cli-telefone")).value.trim();
    const email = (getEl("cli-email")).value.trim();
    if (!nome) {
        alert("Informe o nome do cliente.");
        return;
    }
    const resultado = await enviarDados({ acao: id ? "editar_cliente" : "criar_cliente", id, nome, telefone, email });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(getEl("modalCliente"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarClientes();
    }
    else {
        alert("Erro: " + resultado.mensagem);
    }
};
window["excluirCliente"] = async function (id) {
    if (!confirm("Excluir este cliente?"))
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
    const tbody = getEl("tabela-servicos");
    const msgVazio = getEl("msg-sem-servicos");
    servicosCache = dados !== null && dados !== void 0 ? dados : [];
    if (!tbody || !msgVazio)
        return;
    if (servicosCache.length === 0) {
        tbody.innerHTML = "";
        msgVazio.classList.remove("d-none");
        return;
    }
    msgVazio.classList.add("d-none");
    tbody.innerHTML = servicosCache.map((s) => `
    <tr>
      <td>${s.id}</td>
      <td>${s.nome}</td>
      <td>${formatarMoeda(parseFloat(s.preco))}</td>
      <td><span class="badge bg-${s.ativo === "1" ? "success" : "secondary"}">${s.ativo === "1" ? "Ativo" : "Inativo"}</span></td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarServico(${s.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirServico(${s.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join("");
}
window["abrirModalServico"] = function () {
    const titulo = getEl("modal-sv-titulo");
    if (titulo)
        titulo.textContent = "Novo Serviço";
    (getEl("sv-id")).value = "";
    (getEl("sv-nome")).value = "";
    (getEl("sv-preco")).value = "";
    (getEl("sv-ativo")).value = "1";
    new bootstrap.Modal(getEl("modalServico")).show();
};
window["editarServico"] = function (id) {
    const s = servicosCache.find((x) => x.id === id);
    if (!s)
        return;
    const titulo = getEl("modal-sv-titulo");
    if (titulo)
        titulo.textContent = "Editar Serviço";
    (getEl("sv-id")).value = String(s.id);
    (getEl("sv-nome")).value = s.nome;
    (getEl("sv-preco")).value = s.preco;
    (getEl("sv-ativo")).value = s.ativo;
    new bootstrap.Modal(getEl("modalServico")).show();
};
window["salvarServico"] = async function () {
    var _a;
    const id = (getEl("sv-id")).value;
    const nome = (getEl("sv-nome")).value.trim();
    const preco = parseFloat((getEl("sv-preco")).value);
    const ativo = (getEl("sv-ativo")).value;
    if (!nome || isNaN(preco) || preco <= 0) {
        alert("Preencha nome e preço válido.");
        return;
    }
    const resultado = await enviarDados({ acao: id ? "editar_servico" : "criar_servico", id, nome, preco, ativo });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(getEl("modalServico"))) === null || _a === void 0 ? void 0 : _a.hide();
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
async function carregarLoja() {
    mostrarLoading(true);
    const [categorias, produtos] = await Promise.all([
        buscarDados("categorias"),
        buscarDados("produtos"),
    ]);
    mostrarLoading(false);
    categoriasCache = categorias !== null && categorias !== void 0 ? categorias : [];
    produtosCache = produtos !== null && produtos !== void 0 ? produtos : [];
    const tabelaCat = getEl("tabela-categorias");
    const vazioCat = getEl("msg-sem-categorias");
    if (tabelaCat && vazioCat) {
        vazioCat.classList.toggle("d-none", categoriasCache.length > 0);
        tabelaCat.innerHTML = categoriasCache.map((c) => `
      <tr>
        <td>${c.id}</td><td>${c.nome}</td><td>${badgeLojaStatus(c.status)}</td>
        <td>
          <button class="btn btn-sm btn-outline-warning me-1" onclick="editarCategoria(${c.id})"><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-outline-danger" onclick="excluirCategoria(${c.id})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`).join("");
    }
    const tabelaProd = getEl("tabela-produtos");
    const vazioProd = getEl("msg-sem-produtos");
    if (tabelaProd && vazioProd) {
        vazioProd.classList.toggle("d-none", produtosCache.length > 0);
        tabelaProd.innerHTML = produtosCache.map((p) => `
      <tr>
        <td><img class="admin-produto-thumb" src="${p.imagem || "https://via.placeholder.com/60"}" alt=""></td>
        <td><strong>${p.nome}</strong><br><small class="text-secondary">${p.descricao || ""}</small></td>
        <td>${p.categoria_nome}</td>
        <td>${formatarMoeda(parseFloat(p.preco))}</td>
        <td>${badgeLojaStatus(p.status)}</td>
        <td>
          <button class="btn btn-sm btn-outline-warning me-1" onclick="editarProduto(${p.id})"><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-outline-danger" onclick="excluirProduto(${p.id})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`).join("");
    }
}
function preencherSelectCategorias(selecionada = "") {
    const select = getEl("prod-categoria");
    if (!select)
        return;
    select.innerHTML = categoriasCache.map((c) => `<option value="${c.id}" ${String(c.id) === selecionada ? "selected" : ""}>${c.nome}</option>`).join("");
}
window["abrirModalCategoria"] = function () {
    const titulo = getEl("modal-cat-titulo");
    if (titulo)
        titulo.textContent = "Nova Categoria";
    (getEl("cat-id")).value = "";
    (getEl("cat-nome")).value = "";
    (getEl("cat-status")).value = "pendente";
    new bootstrap.Modal(getEl("modalCategoria")).show();
};
window["editarCategoria"] = function (id) {
    const c = categoriasCache.find((x) => x.id === id);
    if (!c)
        return;
    const titulo = getEl("modal-cat-titulo");
    if (titulo)
        titulo.textContent = "Editar Categoria";
    (getEl("cat-id")).value = String(c.id);
    (getEl("cat-nome")).value = c.nome;
    (getEl("cat-status")).value = c.status;
    new bootstrap.Modal(getEl("modalCategoria")).show();
};
window["salvarCategoria"] = async function () {
    var _a;
    const id = (getEl("cat-id")).value;
    const nome = (getEl("cat-nome")).value.trim();
    const status = (getEl("cat-status")).value;
    if (!nome) {
        alert("Informe o nome da categoria.");
        return;
    }
    const resultado = await enviarDados({ acao: "salvar_categoria", id, nome, status });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(getEl("modalCategoria"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarLoja();
    }
    else {
        alert("Erro: " + resultado.mensagem);
    }
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
    const titulo = getEl("modal-prod-titulo");
    if (titulo)
        titulo.textContent = "Novo Produto";
    ["prod-id", "prod-nome", "prod-descricao", "prod-imagem", "prod-preco"].forEach((id) => {
        const el = getEl(id);
        if (el)
            el.value = "";
    });
    (getEl("prod-status")).value = "pendente";
    preencherSelectCategorias();
    new bootstrap.Modal(getEl("modalProduto")).show();
};
window["editarProduto"] = function (id) {
    const p = produtosCache.find((x) => x.id === id);
    if (!p)
        return;
    const titulo = getEl("modal-prod-titulo");
    if (titulo)
        titulo.textContent = "Editar Produto";
    (getEl("prod-id")).value = String(p.id);
    (getEl("prod-nome")).value = p.nome;
    (getEl("prod-descricao")).value = p.descricao || "";
    (getEl("prod-preco")).value = p.preco;
    (getEl("prod-imagem")).value = p.imagem || "";
    (getEl("prod-status")).value = p.status;
    preencherSelectCategorias(String(p.categoria_id));
    new bootstrap.Modal(getEl("modalProduto")).show();
};
window["salvarProduto"] = async function () {
    var _a;
    const id = (getEl("prod-id")).value;
    const nome = (getEl("prod-nome")).value.trim();
    const preco = parseFloat((getEl("prod-preco")).value);
    const categoria_id = (getEl("prod-categoria")).value;
    const descricao = (getEl("prod-descricao")).value.trim();
    const imagem = (getEl("prod-imagem")).value.trim();
    const status = (getEl("prod-status")).value;
    if (!nome || !categoria_id || isNaN(preco) || preco <= 0) {
        alert("Preencha nome, categoria e preço válido.");
        return;
    }
    const resultado = await enviarDados({ acao: "salvar_produto", id, nome, preco, categoria_id, descricao, imagem, status });
    if (resultado.sucesso) {
        (_a = bootstrap.Modal.getInstance(getEl("modalProduto"))) === null || _a === void 0 ? void 0 : _a.hide();
        carregarLoja();
    }
    else {
        alert("Erro: " + resultado.mensagem);
    }
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
    const secao = getEl(`tab-${nome}`);
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
    document.querySelectorAll(".nav-side [data-tab]").forEach((link) => {
        link.addEventListener("click", (e) => {
            var _a;
            e.preventDefault();
            ativarTab((_a = link.dataset["tab"]) !== null && _a !== void 0 ? _a : "dashboard");
        });
    });
    document.querySelectorAll("[data-filtro]").forEach((btn) => {
        btn.addEventListener("click", () => {
            var _a;
            document.querySelectorAll("[data-filtro]").forEach((b) => b.classList.remove("active"));
            btn.classList.add("active");
            filtroStatusAtual = (_a = btn.dataset["filtro"]) !== null && _a !== void 0 ? _a : "";
            carregarAgendamentos(filtroStatusAtual);
        });
    });
    carregarDashboard();
    carregarServicos();
});
export {};
