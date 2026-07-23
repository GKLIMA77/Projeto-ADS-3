<?php 
session_start();

// Bloqueia acesso sem login
if (empty($_SESSION['admin_logado'])) {
    header('Location: login.php');
    exit;
}

// Logout: destrói a sessão completamente e volta para o login
if (isset($_GET['sair'])) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

include 'conexao.php';

// ============================================================
// AÇÕES: aceitar, recusar, editar
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if ($id > 0) {

        // Confirma o agendamento
        if ($acao === 'aceitar') {
            $conexao->query("UPDATE agendamentos SET status = 'confirmado' WHERE id = $id");
        }

        // Cancela o agendamento
        if ($acao === 'recusar') {
            $conexao->query("UPDATE agendamentos SET status = 'cancelado' WHERE id = $id");
        }

        // Atualiza nome, data/hora e status
        if ($acao === 'editar') {
            $nome     = $conexao->real_escape_string(trim($_POST['nome']     ?? ''));
            $datahora = $conexao->real_escape_string($_POST['datahora']       ?? '');
            $status   = $conexao->real_escape_string($_POST['status']         ?? 'pendente');
            if ($nome && $datahora) {
                $conexao->query("
                    UPDATE agendamentos
                    SET cliente_nome = '$nome', data_hora = '$datahora', status = '$status'
                    WHERE id = $id
                ");
            }
        }
    }

    // Redireciona para evitar reenvio ao atualizar a página
    header('Location: painel.php?filtro=' . ($_POST['filtro_atual'] ?? 'todos'));
    exit;
}

// ============================================================
// FILTRO DE STATUS
// ============================================================
$filtro = $_GET['filtro'] ?? 'todos';
$where  = '';
if ($filtro === 'pendente')   $where = "WHERE a.status = 'pendente'";
if ($filtro === 'confirmado') $where = "WHERE a.status = 'confirmado'";
if ($filtro === 'cancelado')  $where = "WHERE a.status = 'cancelado'";

