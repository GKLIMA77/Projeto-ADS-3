<?php
// ============================================================
// index.php — Página principal da Barbearia Adrian Souza
// ============================================================

include  'conexao.php';
require_once 'admin_auth.php';

$whatsappVendas = WHATSAPP_VENDAS;

//  Busca serviços ativos no banco
$servicos = [];
$resultado = $conexao->query("SELECT * FROM servicos WHERE ativo = 1 ORDER BY preco ASC");
while ($linha = $resultado->fetch_assoc()) {
    $servicos[] = $linha;
}

//  Busca produtos aprovados com suas categorias 
$produtos = [];
$res = $conexao->query("
    SELECT p.*, c.nome AS categoria_nome
    FROM produtos p
    JOIN categorias c ON c.id = p.categoria_id
    WHERE p.status = 'aprovado' AND c.status = 'aprovada'
    ORDER BY c.nome, p.nome
");
if ($res) {
    while ($linha = $res->fetch_assoc()) {
        $produtos[] = $linha;
    }
}
?>

<?php include 'header.php'; ?>



     HERO — Banner principal
<section class="hero" id="inicio">
  <div class="hero-content">
    <p class="mini-texto">BARBEARIA PREMIUM</p>
    <h1>Seu estilo começa aqui.</h1>
    <p>Mais do que cortes, entregamos estilo, presença e personalidade.</p>
    <a href="#agenda" class="btn-principal">Agendar Horário</a>
  </div>
</section>


     LOJA — Produtos disponíveis
<section class="loja" id="loja">
  <div class="container">

    <div class="section-topo">
      <p class="mini-texto">LOJA DA BARBEARIA</p>
      <h2>Leve o seu estilo para casa</h2>
      <p class="agenda-descricao">Produtos para manter o corte, a barba e o acabamento perfeitos no dia a dia.</p>
    </div>

    <?php if (count($produtos) > 0): ?>

      <!-- Botões de filtro por categoria -->
      <div class="loja-filtros">
        <button type="button" class="filtro-produto ativo" data-categoria-filtro="todas">Todos</button>
        <?php
          $categoriasLoja = array_unique(array_column($produtos, 'categoria_nome'));
          foreach ($categoriasLoja as $cat):
        ?>
          <button type="button" class="filtro-produto" data-categoria-filtro="<?php echo htmlspecialchars($cat); ?>">
            <?php echo htmlspecialchars($cat); ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Grade de produtos -->
      <div class="produtos-grid">
        <?php
          $catAtual = '';
          foreach ($produtos as $produto):
            if ($catAtual !== $produto['categoria_nome']):
              $catAtual = $produto['categoria_nome'];
        ?>
          <div class="categoria-titulo">
            <span><?php echo htmlspecialchars($catAtual); ?></span>
          </div>
        <?php endif; ?>

        <article class="produto-card" data-categoria-produto="<?php echo htmlspecialchars($produto['categoria_nome']); ?>">
          <div class="produto-imagem-wrap">
            <img
              src="<?php echo htmlspecialchars($produto['imagem'] ?: 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?q=80&w=900'); ?>"
              alt="<?php echo htmlspecialchars($produto['nome']); ?>"
              loading="lazy">
            <span class="produto-tag"><?php echo htmlspecialchars($produto['categoria_nome']); ?></span>
          </div>
          <div class="produto-info">
            <h3><?php echo htmlspecialchars($produto['nome']); ?></h3>
            <p><?php echo htmlspecialchars($produto['descricao']); ?></p>
            <strong>R$ <?php echo number_format((float)$produto['preco'], 2, ',', '.'); ?></strong>
            <button
              type="button"
              class="btn-produto"
              onclick="abrirCompra('<?php echo htmlspecialchars(addslashes($produto['nome'])); ?>', <?php echo (float)$produto['preco']; ?>)">
              Comprar pelo WhatsApp <i class="fa-brands fa-whatsapp"></i>
            </button>
          </div>
        </article>

        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <p class="text-center text-secondary">Em breve teremos produtos disponíveis.</p>
    <?php endif; ?>

  </div>
</section>

<!-- Modal de compra pelo WhatsApp -->
<div class="modal fade" id="modalCompra" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content compra-modal">
      <div class="modal-header">
        <h5 class="modal-title">Finalizar interesse</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="compra-produto" id="compraProduto"></p>
        <label for="compraQuantidade" class="form-label">Quantidade</label>
        <div class="quantidade-controle">
          <button type="button" onclick="alterarQuantidade(-1)">−</button>
          <input type="number" id="compraQuantidade" value="1" min="1" max="20">
          <button type="button" onclick="alterarQuantidade(1)">+</button>
        </div>
        <p class="compra-total">Total estimado: <strong id="compraTotal">R$ 0,00</strong></p>
        <p class="compra-aviso">Você será levado ao WhatsApp para combinar disponibilidade, retirada e pagamento.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continuar olhando</button>
        <button type="button" class="btn btn-whatsapp" onclick="enviarCompraWhatsApp()">
          <i class="fa-brands fa-whatsapp me-2"></i>Enviar pedido
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// ── Modal de compra ───────────────────────────────────────────
let produtoCompra = { nome: '', preco: 0 };

function formatarBRL(valor) {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function atualizarTotal() {
  const qtd = Math.max(1, Math.min(20, Number(document.getElementById('compraQuantidade').value) || 1));
  document.getElementById('compraQuantidade').value = qtd;
  document.getElementById('compraTotal').textContent = formatarBRL(produtoCompra.preco * qtd);
}

function abrirCompra(nome, preco) {
  produtoCompra = { nome, preco };
  document.getElementById('compraProduto').textContent = nome;
  document.getElementById('compraQuantidade').value = 1;
  atualizarTotal();
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCompra')).show();
}

function alterarQuantidade(delta) {
  const c = document.getElementById('compraQuantidade');
  c.value = Math.max(1, Math.min(20, Number(c.value) + delta));
  atualizarTotal();
}

document.getElementById('compraQuantidade').addEventListener('input', atualizarTotal);

function enviarCompraWhatsApp() {
  const qtd   = Number(document.getElementById('compraQuantidade').value) || 1;
  const total = produtoCompra.preco * qtd;
  const msg   = `Olá! Tenho interesse em comprar ${qtd}x ${produtoCompra.nome}. Total estimado: ${formatarBRL(total)}.`;
  window.open('https://wa.me/<?php echo $whatsappVendas; ?>?text=' + encodeURIComponent(msg), '_blank', 'noopener');
}

// ── Filtro de categoria na loja ───────────────────────────────
document.querySelectorAll('[data-categoria-filtro]').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('[data-categoria-filtro]').forEach(b => b.classList.remove('ativo'));
    btn.classList.add('ativo');

    const cat = btn.dataset.categoriaFiltro;

    document.querySelectorAll('[data-categoria-produto]').forEach(function(card) {
      card.classList.toggle('produto-oculto', cat !== 'todas' && card.dataset.categoriaProduto !== cat);
    });

    document.querySelectorAll('.categoria-titulo').forEach(function(titulo) {
      titulo.classList.toggle('produto-oculto', cat !== 'todas' && titulo.textContent.trim() !== cat);
    });
  });
});
</script>


