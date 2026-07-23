<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Barbearia Adrian Souza</title>

  <!-- Fontes -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;700&family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome (ícones) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <!-- CSS do projeto -->
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- NAVBAR: barra de navegação fixa no topo -->
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-custom">
    <div class="container">

      <a class="navbar-brand fw-bold" href="#inicio">💈 Adrian Souza</a>

      <!-- Botão hambúrguer (aparece em telas pequenas) -->
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="menu">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
          <li class="nav-item"><a class="nav-link" href="#inicio">Início</a></li>
          <li class="nav-item"><a class="nav-link" href="#loja">Loja</a></li>
          <li class="nav-item"><a class="nav-link" href="#sobre">Localização</a></li>
          <li class="nav-item">
            <a href="#agenda" class="btn btn-warning fw-bold px-4 rounded-pill">Agendar</a>
          </li>
        </ul>
      </div>

    </div>
  </nav>
