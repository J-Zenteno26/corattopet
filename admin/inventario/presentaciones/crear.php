<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/consultas-presentaciones.php';
require_once __DIR__ . '/includes/validaciones-presentacion.php';

requireAuthentication();

$productId = idPositivoPresentacion($_GET['id_producto'] ?? null);

try {
    $connection = database();
    $product = $productId === null
        ? null
        : buscarProductoFraccionable($connection, $productId);
} catch (Throwable $exception) {
    error_log('Presentation create load error: ' . $exception->getMessage());
    $product = null;
}

if ($product === null) {
    header(
        'Location: ' . appUrl('admin/inventario/index.php?mensaje=presentaciones_no_disponibles'),
        true,
        302
    );
    exit;
}

$state = consumirEstadoPresentacion('presentacion_crear_' . $productId);

$values = array_merge(
    valoresInicialesPresentacion(),
    is_array($state['valores'] ?? null) ? $state['valores'] : []
);

$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null)
    ? $state['error_general']
    : null;

$csrfToken = csrfToken();
$pageTitle = 'Agregar presentación';
$activeSection = 'inventario';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>

<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="<?= escape(appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $productId)) ?>">
                ← Volver a presentaciones
            </a>

            <h1 class="admin-page-title admin-page-title--paw">Agregar presentación</h1>
            <p><?= escape((string) $product['nombre']) ?></p>
        </div>
    </header>

    <?php if ($errors !== [] || $generalError !== null): ?>
        <div class="admin-alert admin-alert--error" role="alert">
            <?= escape((string) ($generalError ?? 'Revisa los campos indicados.')) ?>
        </div>
    <?php endif; ?>

    <form class="admin-product-form" method="post" action="<?= escape(appUrl('admin/inventario/presentaciones/guardar.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
        <input type="hidden" name="id_producto" value="<?= (int) $productId ?>">

        <section class="admin-panel">
            <div class="admin-panel__header">
                <h2>Información de la presentación</h2>
                <p class="admin-panel__intro">
                    Define el formato comercial del producto. El stock físico se gestiona por lotes desde Gestión de stock.
                </p>
            </div>

            <div class="admin-form-grid">
                <div class="admin-field<?= isset($errors['nombre']) ? ' admin-field--invalid' : '' ?>">
                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="120" placeholder="Ej.: Bolsa 1 kg" value="<?= escape((string) $values['nombre']) ?>" required>
                    <?php if (isset($errors['nombre'])): ?>
                        <span class="admin-field__error"><?= escape((string) $errors['nombre']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['cantidad_gramos']) ? ' admin-field--invalid' : '' ?>">
                    <label for="cantidad_gramos">Cantidad en gramos</label>
                    <input id="cantidad_gramos" name="cantidad_gramos" type="number" min="1" step="1" placeholder="Ej.: 1000" value="<?= escape((string) $values['cantidad_gramos']) ?>" required>
                    <?php if (isset($errors['cantidad_gramos'])): ?>
                        <span class="admin-field__error"><?= escape((string) $errors['cantidad_gramos']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['precio_venta']) ? ' admin-field--invalid' : '' ?>">
                    <label for="precio_venta">Precio de venta</label>
                    <input id="precio_venta" name="precio_venta" type="number" min="0" step="1" placeholder="Ej.: 8990" value="<?= escape((string) $values['precio_venta']) ?>" required>
                    <?php if (isset($errors['precio_venta'])): ?>
                        <span class="admin-field__error"><?= escape((string) $errors['precio_venta']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['sku']) ? ' admin-field--invalid' : '' ?>">
                    <label for="sku">SKU <span>(opcional)</span></label>
                    <input id="sku" name="sku" type="text" maxlength="100" placeholder="Ej.: ACA-1KG" value="<?= escape((string) $values['sku']) ?>">
                    <?php if (isset($errors['sku'])): ?>
                        <span class="admin-field__error"><?= escape((string) $errors['sku']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['orden']) ? ' admin-field--invalid' : '' ?>">
                    <label for="orden">Orden</label>
                    <input id="orden" name="orden" type="number" min="0" step="1" value="<?= escape((string) $values['orden']) ?>" required>
                    <?php if (isset($errors['orden'])): ?>
                        <span class="admin-field__error"><?= escape((string) $errors['orden']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="admin-field">
                    <label>
                        <input name="activo" type="checkbox" value="1" <?= !empty($values['activo']) ? 'checked' : '' ?>>
                        Presentación activa
                    </label>
                </div>
            </div>
        </section>

        <section class="admin-panel admin-form-actions">
            <a class="admin-button" href="<?= escape(appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $productId)) ?>">Cancelar</a>
            <button class="admin-button admin-button--primary" type="submit">Guardar presentación</button>
        </section>
    </form>
</main>

<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>