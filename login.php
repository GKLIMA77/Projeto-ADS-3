<?php
// ============================================================
// login.php — Login do painel simples (painel.php)
// ============================================================
// IMPORTANTE: Sempre destrói a sessão ao carregar esta página.
// Assim, toda vez que o usuário acessar login.php (inclusive
// vindo do botão "Sair"), precisará digitar a senha novamente.

session_start();

// Destrói qualquer sessão existente ao entrar na página de login
// Isso garante que SEMPRE será pedida a senha
session_unset();
session_destroy();

// Inicia uma nova sessão limpa
session_start();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha']        ?? '';

    if ($usuario === 'admin' && $senha === '1234') {
        session_regenerate_id(true);
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

    <?php if ($erro): ?>
      <div class="alert alert-danger py-2"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form method="post" class="login-form">
      <label for="usuario">Usuário</label>
      <input type="text" id="usuario" name="usuario" autocomplete="off" required placeholder="admin">

      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" autocomplete="off" required placeholder="••••">

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
