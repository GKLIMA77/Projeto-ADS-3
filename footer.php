<?php
// ============================================================
// footer.php — Rodapé do site
// ============================================================
// Incluído no final do index.php com: include('footer.php')
?>

<footer>
  <div class="footer-content">

    <p class="footer-brand">💈 Adrian Souza</p>
    <p class="footer-slogan">Estilo, presença e personalidade — desde o primeiro corte.</p>

    <!-- Redes sociais -->
    <div class="footer-redes">
      <a href="https://instagram.com" target="_blank" rel="noopener" aria-label="Instagram" class="footer-rede">
        <i class="fa-brands fa-instagram"></i>
      </a>
      <a href="https://wa.me/5544997306220" target="_blank" rel="noopener" aria-label="WhatsApp" class="footer-rede footer-rede-whatsapp">
        <i class="fa-brands fa-whatsapp"></i>
      </a>
      <a href="https://facebook.com" target="_blank" rel="noopener" aria-label="Facebook" class="footer-rede footer-rede-face">
        <i class="fa-brands fa-facebook-f"></i>
      </a>
    </div>

    <!-- Copyright + botão discreto de acesso ao painel -->
    <div class="footer-bottom">
      <p class="footer-copy">&copy; <?php echo date('Y'); ?> Barbearia Adrian Souza &mdash; Todos os direitos reservados.</p>
      <a href="login.php" class="footer-admin-btn">
        <i class="fas fa-lock"></i> Área Admin
      </a>
    </div>

  </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
