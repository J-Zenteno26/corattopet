<?php declare(strict_types=1); ?>
<footer class="site-footer" id="contacto">
  <div class="container footer-grid">
    <div class="footer-brand"><img src="../public/img/logo-coratto-pet.png" alt="Coratto Pet"><p>Alimentación premium con un porqué. Elegimos con cariño y explicamos con claridad.</p></div>
    <div><h3>Explora</h3><a href="#guia-eleccion">Necesidades</a><a href="#criterios-alimento">Cómo elegir</a><a href="#seleccion">Selección Coratto</a><a href="#aprende">Guías</a></div>
    <div><h3>Contacto</h3><?php if (!empty($config['email_contacto'])): ?><a href="mailto:<?= e($config['email_contacto']) ?>"><?= e($config['email_contacto']) ?></a><?php endif; ?><a href="<?= e($whatsappUrl) ?>">WhatsApp</a><?php if (!empty($config['instagram'])): ?><a href="<?= e($config['instagram']) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?><?php if (!empty($config['horario_atencion'])): ?><p><?= nl2br(e($config['horario_atencion'])) ?></p><?php endif; ?></div>
    <div><h3>Una elección informada</h3><p>La información del sitio es orientativa y no reemplaza la recomendación de un médico veterinario.</p></div>
  </div>
  <div class="footer-bottom"><div class="container">© <?= date('Y') ?> Coratto Pet. Todos los derechos reservados.</div></div>
</footer>
