<?php
require_once __DIR__ . '/admin_auth.php';
exigirAdmin();
require_once __DIR__ . '/conexao.php';

$clientes = [];
$resultado = $conexao->query('SELECT id, nome, telefone, email FROM clientes ORDER BY nome ASC');
if ($resultado) {
    while ($linha = $resultado->fetch_assoc()) {
        $clientes[] = $linha;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes — Barbearia Adrian Souza</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=2">
</head>
<body>
  <div class="d-flex min-vh-100">
    <aside class="sidebar">
      <span class="brand">💈 Admin</span>
      <nav class="nav-side d-flex flex-column">
        <a href="admin.php" class="nav-link"><i class="fas fa-chart-bar me-2"></i>Dashboard</a>
        <a href="admin.php" class="nav-link"><i class="fas fa-calendar me-2"></i>Agendamentos</a>
        <a href="clientes.php" class="nav-link active"><i class="fas fa-users me-2"></i>Clientes</a>
        <a href="admin.php" class="nav-link"><i class="fas fa-scissors me-2"></i>Serviços</a>
        <a href="admin.php" class="nav-link"><i class="fas fa-store me-2"></i>Loja</a>
        <hr>
        <a href="index.php" class="nav-link"><i class="fas fa-home me-2"></i>Ver Site</a>
        <a href="admin.php?sair=1" class="nav-link"><i class="fas fa-right-from-bracket me-2"></i>Sair</a>
      </nav>
    </aside>

    <main class="main-content flex-grow-1">
      <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap">
        <div>
          <p class="mini-texto mb-2">ÁREA ADMINISTRATIVA</p>
          <h1 class="section-title mb-1">Clientes</h1>
          <p class="text-secondary mb-0">Confira os clientes que já fizeram contato com a barbearia.</p>
        </div>
        <a href="admin.php" class="btn btn-gold"><i class="fas fa-arrow-left me-2"></i>Voltar ao painel</a>
      </div>

      <div class="card-stat mb-4">
        <h4><?php echo count($clientes); ?></h4>
        <p>Cliente(s) cadastrado(s)</p>
      </div>

      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle">
          <thead><tr><th>#</th><th>Nome</th><th>Telefone</th><th>E-mail</th></tr></thead>
          <tbody>
          <?php foreach ($clientes as $cliente): ?>
            <tr>
              <td><?php echo (int)$cliente['id']; ?></td>
              <td><strong><?php echo htmlspecialchars($cliente['nome']); ?></strong></td>
              <td><?php echo htmlspecialchars($cliente['telefone'] ?: 'Não informado'); ?></td>
              <td><?php echo htmlspecialchars($cliente['email'] ?: 'Não informado'); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (count($clientes) === 0): ?><p class="text-secondary text-center py-4">Nenhum cliente cadastrado.</p><?php endif; ?>
      </div>
    </main>
  </div>
</body>
</html>