// ============================================================
// BUSCA OS AGENDAMENTOS
// ============================================================
$agendamentos = [];
$resultado = $conexao->query("
    SELECT a.id, a.cliente_nome, s.nome AS servico, a.data_hora, a.status
    FROM agendamentos a
    JOIN servicos s ON s.id = a.servico_id
    $where
    ORDER BY a.data_hora DESC
");
while ($linha = $resultado->fetch_assoc()) {
    $agendamentos[] = $linha;
}

// ============================================================
// CONTAGEM POR STATUS (cards de resumo)
// ============================================================
$contagem = ['pendente' => 0, 'confirmado' => 0, 'cancelado' => 0];
$totais = $conexao->query("SELECT status, COUNT(*) AS total FROM agendamentos GROUP BY status");
while ($t = $totais->fetch_assoc()) {
    $contagem[$t['status']] = (int)$t['total'];
}

// Mapeamento de status para cor do badge Bootstrap
$cores = [
    'pendente'   => 'warning text-dark',
    'confirmado' => 'success',
    'cancelado'  => 'danger',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Painel Admin — Barbearia Adrian Souza</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&family=Lora:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Botão hambúrguer (visível só no mobile) -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menu">
  <i class="fas fa-bars"></i>
</button>

<!-- Overlay escuro que fecha o menu ao clicar (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="d-flex">

  <!-- ============================================================
       SIDEBAR: menu lateral
       ============================================================ -->
  <aside class="sidebar" id="sidebar">

    <!-- Cabeçalho do sidebar com botão de fechar (mobile) -->
    <div class="sidebar-header">
      <span class="brand">💈 Admin</span>
      <button class="sidebar-close" id="sidebarClose" aria-label="Fechar menu">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <nav class="nav-side d-flex flex-column">
      <a href="painel.php" class="nav-link active">
        <i class="fas fa-calendar me-2"></i>Agendamentos
      </a>
      <hr>
      <a href="index.php" class="nav-link">
        <i class="fas fa-home me-2"></i>Ver Site
      </a>
      <a href="painel.php?sair=1" class="nav-link nav-link-sair">
        <i class="fas fa-right-from-bracket me-2"></i>Sair
      </a>
    </nav>

  </aside>

  <!-- ============================================================
       CONTEÚDO PRINCIPAL
       ============================================================ -->
  <main class="main-content flex-grow-1">

    <!-- Topo com título e nome do painel -->
    <div class="painel-topo">
      <div>
        <p class="mini-texto mb-1">ÁREA ADMINISTRATIVA</p>
        <h2 class="section-title mb-0">Agendamentos</h2>
      </div>
      <span class="painel-usuario"><i class="fas fa-user-shield me-1"></i>admin</span>
    </div>

    <!-- ── CARDS DE RESUMO ── -->
    <div class="row g-3 mb-4">
      <div class="col-sm-4">
        <div class="card-stat card-stat-pendente">
          <div class="card-stat-icon"><i class="fas fa-clock"></i></div>
          <div>
            <h4><?php echo $contagem['pendente']; ?></h4>
            <p>Pendentes</p>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="card-stat card-stat-confirmado">
          <div class="card-stat-icon"><i class="fas fa-check-circle"></i></div>
          <div>
            <h4><?php echo $contagem['confirmado']; ?></h4>
            <p>Confirmados</p>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="card-stat card-stat-cancelado">
          <div class="card-stat-icon"><i class="fas fa-times-circle"></i></div>
          <div>
            <h4><?php echo $contagem['cancelado']; ?></h4>
            <p>Cancelados</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── FILTROS DE STATUS ── -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <a href="painel.php?filtro=todos"      class="tab-btn <?php if ($filtro === 'todos')      echo 'active'; ?>">Todos</a>
      <a href="painel.php?filtro=pendente"   class="tab-btn <?php if ($filtro === 'pendente')   echo 'active'; ?>">Pendentes</a>
      <a href="painel.php?filtro=confirmado" class="tab-btn <?php if ($filtro === 'confirmado') echo 'active'; ?>">Confirmados</a>
      <a href="painel.php?filtro=cancelado"  class="tab-btn <?php if ($filtro === 'cancelado')  echo 'active'; ?>">Cancelados</a>
    </div>

    <!-- ── TABELA DE AGENDAMENTOS ── -->
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Cliente</th>
            <th>Serviço</th>
            <th>Data / Hora</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>

          <?php if (count($agendamentos) === 0): ?>
            <tr>
              <td colspan="6" class="text-center text-secondary py-4">Nenhum agendamento encontrado.</td>
            </tr>
          <?php endif; ?>

          <?php foreach ($agendamentos as $ag): ?>
            <tr>
              <td><?php echo $ag['id']; ?></td>
              <td><strong><?php echo htmlspecialchars($ag['cliente_nome']); ?></strong></td>
              <td><?php echo htmlspecialchars($ag['servico']); ?></td>
              <td><?php echo date('d/m/Y H:i', strtotime($ag['data_hora'])); ?></td>
              <td>
                <span class="badge bg-<?php echo $cores[$ag['status']] ?? 'secondary'; ?>">
                  <?php echo ucfirst($ag['status']); ?>
                </span>
              </td>
              <td>
                <div class="acoes-grupo">

                  <!-- ACEITAR: só aparece se não estiver confirmado -->
                  <?php if ($ag['status'] !== 'confirmado'): ?>
                    <form method="post">
                      <input type="hidden" name="acao"         value="aceitar">
                      <input type="hidden" name="id"           value="<?php echo $ag['id']; ?>">
                      <input type="hidden" name="filtro_atual" value="<?php echo $filtro; ?>">
                      <button type="submit" class="btn btn-sm btn-success" title="Aceitar">
                        <i class="fas fa-check"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- RECUSAR: só aparece se não estiver cancelado -->
                  <?php if ($ag['status'] !== 'cancelado'): ?>
                    <form method="post">
                      <input type="hidden" name="acao"         value="recusar">
                      <input type="hidden" name="id"           value="<?php echo $ag['id']; ?>">
                      <input type="hidden" name="filtro_atual" value="<?php echo $filtro; ?>">
                      <button type="submit" class="btn btn-sm btn-danger" title="Recusar">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- EDITAR: abre o modal com os dados preenchidos -->
                  <button
                    class="btn btn-sm btn-outline-warning"
                    title="Editar"
                    onclick="abrirEdicao(
                      <?php echo $ag['id']; ?>,
                      '<?php echo addslashes(htmlspecialchars($ag['cliente_nome'])); ?>',
                      '<?php echo str_replace(' ', 'T', $ag['data_hora']); ?>',
                      '<?php echo $ag['status']; ?>'
                    )">
                    <i class="fas fa-edit"></i>
                  </button>

                </div>
              </td>
            </tr>
          <?php endforeach; ?>

        </tbody>
      </table>
    </div>

  </main>
</div>


     MODAL DE EDIÇÃO
<div class="modal fade" id="modalEdicao" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Agendamento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <form method="post">
        <div class="modal-body">
          <!-- Campos ocultos para identificar a ação e o registro -->
          <input type="hidden" name="acao"         value="editar">
          <input type="hidden" name="id"           id="edit-id">
          <input type="hidden" name="filtro_atual" value="<?php echo $filtro; ?>">

          <div class="mb-3">
            <label class="form-label">Nome do cliente</label>
            <input type="text" name="nome" id="edit-nome" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Data e hora</label>
            <input type="datetime-local" name="datahora" id="edit-datahora" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="edit-status" class="form-select">
              <option value="pendente">Pendente</option>
              <option value="confirmado">Confirmado</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-gold">Salvar alterações</button>
        </div>
      </form>

    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Preenche o modal com os dados do agendamento selecionado 
function abrirEdicao(id, nome, datahora, status) {
  document.getElementById('edit-id').value       = id;
  document.getElementById('edit-nome').value     = nome;
  document.getElementById('edit-datahora').value = datahora.slice(0, 16);
  document.getElementById('edit-status').value   = status;
  new bootstrap.Modal(document.getElementById('modalEdicao')).show();
}

//  Menu lateral no mobile 
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebarOverlay');
const btnOpen  = document.getElementById('sidebarToggle');
const btnClose = document.getElementById('sidebarClose');

function abrirMenu()  { sidebar.classList.add('aberto');   overlay.classList.add('ativo'); }
function fecharMenu() { sidebar.classList.remove('aberto'); overlay.classList.remove('ativo'); }

btnOpen.addEventListener('click', abrirMenu);
btnClose.addEventListener('click', fecharMenu);
overlay.addEventListener('click', fecharMenu);
</script>

</body>
</html>