<!-- =====================================================
     LOCALIZAÇÃO — Mapa do Google Maps
     ===================================================== -->
<section class="localizacao" id="sobre">
  <div class="container">

    <div class="section-topo">
      <p class="mini-texto">ONDE ESTAMOS</p>
      <h2>Nossa localização</h2>
      <p class="agenda-descricao">Venha nos visitar! Estamos prontos para te atender com hora marcada ou na hora.</p>
    </div>

    <div class="mapa-container">
      <!--
        Para exibir o endereço exato da barbearia:
        1. Abra maps.google.com e pesquise o endereço
        2. Clique em Compartilhar → Incorporar um mapa
        3. Copie o link do src= e cole aqui abaixo
      -->
      <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d58868.34!2d-52.3715!3d-24.0456!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x94f3e53bb0ac27c1%3A0x93ac7c67dc4a8b3f!2sCampo%20Mour%C3%A3o%2C%20PR!5e0!3m2!1spt-BR!2sbr!4v1700000000000"
        width="100%" height="420"
        style="border:0; border-radius:18px;"
        allowfullscreen="" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        title="Localização da Barbearia Adrian Souza">
      </iframe>

      <div class="mapa-info-boxes">
        <div class="mapa-info">
          <i class="fas fa-map-marker-alt"></i>
          <div><strong>Endereço</strong><span>Campo Mourão – PR</span></div>
        </div>
        <div class="mapa-info">
          <i class="fas fa-clock"></i>
          <div><strong>Horário</strong><span>Seg–Sáb: 9h às 19h</span></div>
        </div>
        <div class="mapa-info">
          <i class="fas fa-phone"></i>
          <div><strong>Contato</strong><span>(44) 99730-6220</span></div>
        </div>
      </div>
    </div>

  </div>
</section>


<!-- =====================================================
     AGENDA — Formulário de agendamento (novo layout)
     ===================================================== -->
