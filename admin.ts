
declare const bootstrap: any;

interface Agendamento {
  id: number;
  cliente_nome: string;
  servico_nome: string;
  servico_preco: string;
  data_hora: string;
  status: "pendente" | "confirmado" | "cancelado";
  criado_em: string;
}

interface Cliente {
  id: number;
  nome: string;
  telefone: string | null;
  email: string | null;
}

interface Servico {
  id: number;
  nome: string;
  preco: string;
  ativo: "0" | "1";
}

interface Categoria {
  id: number;
  nome: string;
  status: "pendente" | "aprovada" | "cancelada";
}

interface Produto {
  id: number;
  categoria_id: string;
  categoria_nome: string;
  nome: string;
  descricao: string;
  preco: string;
  imagem: string;
  status: "pendente" | "aprovado" | "cancelado";
}

interface Indicadores {
  total_agendamentos: string;
  faturamento_total: string;
  agendamentos_hoje: string;
  pendentes: string;
}

interface RankingServico {
  nome: string;
  preco: string;
  total_agendamentos: string;
  faturamento: string;
}

interface RespostaApi<T> {
  sucesso: boolean;
  mensagem: string;
  dados?: T;
}

// ── ESTADO GLOBAL ─────────────────────────────────────────────────────────────

let agendamentosCache: Agendamento[] = [];
let servicosCache: Servico[] = [];
let filtroStatusAtual: string = "";
let categoriasCache: Categoria[] = [];
let produtosCache: Produto[] = [];

// ── HELPERS ───────────────────────────────────────────────────────────────────

function mostrarLoading(ativo: boolean): void {
  const el = document.getElementById("loading-overlay");
  if (!el) return;
  if (ativo) el.classList.add("ativo");
  else el.classList.remove("ativo");
}

