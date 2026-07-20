<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/consultas-presentaciones.php';
require_once __DIR__ . '/includes/validaciones-presentacion.php';
requireAuthentication();
$productId = idPositivoPresentacion($_GET['id_producto'] ?? null);
try {
    $product = $productId === null ? null : buscarProductoFraccionable(database(), $productId);
} catch (Throwable $exception) {
    error_log('Presentation create load error: ' . $exception->getMessage());
    $product = null;
}
if ($product === null) {
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=presentaciones_no_disponibles'), true, 302);
    exit;
}
$state = consumirEstadoPresentacion('presentacion_crear_' . $productId);
$values = array_merge(valoresInicialesPresentacion(), $state['valores'] ?? []);
$errors = $state['errores'] ?? [];
$generalError = $state['error_general'] ?? null;
$csrfToken = csrfToken();
$pageTitle = 'Agregar presentación';
$activeSection = 'inventario';
require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div><a class="admin-back-link"
                href="<?= escape(appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $productId)) ?>">←
                Volver a presentaciones</a>
            <h1 class="admin-page-title admin-page-title--paw">Agregar presentación</h1>
            <p><?= escape((string) $product['nombre']) ?></p>
        </div>
    </header>
    <?php if ($errors !== [] || $generalError): ?>
        <div class="admin-alert admin-alert--error" role="alert">
            <?= escape((string) ($generalError ?? 'Revisa los campos indicados.')) ?></div><?php endif; ?>
    <form class="admin-product-form" method="post"
        action="<?= escape(appUrl('admin/inventario/presentaciones/guardar.php')) ?>"><input type="hidden"
            name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="id_producto"
            value="<?= $productId ?>">
        <section class="admin-panel">
            <div class="admin-panel__header">
                <h2>Información de la presentación</h2>
            </div>
            <div class="admin-form-grid">
                <?php foreach (['nombre' => ['Nombre', 'Ej.: Bolsa 1 kg'], 'cantidad_gramos' => ['Cantidad en gramos', 'Ej.: 1000'], 'precio_venta' => ['Precio de venta', 'Ej.: 8990'], 'sku' => ['SKU (opcional)', 'Ej.: ACA-1KG'], 'orden' => ['Orden', 'Ej.: 1']] as $field => [$label, $placeholder]): ?>
                    <div class="admin-field<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>"><label
                            for="<?= $field ?>"><?= $label ?></label><input id="<?= $field ?>" name="<?= $field ?>"
                            type="<?= in_array($field, ['cantidad_gramos', 'precio_venta', 'orden'], true) ? 'number' : 'text' ?>"
                            <?= in_array($field, ['cantidad_gramos', 'precio_venta', 'orden'], true) ? 'min="0" step="1"' : '' ?> placeholder="<?= $placeholder ?>" value="<?= escape((string) $values[$field]) ?>" <?= $field !== 'sku' ? 'required' : '' ?>><?php if (isset($errors[$field])): ?><span
                                class="admin-field__error"><?= escape((string) $errors[$field]) ?></span><?php endif; ?></div>
                <?php endforeach; ?>
                <div class="admin-field"><label><input name="activo" type="checkbox" value="1" <?= $values['activo'] ? 'checked' : '' ?>> Presentación activa</label></div>
            </div>
        </section>
        <section class="admin-panel admin-form-actions"><a class="admin-button"
                href="<?= escape(appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $productId)) ?>">Cancelar</a><button
                class="admin-button admin-button--primary" type="submit">Guardar presentación</button></section>
    </form>
    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>