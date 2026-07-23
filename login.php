<?php
// ============================================================
// login.php — Tela de login do painel simples
// ============================================================
// Acessado pelo botão "Área Admin" no rodapé do site.
// Sempre pede senha ao entrar — a sessão é destruída ao sair.
// Credenciais: usuário = admin | senha = 1234

session_start();

// Se já estiver logado, redireciona para o painel
if (!empty($_SESSION['admin_logado'])) {
    header('Location: painel.php');
    exit;
}

$erro = '';

// Verifica o formulário ao enviar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha']        ?? '';

    if ($usuario === 'admin' && $senha === '1234') {
        session_regenerate_id(true);          // Evita session fixation
        $_SESSION['admin_logado'] = true;
        header('Location: painel.php');
        exit;
    }

    $erro = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Barbearia Adrian Souza</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&family=Lora:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">

  <main class="login-card">

    <div class="login-icon"><i class="fa-solid fa-lock"></i></div>

    <p class="mini-texto">ÁREA RESTRITA</p>
    <h1>Painel Admin</h1>
    <p class="login-subtitulo">Entre com suas credenciais para gerenciar os agendamentos.</p>

    <!-- Erro de login -->
    <?php if ($erro): ?>
      <div class="alert alert-danger py-2"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <!-- Formulário -->
    <form method="post" class="login-form">
      <label for="usuario">Usuário</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" required placeholder="admin">

      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" autocomplete="current-password" required placeholder="••••">

      <button type="submit" class="btn btn-gold w-100 mt-3">
        <i class="fa-solid fa-right-to-bracket me-2"></i>Entrar
      </button>
    </form>

    <a class="login-voltar" href="index.php">
      <i class="fa-solid fa-arrow-left me-1"></i>Voltar para o site
    </a>

  </main>

</body>
</html>