<section class="agenda" id="agenda">
  <div class="container">

    <div class="section-topo">
      <p class="mini-texto">AGENDAMENTO</p>
      <h2>Agende seu horário</h2>
      <p class="agenda-descricao">Preencha os dados abaixo e confirme. Responderemos em até 24h.</p>
    </div>

    <div class="agenda-card">

      <!-- Alertas (ficam ocultos até o envio) -->
      <div id="alerta-sucesso" class="alerta-agendamento alerta-ok">
        <i class="fas fa-check-circle"></i>
        <span id="alerta-msg-sucesso"></span>
      </div>
      <div id="alerta-erro" class="alerta-agendamento alerta-fail">
        <i class="fas fa-exclamation-circle"></i>
        <span id="alerta-msg-erro"></span>
      </div>

      <!-- Formulário de agendamento -->
      <form id="form-agendamento" novalidate>

        <!-- Linha 1: nome e serviço -->
        <div class="agenda-grid">
          <div class="agenda-campo">
            <label for="agenda-nome">
              <i class="fas fa-user"></i> Seu nome
            </label>
            <input
              type="text"
              id="agenda-nome"
              name="agenda-nome"
              placeholder="Como você se chama?"
              required>
          </div>

          <div class="agenda-campo">
            <label for="agenda-servico">
              <i class="fas fa-scissors"></i> Serviço
            </label>
            <select id="agenda-servico" name="agenda-servico" required>
              <option value="">Selecione o serviço</option>
              <?php foreach ($servicos as $sv): ?>
                <option value="<?php echo htmlspecialchars($sv['nome']); ?>">
                  <?php echo htmlspecialchars($sv['nome']); ?>
                  — R$ <?php echo number_format((float)$sv['preco'], 2, ',', '.'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Linha 2: data e horário -->
        <div class="agenda-grid">
          <div class="agenda-campo">
            <label for="agenda-data">
              <i class="fas fa-calendar-alt"></i> Data
            </label>
            <input
              type="date"
              id="agenda-data"
              name="agenda-data"
              required>
          </div>

          <div class="agenda-campo">
            <label for="agenda-horario">
              <i class="fas fa-clock"></i> Horário
            </label>
            <select id="agenda-horario" name="agenda-horario" required>
              <option value="">Escolha um horário</option>
              <option value="09:00">09:00 — manhã</option>
              <option value="10:30">10:30 — manhã</option>
              <option value="12:00">12:00 — meio-dia</option>
              <option value="14:00">14:00 — tarde</option>
              <option value="15:30">15:30 — tarde</option>
              <option value="17:00">17:00 — fim de tarde</option>
              <option value="18:30">18:30 — noite</option>
            </select>
          </div>
        </div>

        <!-- Resumo do agendamento (aparece ao preencher) -->
        <div id="resumo-agendamento" class="resumo-agendamento" style="display:none;">
          <i class="fas fa-calendar-check"></i>
          <span id="resumo-texto"></span>
        </div>

        <button type="submit" id="btn-submit" class="btn-agendar">
          <i class="fas fa-calendar-check me-2"></i>
          Confirmar agendamento
        </button>

      </form>
    </div>

  </div>
</section>

<script>
// ── Bloqueia datas passadas ───────────────────────────────────
document.getElementById('agenda-data').min = new Date().toISOString().split('T')[0];

// ── Mostra resumo do agendamento conforme o usuário preenche ──
function atualizarResumo() {
  const nome    = document.getElementById('agenda-nome').value.trim();
  const servico = document.getElementById('agenda-servico').value;
  const data    = document.getElementById('agenda-data').value;
  const horario = document.getElementById('agenda-horario').value;
  const resumo  = document.getElementById('resumo-agendamento');

  if (nome && servico && data && horario) {
    const dataFormatada = new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
    document.getElementById('resumo-texto').textContent =
      `${nome} — ${servico} — ${dataFormatada} às ${horario}`;
    resumo.style.display = 'flex';
  } else {
    resumo.style.display = 'none';
  }
}

['agenda-nome', 'agenda-servico', 'agenda-data', 'agenda-horario'].forEach(function(id) {
  document.getElementById(id).addEventListener('input', atualizarResumo);
  document.getElementById(id).addEventListener('change', atualizarResumo);
});

// ── Envio do formulário via fetch ─────────────────────────────
document.getElementById('form-agendamento').addEventListener('submit', async function(e) {
  e.preventDefault();

  // Esconde alertas anteriores
  document.getElementById('alerta-sucesso').style.display = 'none';
  document.getElementById('alerta-erro').style.display    = 'none';

  // Desabilita botão para evitar duplo envio
  const botao = document.getElementById('btn-submit');
  botao.disabled   = true;
  botao.innerHTML  = '<i class="fas fa-spinner fa-spin me-2"></i>Processando...';

  try {
    const dados    = new FormData(this);
    const resposta = await fetch('processar_agendamento.php', { method: 'POST', body: dados });
    const resultado = await resposta.json();

    if (resultado.sucesso) {
      document.getElementById('alerta-msg-sucesso').textContent = resultado.mensagem;
      document.getElementById('alerta-sucesso').style.display   = 'flex';
      this.reset();
      document.getElementById('resumo-agendamento').style.display = 'none';
      document.getElementById('alerta-sucesso').scrollIntoView({ behavior: 'smooth' });
    } else {
      document.getElementById('alerta-msg-erro').textContent = resultado.mensagem;
      document.getElementById('alerta-erro').style.display   = 'flex';
    }
  } catch (erro) {
    document.getElementById('alerta-msg-erro').textContent = 'Erro de conexão: ' + erro.message;
    document.getElementById('alerta-erro').style.display   = 'flex';
  }

  botao.disabled  = false;
  botao.innerHTML = '<i class="fas fa-calendar-check me-2"></i>Confirmar agendamento';
});
</script>


<?php include 'footer.php'; ?>
