<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/consultas-presentaciones.php';
require_once __DIR__ . '/includes/validaciones-presentacion.php';
requireAuthentication();
$presentationId = idPositivoPresentacion($_GET['id'] ?? null);
try {
    $connection = database();
    $presentation = $presentationId === null ? null : buscarPresentacion($connection, $presentationId);
    $product = $presentation === null ? null : buscarProductoFraccionable($connection, (int) $presentation['id_producto']);
} catch (Throwable $exception) {
    error_log('Presentation edit load error: ' . $exception->getMessage());
    $presentation = null;
    $product = null;
}
if ($presentation === null || $product === null) {
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=presentaciones_no_disponibles'), true, 302);
    exit;
}
$key = 'presentacion_editar_' . $presentationId;
$state = consumirEstadoPresentacion($key);
$values = array_merge(valoresInicialesPresentacion(), $presentation, $state['valores'] ?? []);
$errors = $state['errores'] ?? [];
$generalError = $state['error_general'] ?? null;
$csrfToken = csrfToken();
$pageTitle = 'Editar presentación';
$activeSection = 'inventario';
require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div><a class="admin-back-link"
                href="<?= escape(appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $presentation['id_producto'])) ?>">←
                Volver a presentaciones</a>
            <h1 class="admin-page-title admin-page-title--paw">Editar presentación</h1>
            <p><?= escape((string) $product['nombre']) ?></p>
        </div>
    </header>
    <?php if ($errors !== [] || $generalError): ?>
        <div class="admin-alert admin-alert--error" role="alert">
            <?= escape((string) ($generalError ?? 'Revisa los campos indicados.')) ?></div><?php endif; ?>
    <form class="admin-product-form" method="post"
        action="<?= escape(appUrl('admin/inventario/presentaciones/actualizar.php')) ?>"><input type="hidden"
            name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="id_presentacion"
            value="<?= $presentationId ?>">
        <section class="admin-panel">
            <div class="admin-panel__header">
                <h2>Información de la presentación</h2>
            </div>
            <div class="admin-form-grid">
                <?php foreach (['nombre' => 'Nombre', 'cantidad_gramos' => 'Cantidad en gramos', 'precio_venta' => 'Precio de venta', 'sku' => 'SKU (opcional)', 'orden' => 'Orden'] as $field => $label): ?>
                    <div class="admin-field<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>"><label
                            for="<?= $field ?>"><?= $label ?></label><input id="<?= $field ?>" name="<?= $field ?>"
                            type="<?= in_array($field, ['cantidad_gramos', 'precio_venta', 'orden'], true) ? 'number' : 'text' ?>"
                            <?= in_array($field, ['cantidad_gramos', 'precio_venta', 'orden'], true) ? 'min="0" step="1"' : '' ?>
                            value="<?= escape((string) $values[$field]) ?>"
                            <?= $field !== 'sku' ? 'required' : '' ?>><?php if (isset($errors[$field])): ?><span
                                class="admin-field__error"><?= escape((string) $errors[$field]) ?></span><?php endif; ?></div>
                <?php endforeach; ?>
                <div class="admin-field"><label><input name="activo" type="checkbox" value="1"
                            <?= $values['activo'] ? 'checked' : '' ?>> Presentación activa</label></div>
            </div>
        </section>
        <section class="admin-panel admin-form-actions"><a class="admin-button"
                href="<?= escape(appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $presentation['id_producto'])) ?>">Cancelar</a><button
                class="admin-button admin-button--primary" type="submit">Actualizar presentación</button></section>
    </form>
    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>