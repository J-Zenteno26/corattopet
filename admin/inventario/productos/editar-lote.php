<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/funciones-inventario.php';
require_once __DIR__ . '/includes/consultas-lotes-producto.php';

requireAuthentication();
$lotId = filter_var($_GET['id_lote'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($lotId === false) {
    guardarModalAdmin('error', 'Lote inválido', 'El lote indicado no es válido.');
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 302);
    exit;
}

try {
    $connection = database();
    $lot = obtenerLoteEditable($connection, $lotId);
    $suppliers = obtenerProveedoresParaLote($connection);
} catch (Throwable $exception) {
    error_log('Lot edit load error: ' . $exception->getMessage());
    $lot = null;
    $suppliers = [];
}
if ($lot === null) {
    guardarModalAdmin('error', 'Lote no encontrado', 'El lote indicado no existe.');
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 302);
    exit;
}

$stateKey = 'lote_editar_' . $lotId;
$state = $_SESSION[$stateKey] ?? [];
unset($_SESSION[$stateKey]);
$values = array_merge([
    'codigo_lote' => (string) $lot['codigo_lote'],
    'fecha_elaboracion' => (string) ($lot['fecha_elaboracion'] ?? ''),
    'fecha_vencimiento' => (string) $lot['fecha_vencimiento'],
    'id_proveedor' => $lot['id_proveedor'] === null ? '' : (string) $lot['id_proveedor'],
], is_array($state['values'] ?? null) ? $state['values'] : []);
$errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
$generalError = is_string($state['general_error'] ?? null) ? $state['general_error'] : null;
$duplicateWarning = existeCodigoLoteProducto($connection, (int) $lot['id_producto'], $values['codigo_lote'], $lotId);
$lotState = estadoFechaLoteInventario((string) $lot['fecha_vencimiento']);
$csrfToken = csrfToken();
$pageTitle = 'Editar lote';
$activeSection = 'inventario';
require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<link rel="stylesheet"
    href="<?= escape(appUrl('public/css/admin-lot-edit.css') . '?v=' . filemtime(dirname(__DIR__, 3) . '/public/css/admin-lot-edit.css')) ?>">
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div><a class="admin-back-link" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">← Volver al
                inventario</a>
            <h1 class="admin-page-title admin-page-title--paw">Editar lote</h1>
            <p>Actualiza únicamente los datos de identificación y vencimiento.</p>
        </div>
    </header>
    <?php if ($generalError !== null): ?>
        <div class="admin-alert admin-alert--error" role="alert"><?= escape($generalError) ?></div><?php endif; ?>
    <?php if ($duplicateWarning): ?>
        <div class="admin-lot-edit-warning" role="status"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <div><strong>Código repetido para este producto</strong><span>Existe otro lote con el código
                    <?= escape($values['codigo_lote']) ?>. Esto puede ser válido históricamente; revisa el dato antes de
                    guardar.</span></div>
        </div><?php endif; ?>
    <section class="admin-lot-edit-readonly" aria-label="Datos actuales del lote">
        <article><span>Producto</span><strong><?= escape((string) $lot['producto']) ?></strong></article>
        <article><span>Cantidad
                inicial</span><strong><?= escape(formatearPesoLoteInventario($lot['cantidad_inicial_g'])) ?></strong>
        </article>
        <article><span>Cantidad
                disponible</span><strong><?= escape(formatearPesoLoteInventario($lot['cantidad_disponible_g'])) ?></strong>
        </article>
        <article><span>Estado actual</span><span
                class="admin-lot-status is-<?= escape($lotState['key']) ?>"><?= escape($lotState['label']) ?></span>
        </article>
    </section>
    <form class="admin-lot-edit-form" method="post"
        action="<?= escape(appUrl('admin/inventario/productos/actualizar-lote.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="id_lote"
            value="<?= $lotId ?>">
        <section class="admin-panel">
            <div class="admin-panel__header">
                <h2>Metadatos del lote</h2>
            </div>
            <div class="admin-lot-edit-grid">
                <div class="admin-field<?= isset($errors['codigo_lote']) ? ' admin-field--invalid' : '' ?>"><label
                        for="codigo_lote">Código de lote</label><input id="codigo_lote" name="codigo_lote"
                        maxlength="80" required
                        value="<?= escape($values['codigo_lote']) ?>"><?php if (isset($errors['codigo_lote'])): ?><span
                            class="admin-field__error"><?= escape((string) $errors['codigo_lote']) ?></span><?php endif; ?>
                </div>
                <div class="admin-field<?= isset($errors['id_proveedor']) ? ' admin-field--invalid' : '' ?>"><label
                        for="id_proveedor">Proveedor</label><select id="id_proveedor" name="id_proveedor">
                        <option value="">Sin proveedor</option><?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= (int) $supplier['id_proveedor'] ?>" <?= $values['id_proveedor'] === (string) $supplier['id_proveedor'] ? 'selected' : '' ?>>
                                <?= escape((string) $supplier['nombre'] . ($supplier['rut'] ? ' · ' . $supplier['rut'] : '') . (!$supplier['activo'] ? ' · Inactivo' : '')) ?>
                            </option><?php endforeach; ?>
                    </select><?php if (isset($errors['id_proveedor'])): ?><span
                            class="admin-field__error"><?= escape((string) $errors['id_proveedor']) ?></span><?php endif; ?>
                </div>
                <div class="admin-field<?= isset($errors['fecha_elaboracion']) ? ' admin-field--invalid' : '' ?>"><label
                        for="fecha_elaboracion">Fecha de elaboración <span>(opcional)</span></label><input
                        id="fecha_elaboracion" name="fecha_elaboracion" type="date"
                        value="<?= escape($values['fecha_elaboracion']) ?>"><?php if (isset($errors['fecha_elaboracion'])): ?><span
                            class="admin-field__error"><?= escape((string) $errors['fecha_elaboracion']) ?></span><?php endif; ?>
                </div>
                <div class="admin-field<?= isset($errors['fecha_vencimiento']) ? ' admin-field--invalid' : '' ?>"><label
                        for="fecha_vencimiento">Fecha de vencimiento</label><input id="fecha_vencimiento"
                        name="fecha_vencimiento" type="date" required
                        value="<?= escape($values['fecha_vencimiento']) ?>"><?php if (isset($errors['fecha_vencimiento'])): ?><span
                            class="admin-field__error"><?= escape((string) $errors['fecha_vencimiento']) ?></span><?php endif; ?>
                </div>
            </div>
        </section>
        <section class="admin-panel admin-form-actions"><a class="admin-button"
                href="<?= escape(appUrl('admin/inventario/index.php')) ?>">Cancelar</a><button
                class="admin-button admin-button--primary" type="submit">Guardar metadatos</button></section>
    </form>
    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>