function formatarMoeda(valor: number): string {
  return valor.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function formatarData(dataHora: string): string {
  if (!dataHora) return "—";
  const d = new Date(dataHora.replace(" ", "T"));
  if (isNaN(d.getTime())) return "—";
  return d.toLocaleString("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
}

function badgeStatus(status: string): string {
  const mapa: Record<string, string> = {
    confirmado: "success",
    pendente: "warning text-dark",
    cancelado: "danger",
  };
  const cor = mapa[status] ?? "secondary";
  return `<span class="badge bg-${cor}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

// ── BUSCA GENÉRICA COM async/await + try/catch ────────────────────────────────

async function buscarDados<T>(endpoint: string, params: Record<string, string> = {}): Promise<T | null> {
  try {
    const qs = new URLSearchParams(params).toString();
    const url = `api.php?acao=${endpoint}${qs ? "&" + qs : ""}`;
    const resp = await fetch(url);

    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

    const json: RespostaApi<T> = await resp.json();
    if (!json.sucesso) throw new Error(json.mensagem);

    return json.dados ?? null;
  } catch (err) {
    console.error(`Erro em [${endpoint}]:`, err);
    return null;
  }
}

async function enviarDados<T>(payload: Record<string, unknown>): Promise<RespostaApi<T>> {
  try {
    const resp = await fetch("api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    return await resp.json();
  } catch (err) {
    return { sucesso: false, mensagem: "Erro de conexão: " + (err as Error).message };
  }
}

// ── DASHBOARD ─────────────────────────────────────────────────────────────────

async function carregarDashboard(): Promise<void> {
  mostrarLoading(true);

  const [indicadores, ranking] = await Promise.all([
    buscarDados<Indicadores>("indicadores"),
    buscarDados<RankingServico[]>("ranking"),
  ]);

  mostrarLoading(false);

  // Edge case: banco vazio
  if (!indicadores) {
    document.getElementById("stat-total")!.textContent = "0";
    document.getElementById("stat-fat")!.textContent   = "R$ 0,00";
    document.getElementById("stat-hoje")!.textContent  = "0";
    document.getElementById("stat-pend")!.textContent  = "0";
    return;
  }

  document.getElementById("stat-total")!.textContent = indicadores.total_agendamentos ?? "0";
  document.getElementById("stat-fat")!.textContent   = formatarMoeda(parseFloat(indicadores.faturamento_total) || 0);
  document.getElementById("stat-hoje")!.textContent  = indicadores.agendamentos_hoje ?? "0";
  document.getElementById("stat-pend")!.textContent  = indicadores.pendentes ?? "0";

  const rc = document.getElementById("ranking-container");
  if (!rc) return;

  if (!ranking || ranking.length === 0) {
    rc.innerHTML = '<p class="text-secondary">Nenhum dado de ranking disponível.</p>';
    return;
  }

  // .map() — transforma array do banco em HTML estilizado
  rc.innerHTML = ranking
    .map(
      (sv) => `
    <div class="col-md-3">
      <div class="card-stat">
        <h4 style="font-size:22px;">${sv.nome}</h4>
        <p style="color:#f3c800;">${sv.total_agendamentos} agendamento(s)</p>
        <p>Faturamento: ${formatarMoeda(parseFloat(sv.faturamento) || 0)}</p>
      </div>
    </div>`
    )
    .join("");
}

// ── AGENDAMENTOS ──────────────────────────────────────────────────────────────

async function carregarAgendamentos(filtro: string = ""): Promise<void> {
  mostrarLoading(true);
  const dados = await buscarDados<Agendamento[]>("agendamentos", { status: filtro });
  mostrarLoading(false);

  const tbody = document.getElementById("tabela-agendamentos");
  const msgVazio = document.getElementById("msg-sem-agendamentos");
  if (!tbody || !msgVazio) return;

  agendamentosCache = dados ?? [];

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
    .map(
      (a) => `
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
    </tr>`
    )
    .join("");
}

// Declarar globais para o HTML inline
declare function abrirModalNovoAgendamento(): void;
declare function salvarAgendamento(): void;
declare function editarAgendamento(id: number): void;
declare function excluirAgendamento(id: number): void;

(window as unknown as Record<string, unknown>)["abrirModalNovoAgendamento"] = function (): void {
  document.getElementById("modal-ag-titulo")!.textContent = "Novo Agendamento";
  (document.getElementById("ag-id") as HTMLInputElement).value = "";
  (document.getElementById("ag-nome") as HTMLInputElement).value = "";
  (document.getElementById("ag-datahora") as HTMLInputElement).value = "";
  (document.getElementById("ag-status") as HTMLSelectElement).value = "pendente";
  preencherSelectServicos();
  new bootstrap.Modal(document.getElementById("modalAgendamento")!).show();
};

(window as unknown as Record<string, unknown>)["editarAgendamento"] = function (id: number): void {
  const ag = agendamentosCache.find((a) => a.id === id);
  if (!ag) return;
  document.getElementById("modal-ag-titulo")!.textContent = "Editar Agendamento";
  (document.getElementById("ag-id") as HTMLInputElement).value = String(ag.id);
  (document.getElementById("ag-nome") as HTMLInputElement).value = ag.cliente_nome;
  (document.getElementById("ag-datahora") as HTMLInputElement).value = ag.data_hora.replace(" ", "T").slice(0, 16);
  (document.getElementById("ag-status") as HTMLSelectElement).value = ag.status;
  preencherSelectServicos(ag.servico_nome);
  new bootstrap.Modal(document.getElementById("modalAgendamento")!).show();
};

(window as unknown as Record<string, unknown>)["salvarAgendamento"] = async function (): Promise<void> {
  const id       = (document.getElementById("ag-id") as HTMLInputElement).value;
  const nome     = (document.getElementById("ag-nome") as HTMLInputElement).value.trim();
  const servicoId= (document.getElementById("ag-servico") as HTMLSelectElement).value;
  const dataHora = (document.getElementById("ag-datahora") as HTMLInputElement).value;
  const status   = (document.getElementById("ag-status") as HTMLSelectElement).value;

  if (!nome || !servicoId || !dataHora) { alert("Preencha todos os campos."); return; }

  const resultado = await enviarDados({ acao: id ? "editar_agendamento" : "criar_agendamento", id, nome, servico_id: servicoId, data_hora: dataHora.replace("T", " ") + ":00", status });

  if (resultado.sucesso) {
    bootstrap.Modal.getInstance(document.getElementById("modalAgendamento")!)?.hide();
    carregarAgendamentos(filtroStatusAtual);
  } else {
    alert("Erro: " + resultado.mensagem);
  }
};

(window as unknown as Record<string, unknown>)["excluirAgendamento"] = async function (id: number): Promise<void> {
  if (!confirm("Tem certeza que deseja excluir este agendamento? Esta ação não pode ser desfeita.")) return;
  const resultado = await enviarDados({ acao: "excluir_agendamento", id });
  if (resultado.sucesso) carregarAgendamentos(filtroStatusAtual);
  else alert("Erro ao excluir: " + resultado.mensagem);
};

// ── CLIENTES ──────────────────────────────────────────────────────────────────

let clientesCache: Cliente[] = [];

async function carregarClientes(): Promise<void> {
  mostrarLoading(true);
  const dados = await buscarDados<Cliente[]>("clientes");
  mostrarLoading(false);

  const tbody   = document.getElementById("tabela-clientes");
  const msgVazio = document.getElementById("msg-sem-clientes");
  if (!tbody || !msgVazio) return;

  clientesCache = dados ?? [];

  if (clientesCache.length === 0) {
    tbody.innerHTML = "";
    msgVazio.classList.remove("d-none");
    return;
  }

  msgVazio.classList.add("d-none");

  tbody.innerHTML = clientesCache
    .map(
      (c) => `
    <tr>
      <td>${c.id}</td>
      <td>${c.nome}</td>
      <td>${c.telefone ?? "—"}</td>
      <td>${c.email ?? "—"}</td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarCliente(${c.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirCliente(${c.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`
    )
    .join("");
}

(window as unknown as Record<string, unknown>)["abrirModalCliente"] = function (): void {
  document.getElementById("modal-cli-titulo")!.textContent = "Novo Cliente";
  (document.getElementById("cli-id") as HTMLInputElement).value = "";
  (document.getElementById("cli-nome") as HTMLInputElement).value = "";
  (document.getElementById("cli-telefone") as HTMLInputElement).value = "";
  (document.getElementById("cli-email") as HTMLInputElement).value = "";
  new bootstrap.Modal(document.getElementById("modalCliente")!).show();
};

(window as unknown as Record<string, unknown>)["editarCliente"] = function (id: number): void {
  const c = clientesCache.find((x) => x.id === id);
  if (!c) return;
  document.getElementById("modal-cli-titulo")!.textContent = "Editar Cliente";
  (document.getElementById("cli-id") as HTMLInputElement).value       = String(c.id);
  (document.getElementById("cli-nome") as HTMLInputElement).value     = c.nome;
  (document.getElementById("cli-telefone") as HTMLInputElement).value = c.telefone ?? "";
  (document.getElementById("cli-email") as HTMLInputElement).value    = c.email ?? "";
  new bootstrap.Modal(document.getElementById("modalCliente")!).show();
};

(window as unknown as Record<string, unknown>)["salvarCliente"] = async function (): Promise<void> {
  const id       = (document.getElementById("cli-id") as HTMLInputElement).value;
  const nome     = (document.getElementById("cli-nome") as HTMLInputElement).value.trim();
  const telefone = (document.getElementById("cli-telefone") as HTMLInputElement).value.trim();
  const email    = (document.getElementById("cli-email") as HTMLInputElement).value.trim();

  if (!nome) { alert("Informe o nome do cliente."); return; }

  const resultado = await enviarDados({ acao: id ? "editar_cliente" : "criar_cliente", id, nome, telefone, email });
  if (resultado.sucesso) {
    bootstrap.Modal.getInstance(document.getElementById("modalCliente")!)?.hide();
    carregarClientes();
  } else {
    alert("Erro: " + resultado.mensagem);
  }
};

(window as unknown as Record<string, unknown>)["excluirCliente"] = async function (id: number): Promise<void> {
  if (!confirm("Excluir este cliente? Se ele tiver agendamentos vinculados, a exclusão não será permitida.")) return;
  const resultado = await enviarDados({ acao: "excluir_cliente", id });
  if (resultado.sucesso) carregarClientes();
  else alert("Não foi possível excluir: " + resultado.mensagem);
};

// ── SERVIÇOS ──────────────────────────────────────────────────────────────────

async function carregarServicos(): Promise<void> {
  mostrarLoading(true);
  const dados = await buscarDados<Servico[]>("servicos");
  mostrarLoading(false);

  const tbody   = document.getElementById("tabela-servicos");
  const msgVazio = document.getElementById("msg-sem-servicos");
  if (!tbody || !msgVazio) return;

  servicosCache = dados ?? [];

  if (servicosCache.length === 0) {
    tbody.innerHTML = "";
    msgVazio.classList.remove("d-none");
    return;
  }

  msgVazio.classList.add("d-none");

  tbody.innerHTML = servicosCache
    .map(
      (s) => `
    <tr>
      <td>${s.id}</td>
      <td>${s.nome}</td>
      <td>${formatarMoeda(parseFloat(s.preco))}</td>
      <td><span class="badge bg-${s.ativo === "1" ? "success" : "secondary"}">${s.ativo === "1" ? "Ativo" : "Inativo"}</span></td>
      <td>
        <button class="btn btn-sm btn-outline-warning me-1" onclick="editarServico(${s.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="excluirServico(${s.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`
    )
    .join("");
}

function preencherSelectServicos(selecionado: string = ""): void {
  const sel = document.getElementById("ag-servico") as HTMLSelectElement;
  sel.innerHTML = '<option value="">Selecione</option>';
  servicosCache.forEach((s) => {
    const opt = document.createElement("option");
    opt.value = String(s.id);
    opt.textContent = `${s.nome} — ${formatarMoeda(parseFloat(s.preco))}`;
    if (s.nome === selecionado) opt.selected = true;
    sel.appendChild(opt);
  });
}

(window as unknown as Record<string, unknown>)["abrirModalServico"] = function (): void {
  document.getElementById("modal-sv-titulo")!.textContent = "Novo Serviço";
  (document.getElementById("sv-id") as HTMLInputElement).value = "";
  (document.getElementById("sv-nome") as HTMLInputElement).value = "";
  (document.getElementById("sv-preco") as HTMLInputElement).value = "";
  (document.getElementById("sv-ativo") as HTMLSelectElement).value = "1";
  new bootstrap.Modal(document.getElementById("modalServico")!).show();
};

(window as unknown as Record<string, unknown>)["editarServico"] = function (id: number): void {
  const s = servicosCache.find((x) => x.id === id);
  if (!s) return;
  document.getElementById("modal-sv-titulo")!.textContent = "Editar Serviço";
  (document.getElementById("sv-id") as HTMLInputElement).value     = String(s.id);
  (document.getElementById("sv-nome") as HTMLInputElement).value   = s.nome;
  (document.getElementById("sv-preco") as HTMLInputElement).value  = s.preco;
  (document.getElementById("sv-ativo") as HTMLSelectElement).value = s.ativo;
  new bootstrap.Modal(document.getElementById("modalServico")!).show();
};

(window as unknown as Record<string, unknown>)["salvarServico"] = async function (): Promise<void> {
  const id    = (document.getElementById("sv-id") as HTMLInputElement).value;
  const nome  = (document.getElementById("sv-nome") as HTMLInputElement).value.trim();
  const preco = parseFloat((document.getElementById("sv-preco") as HTMLInputElement).value);
  const ativo = (document.getElementById("sv-ativo") as HTMLSelectElement).value;

  if (!nome || isNaN(preco) || preco <= 0) { alert("Preencha nome e preço válido."); return; }

  const resultado = await enviarDados({ acao: id ? "editar_servico" : "criar_servico", id, nome, preco, ativo });
  if (resultado.sucesso) {
    bootstrap.Modal.getInstance(document.getElementById("modalServico")!)?.hide();
    carregarServicos();
  } else {
    alert("Erro: " + resultado.mensagem);
  }
};

(window as unknown as Record<string, unknown>)["excluirServico"] = async function (id: number): Promise<void> {
  if (!confirm("Excluir este serviço? Só é possível excluir serviços sem agendamentos vinculados.")) return;
  const resultado = await enviarDados({ acao: "excluir_servico", id });
  if (resultado.sucesso) carregarServicos();
  else alert("Não foi possível excluir: " + resultado.mensagem);
};

// ── LOJA: CATEGORIAS E PRODUTOS ──────────────────────────────────────────────

function badgeLojaStatus(status: string): string {
  const mapa: Record<string, string> = { aprovado: "success", aprovada: "success", pendente: "warning text-dark", cancelado: "danger", cancelada: "danger" };
  return `<span class="badge bg-${mapa[status] ?? "secondary"}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}

async function carregarLoja(): Promise<void> {
  mostrarLoading(true);
  const [categorias, produtos] = await Promise.all([buscarDados<Categoria[]>("categorias"), buscarDados<Produto[]>("produtos")]);
  mostrarLoading(false);
  categoriasCache = categorias ?? [];
  produtosCache = produtos ?? [];
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

function preencherSelectCategorias(selecionada: string = ""): void {
  const select = document.getElementById("prod-categoria") as HTMLSelectElement;
  select.innerHTML = categoriasCache.map((c) => `<option value="${c.id}" ${String(c.id) === selecionada ? "selected" : ""}>${c.nome}</option>`).join("");
}

(window as unknown as Record<string, unknown>)["abrirModalCategoria"] = function (): void {
  document.getElementById("modal-cat-titulo")!.textContent = "Nova Categoria";
  (document.getElementById("cat-id") as HTMLInputElement).value = "";
  (document.getElementById("cat-nome") as HTMLInputElement).value = "";
  (document.getElementById("cat-status") as HTMLSelectElement).value = "pendente";
  new bootstrap.Modal(document.getElementById("modalCategoria")!).show();
};
(window as unknown as Record<string, unknown>)["editarCategoria"] = function (id: number): void {
  const categoria = categoriasCache.find((c) => c.id === id);
  if (!categoria) return;
  document.getElementById("modal-cat-titulo")!.textContent = "Editar Categoria";
  (document.getElementById("cat-id") as HTMLInputElement).value = String(categoria.id);
  (document.getElementById("cat-nome") as HTMLInputElement).value = categoria.nome;
  (document.getElementById("cat-status") as HTMLSelectElement).value = categoria.status;
  new bootstrap.Modal(document.getElementById("modalCategoria")!).show();
};
(window as unknown as Record<string, unknown>)["salvarCategoria"] = async function (): Promise<void> {
  const id = (document.getElementById("cat-id") as HTMLInputElement).value;
  const nome = (document.getElementById("cat-nome") as HTMLInputElement).value.trim();
  const status = (document.getElementById("cat-status") as HTMLSelectElement).value;
  if (!nome) { alert("Informe o nome da categoria."); return; }
  const resultado = await enviarDados({ acao: "salvar_categoria", id, nome, status });
  if (resultado.sucesso) { bootstrap.Modal.getInstance(document.getElementById("modalCategoria")!)?.hide(); carregarLoja(); } else alert("Erro: " + resultado.mensagem);
};
(window as unknown as Record<string, unknown>)["excluirCategoria"] = async function (id: number): Promise<void> {
  if (!confirm("Excluir esta categoria? Ela precisa estar sem produtos.")) return;
  const resultado = await enviarDados({ acao: "excluir_categoria", id });
  if (resultado.sucesso) carregarLoja(); else alert(resultado.mensagem);
};
(window as unknown as Record<string, unknown>)["abrirModalProduto"] = function (): void {
  document.getElementById("modal-prod-titulo")!.textContent = "Novo Produto";
  ["prod-id", "prod-nome", "prod-descricao", "prod-imagem", "prod-preco"].forEach((id) => (document.getElementById(id) as HTMLInputElement).value = "");
  (document.getElementById("prod-status") as HTMLSelectElement).value = "pendente";
  preencherSelectCategorias();
  new bootstrap.Modal(document.getElementById("modalProduto")!).show();
};
(window as unknown as Record<string, unknown>)["editarProduto"] = function (id: number): void {
  const produto = produtosCache.find((p) => p.id === id);
  if (!produto) return;
  document.getElementById("modal-prod-titulo")!.textContent = "Editar Produto";
  (document.getElementById("prod-id") as HTMLInputElement).value = String(produto.id);
  (document.getElementById("prod-nome") as HTMLInputElement).value = produto.nome;
  (document.getElementById("prod-descricao") as HTMLTextAreaElement).value = produto.descricao || "";
  (document.getElementById("prod-preco") as HTMLInputElement).value = produto.preco;
  (document.getElementById("prod-imagem") as HTMLInputElement).value = produto.imagem || "";
  (document.getElementById("prod-status") as HTMLSelectElement).value = produto.status;
  preencherSelectCategorias(String(produto.categoria_id));
  new bootstrap.Modal(document.getElementById("modalProduto")!).show();
};
(window as unknown as Record<string, unknown>)["salvarProduto"] = async function (): Promise<void> {
  const id = (document.getElementById("prod-id") as HTMLInputElement).value;
  const nome = (document.getElementById("prod-nome") as HTMLInputElement).value.trim();
  const preco = parseFloat((document.getElementById("prod-preco") as HTMLInputElement).value);
  const categoria_id = (document.getElementById("prod-categoria") as HTMLSelectElement).value;
  const descricao = (document.getElementById("prod-descricao") as HTMLTextAreaElement).value.trim();
  const imagem = (document.getElementById("prod-imagem") as HTMLInputElement).value.trim();
  const status = (document.getElementById("prod-status") as HTMLSelectElement).value;
  if (!nome || !categoria_id || isNaN(preco) || preco <= 0) { alert("Preencha nome, categoria e preço válido."); return; }
  const resultado = await enviarDados({ acao: "salvar_produto", id, nome, preco, categoria_id, descricao, imagem, status });
  if (resultado.sucesso) { bootstrap.Modal.getInstance(document.getElementById("modalProduto")!)?.hide(); carregarLoja(); } else alert("Erro: " + resultado.mensagem);
};
(window as unknown as Record<string, unknown>)["excluirProduto"] = async function (id: number): Promise<void> {
  if (!confirm("Excluir este produto definitivamente?")) return;
  const resultado = await enviarDados({ acao: "excluir_produto", id });
  if (resultado.sucesso) carregarLoja(); else alert(resultado.mensagem);
};

// ── NAVEGAÇÃO POR ABAS ────────────────────────────────────────────────────────

function ativarTab(nome: string): void {
  document.querySelectorAll<HTMLElement>(".tab-section").forEach((s) => s.classList.add("d-none"));
  document.querySelectorAll<HTMLElement>(".nav-side .nav-link").forEach((l) => l.classList.remove("active"));

  const secao = document.getElementById(`tab-${nome}`);
  const link  = document.querySelector<HTMLElement>(`.nav-side [data-tab="${nome}"]`);

  if (secao) secao.classList.remove("d-none");
  if (link)  link.classList.add("active");

  if (nome === "dashboard")    carregarDashboard();
  if (nome === "agendamentos") { carregarServicos(); carregarAgendamentos(); }
  if (nome === "clientes")     carregarClientes();
  if (nome === "servicos")     carregarServicos();
  if (nome === "loja")         carregarLoja();
}

// ── INICIALIZAÇÃO ─────────────────────────────────────────────────────────────

document.addEventListener("DOMContentLoaded", () => {
  // Navegação lateral
  document.querySelectorAll<HTMLElement>(".nav-side [data-tab]").forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      ativarTab(link.dataset["tab"] ?? "dashboard");
    });
  });

  // Filtros de status
  document.querySelectorAll<HTMLElement>("[data-filtro]").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll<HTMLElement>("[data-filtro]").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      filtroStatusAtual = btn.dataset["filtro"] ?? "";
      carregarAgendamentos(filtroStatusAtual);
    });
  });

  // Carga inicial
  carregarDashboard();
  carregarServicos(); // pré-carrega para o select de agendamentos
});

export {};
