<?php

declare(strict_types=1);

$footerEmail = trim((string) ($config['email_contacto'] ?? ''));
?>

<footer class="site-footer">
    <div class="container site-footer__main">

        <div class="site-footer__identity">
            <img
                src="<?= e(appUrl('public/assets/img/frase-mascota-footer.png')) ?>"
                alt="Nutrición, bienestar y confianza para tu mascota"
                class="site-footer__phrase"
                loading="lazy"
                decoding="async"
            >
        </div>

        <nav class="site-footer__column" aria-label="Enlaces del sitio">
            <h2>Enlaces</h2>

            <a href="<?= e(appUrl('public/index.php')) ?>">Inicio</a>
            <a href="<?= e(appUrl('public/nosotros.php')) ?>">Nosotros</a>
            <a href="<?= e(appUrl('public/catalogo.php')) ?>">Productos</a>
            <a href="<?= e(appUrl('public/blog.php')) ?>">Blog</a>
            <a href="<?= e(appUrl('public/contacto.php')) ?>">Contacto</a>
        </nav>

        <nav class="site-footer__column" aria-label="Ayuda">
            <h2>Ayuda</h2>

            <a href="<?= e(appUrl('public/preguntas-frecuentes.php')) ?>">
                Preguntas frecuentes
            </a>

            <a href="<?= e(appUrl('public/politicas-envio.php')) ?>">
                Políticas de envío
            </a>

            <a href="<?= e(appUrl('public/cambios-devoluciones.php')) ?>">
                Cambios y devoluciones
            </a>

            <a href="<?= e(appUrl('public/terminos-condiciones.php')) ?>">
                Términos y condiciones
            </a>
        </nav>

        <div class="site-footer__column site-footer__contact">
            <h2>Contacto</h2>

            <?php if ($footerEmail !== ''): ?>
                <a href="mailto:<?= e($footerEmail) ?>">
                    <span aria-hidden="true">✉</span>
                    <?= e($footerEmail) ?>
                </a>
            <?php endif; ?>

            <?php if (!empty($whatsappUrl) && !str_starts_with($whatsappUrl, '#')): ?>
                <a href="<?= e($whatsappUrl) ?>">
                    <span aria-hidden="true">⌕</span>
                    WhatsApp Coratto
                </a>
            <?php endif; ?>

            <a href="<?= e(appUrl('public/contacto.php')) ?>">
                <span aria-hidden="true">♡</span>
                Formulario de contacto
            </a>
        </div>

        <div class="site-footer__newsletter newsletter">
            <h2>Suscríbete a nuestro newsletter</h2>

            <p>
                Recibe novedades, consejos y promociones exclusivas para tu mascota.
            </p>

            <form class="site-footer__newsletter-form">
                <label class="sr-only" for="footer-newsletter-email">
                    Tu correo electrónico
                </label> 

                <input
                    id="footer-newsletter-email"
                    name="email"
                    type="email"
                    placeholder="Tu correo electrónico"
                    autocomplete="email"
                    required
                >

              <button type="submit" aria-label="Suscribirme al newsletter">
                  <span class="site-footer__newsletter-paw" aria-hidden="true"></span>
              </button>
            </form>
        </div>

    </div>

    <div class="site-footer__bottom">
        <div class="container">
            <p>
                © <?= date('Y') ?> Coratto Pet. Todos los derechos reservados.
            </p>

            <span class="site-footer__bottom-paw" aria-hidden="true">🐾</span>
        </div>
    </div>
</footer>