<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit('Servicio temporalmente no disponible.');
}

$cliente = exigirClientePublico($pdo, 'public/clientes/seguridad.php');
$estado = $_SESSION['cliente_seguridad_estado'] ?? null;
unset($_SESSION['cliente_seguridad_estado']);

renderPublicPageStart(
    'Seguridad | Coratto Pet',
    'Administra la seguridad de tu cuenta Coratto Pet.',
    'cuenta'
);
?>
<main id="contenido" class="customer-area">
    <section class="customer-shell">
        <header class="customer-section-heading">
            <div>
                <a class="customer-back" href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">← Mi cuenta</a>
                <span>SEGURIDAD</span>
                <h1>Protege tu cuenta</h1>
                <p>
                    Mantén tu contraseña actualizada para cuidar el acceso a tus datos y compras.
                </p>
            </div>
        </header>

        <nav class="customer-account-nav" aria-label="Secciones de mi cuenta">
            <a href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">Resumen</a>
            <a href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">Mis pedidos</a>
            <a href="<?= e(appUrl('public/clientes/fichas-alimentacion.php')) ?>">Mis fichas de alimentación</a>
            <a href="<?= e(appUrl('public/clientes/perfil.php')) ?>">Mis datos</a>
            <a class="active" href="<?= e(appUrl('public/clientes/seguridad.php')) ?>">Seguridad</a>
        </nav>

        <?php if (is_array($estado)): ?>
            <div class="customer-feedback customer-feedback--<?= e((string) ($estado['tipo'] ?? 'ok')) ?>">
                <?= e((string) ($estado['mensaje'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <section class="customer-security-layout">
            <div class="customer-security-intro">
                <span>ACCESO SEGURO</span>
                <h2>Cambiar contraseña</h2>
                <p>
                    Usa una contraseña distinta a la de otros servicios y evita compartirla.
                    Debe tener al menos 10 caracteres.
                </p>
            </div>

            <form class="customer-form customer-security-form" method="post" action="<?= e(appUrl('public/clientes/actualizar-clave.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <label class="customer-field">
                    <span>Contraseña actual</span>
                    <input type="password" name="actual" maxlength="200" required autocomplete="current-password">
                </label>

                <label class="customer-field">
                    <span>Nueva contraseña</span>
                    <input type="password" name="nueva" minlength="10" maxlength="200" required autocomplete="new-password">
                </label>

                <label class="customer-field">
                    <span>Confirmar nueva contraseña</span>
                    <input type="password" name="confirmacion" minlength="10" maxlength="200" required autocomplete="new-password">
                </label>

                <button class="button customer-primary-action" type="submit">
                    Actualizar contraseña
                </button>
            </form>
        </section>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
