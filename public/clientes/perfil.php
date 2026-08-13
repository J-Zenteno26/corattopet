<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit('Servicio temporalmente no disponible.');
}

$cliente = exigirClientePublico($pdo, 'public/clientes/perfil.php');
$estado = $_SESSION['cliente_perfil_estado'] ?? null;
unset($_SESSION['cliente_perfil_estado']);

renderPublicPageStart(
    'Mis datos | Coratto Pet',
    'Administra tus datos de cliente Coratto Pet.',
    'cuenta'
);
?>
<main id="contenido" class="customer-area">
    <section class="customer-shell">
        <header class="customer-section-heading">
            <div>
                <a class="customer-back" href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">← Mi cuenta</a>
                <span>MIS DATOS</span>
                <h1>Información personal</h1>
                <p>Mantén actualizados tus datos principales y de contacto.</p>
            </div>
        </header>

        <nav class="customer-account-nav" aria-label="Secciones de mi cuenta">
            <a href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">Resumen</a>
            <a href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">Mis pedidos</a>
            <a href="<?= e(appUrl('public/clientes/fichas-alimentacion.php')) ?>">Mis fichas de alimentación</a>
            <a class="active" href="<?= e(appUrl('public/clientes/perfil.php')) ?>">Mis datos</a>
            <a href="<?= e(appUrl('public/clientes/seguridad.php')) ?>">Seguridad</a>
        </nav>

        <?php if (is_array($estado)): ?>
            <div class="customer-feedback customer-feedback--<?= e((string) ($estado['tipo'] ?? 'ok')) ?>">
                <?= e((string) ($estado['mensaje'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <section class="customer-panel customer-form-panel">
            <header>
                <div>
                    <span>PERFIL</span>
                    <h2>Datos de tu cuenta</h2>
                </div>
            </header>

            <form class="customer-form customer-form--two-columns" method="post" action="<?= e(appUrl('public/clientes/actualizar-perfil.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <label class="customer-field">
                    <span>Nombre</span>
                    <input name="nombre" maxlength="100" required value="<?= e((string) $cliente['nombre']) ?>">
                </label>

                <label class="customer-field">
                    <span>Apellido</span>
                    <input name="apellido" maxlength="100" required value="<?= e((string) ($cliente['apellido'] ?? '')) ?>">
                </label>

                <label class="customer-field">
                    <span>Correo</span>
                    <input type="email" name="email" maxlength="160" required value="<?= e((string) $cliente['email']) ?>">
                </label>

                <label class="customer-field">
                    <span>Teléfono</span>
                    <input name="telefono" maxlength="40" value="<?= e((string) ($cliente['telefono'] ?? '')) ?>">
                </label>

                <label class="customer-field">
                    <span>RUT <small>Opcional</small></span>
                    <input name="rut" maxlength="20" value="<?= e((string) ($cliente['rut'] ?? '')) ?>">
                </label>

                <label class="customer-field">
                    <span>Región <small>Opcional</small></span>
                    <input name="region" maxlength="100" value="<?= e((string) ($cliente['region'] ?? '')) ?>">
                </label>

                <label class="customer-field">
                    <span>Comuna <small>Opcional</small></span>
                    <input name="comuna" maxlength="100" value="<?= e((string) ($cliente['comuna'] ?? '')) ?>">
                </label>

                <label class="customer-field customer-field--full">
                    <span>Dirección <small>Opcional</small></span>
                    <input name="direccion" maxlength="500" value="<?= e((string) ($cliente['direccion'] ?? '')) ?>">
                </label>

                <div class="customer-field--full customer-form-actions">
                    <button class="button customer-primary-action" type="submit">
                        Guardar cambios
                    </button>
                </div>
            </form>
        </section>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
