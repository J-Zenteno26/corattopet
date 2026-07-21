<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/funciones-importacion.php';
requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $_SESSION['inventario_importacion_error'] = 'La solicitud no es válida. Recarga la pantalla e intenta nuevamente.';
        header('Location: ' . appUrl('admin/inventario/importar/index.php'), true, 303); exit;
    }
    $file = $_FILES['archivo_excel'] ?? null;
    $error = null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) $error = 'Selecciona un archivo XLSX válido.';
    elseif ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > IMPORT_MAX_BYTES) $error = 'El archivo debe pesar entre 1 byte y 5 MB.';
    elseif (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'xlsx') $error = 'Solo se permiten archivos con extensión .xlsx.';
    elseif (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) $error = 'No fue posible verificar el archivo subido.';
    else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], true)) $error = 'El contenido del archivo no corresponde a un XLSX válido.';
    }
    if ($error !== null) {
        $_SESSION['inventario_importacion_error'] = $error;
        header('Location: ' . appUrl('admin/inventario/importar/index.php'), true, 303); exit;
    }
    try {
        $result = validarLibroImportacion(database(), leerHojasXlsx((string) $file['tmp_name']));
        $token = guardarImportacionValidada($result);
        header('Location: ' . appUrl('admin/inventario/importar/validar.php?token=' . $token), true, 303); exit;
    } catch (Throwable $exception) {
        error_log('Excel validation error: ' . $exception->getMessage());
        $_SESSION['inventario_importacion_error'] = 'No fue posible leer el archivo. Verifica que sea un XLSX válido.';
        header('Location: ' . appUrl('admin/inventario/importar/index.php'), true, 303); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); exit; }

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$result = preg_match('/^[a-f0-9]{48}$/', $token) === 1 ? cargarImportacionValidada($token) : null;
if ($result === null) {
    $_SESSION['inventario_importacion_error'] = 'La previsualización no existe o expiró. Vuelve a subir el archivo.';
    header('Location: ' . appUrl('admin/inventario/importar/index.php'), true, 302); exit;
}
$hasErrors = (int) $result['resumen']['errores'] > 0;
$confirmationError = $_SESSION['inventario_importacion_confirmacion_error'] ?? null;
unset($_SESSION['inventario_importacion_confirmacion_error']);
$products = is_array($result['productos'] ?? null) ? $result['productos'] : [];
$presentations = is_array($result['presentaciones'] ?? null) ? $result['presentaciones'] : [];
$previewProducts = array_slice($products, 0, 50);
$previewPresentations = array_slice($presentations, 0, 50);
$hasFractionable = $hasNormalProducts = $hasPresentationsForExistingProducts = false;
$fractionableSkus = $presentationBaseSkus = [];
foreach ($products as $product) {
    if (!empty($product['fraccionable'])) { $hasFractionable = true; if ((string) $product['sku'] !== '') $fractionableSkus[mb_strtolower((string) $product['sku'])] = true; }
    else $hasNormalProducts = true;
}
foreach ($presentations as $presentation) {
    $presentationBaseSkus[(string) $presentation['sku_producto_base']] = true;
    if (!empty($presentation['producto_existente'])) $hasPresentationsForExistingProducts = true;
}
$hasFractionableWithoutPresentations = array_diff_key($fractionableSkus, $presentationBaseSkus) !== [];
$showTechnicalDetail = in_array((string) env('APP_ENV', 'production'), ['local', 'development'], true)
    || filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
$adminModal = null;
if (is_array($confirmationError)) {
    $adminModal = [
        'type' => 'error', 'title' => 'No fue posible completar la importación',
        'message' => 'No se insertó ningún producto.',
        'reference' => (string) ($confirmationError['referencia'] ?? ''),
        'detail' => $showTechnicalDetail ? (string) ($confirmationError['detalle'] ?? '') : '',
        'primaryText' => 'Volver a la vista previa',
    ];
} elseif ($hasErrors) {
    $adminModal = [
        'type' => 'warning', 'title' => 'Archivo con errores',
        'message' => 'Corrige los errores indicados antes de confirmar la importación.',
        'primaryText' => 'Revisar errores',
    ];
}
$csrfToken = csrfToken(); $pageTitle = 'Validar importación Excel'; $activeSection = 'inventario';
require dirname(__DIR__, 3) . '/shared/admin-header.php'; require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header"><div><h1 class="admin-page-title">Previsualización de importación</h1><p>Revisa el resultado antes de confirmar cualquier cambio.</p></div><a class="admin-button" href="<?= escape(appUrl('admin/inventario/importar/index.php')) ?>">Subir otro archivo</a></header>
    <section class="admin-import-summary" aria-label="Resumen de validación">
        <article class="admin-import-card admin-import-card--products"><span>PRODUCTOS DETECTADOS</span><strong><?= escape((string) $result['resumen']['productos_detectados']) ?></strong></article>
        <article class="admin-import-card admin-import-card--valid"><span>PRODUCTOS VÁLIDOS</span><strong><?= escape((string) $result['resumen']['productos_validos']) ?></strong></article>
        <article class="admin-import-card admin-import-card--presentations"><span>PRESENTACIONES VÁLIDAS</span><strong><?= escape((string) $result['resumen']['presentaciones_validas']) ?></strong></article>
        <article class="admin-import-card admin-import-card--errors"><span>ERRORES</span><strong><?= escape((string) $result['resumen']['errores']) ?></strong></article>
    </section>
    <?php if ($hasErrors): ?>
        <div class="admin-alert admin-alert--error" role="alert"><strong>Corrige el Excel antes de importarlo.</strong><p>No se insertó ningún dato.</p></div>
        <?php foreach (['errores_generales' => 'Generales', 'errores_productos' => 'Productos', 'errores_presentaciones' => 'Presentaciones'] as $key => $label): if (($result[$key] ?? []) !== []): ?>
            <section class="admin-panel"><div class="admin-panel__header"><h2><?= escape($label) ?></h2></div><ul><?php foreach ($result[$key] as $item): ?><li><?= is_array($item) ? 'Fila ' . escape((string) $item['fila']) . ': ' . escape((string) $item['mensaje']) : escape((string) $item) ?></li><?php endforeach; ?></ul></section>
        <?php endif; endforeach; ?>
    <?php endif; ?>
    <?php if ($hasFractionable || $hasNormalProducts || $hasPresentationsForExistingProducts): ?><div class="admin-import-notices" aria-label="Avisos de importación">
        <?php if ($hasFractionable): ?><p class="admin-import-notice">Los alimentos se importarán como productos base con stock en gramos y precio base 0. Sus precios se tomarán desde Presentaciones.</p><?php endif; ?>
        <?php if ($hasFractionableWithoutPresentations): ?><p class="admin-import-notice">Hay alimentos válidos sin presentaciones en este archivo.</p><?php endif; ?>
        <?php if ($hasPresentationsForExistingProducts): ?><p class="admin-import-notice">Hay presentaciones asociadas a productos existentes.</p><?php endif; ?>
        <?php if ($hasNormalProducts): ?><p class="admin-import-notice">Hay productos por unidad; estos no requieren presentaciones.</p><?php endif; ?>
    </div><?php endif; ?>
    <section class="admin-import-preview" aria-labelledby="import-preview-title">
        <div class="admin-import-preview__header"><h2 id="import-preview-title">Vista previa de importación</h2><p>Estos son los productos y presentaciones que se insertarían si confirmas la importación.</p></div>
        <section aria-labelledby="preview-products-title"><h3 id="preview-products-title">Productos a insertar</h3>
        <?php if ($previewProducts === []): ?><p class="admin-import-empty">No hay productos válidos para mostrar.</p><?php else: ?><div class="admin-table-wrap"><table class="admin-import-preview-table"><thead><tr><th>PRODUCTO</th><th>IDENTIFICACIÓN</th><th>CLASIFICACIÓN</th><th>PRECIO</th><th>STOCK INICIAL</th><th>STOCK MÍNIMO</th><th>ESTADO</th><th>RESULTADO</th></tr></thead><tbody>
        <?php foreach ($previewProducts as $product): $fractionable = !empty($product['fraccionable']); ?><tr>
            <td><strong><?= escape((string) $product['nombre']) ?></strong><small><?= escape(textoTipoProductoImportacion($fractionable)) ?></small></td>
            <td><span>SKU: <?= escape($product['sku'] === '' ? 'Sin SKU' : (string) $product['sku']) ?></span><small>Código: <?= escape($product['codigo_barras'] === '' ? 'Sin código' : (string) $product['codigo_barras']) ?></small></td>
            <td><strong><?= escape((string) $product['categoria']) ?></strong><small><?= escape((string) $product['marca']) ?> · <?= escape(etiquetaTipoMascotaImportacion((string) $product['tipo_mascota'])) ?></small></td>
            <td><?= $fractionable ? '<span class="admin-price-badge admin-price-badge--presentation">Por presentación</span>' : '<strong>' . escape(formatearPrecioImportacion($product['precio_venta'])) . '</strong>' ?></td>
            <td><?= escape(formatearStockImportacion((int) $product['stock_inicial'], $fractionable)) ?></td><td><?= escape(formatearStockImportacion($fractionable ? 5000 : 5, $fractionable)) ?></td>
            <td><span class="admin-status-badge <?= $product['estado'] === 'activo' ? 'is-active' : 'is-inactive' ?>"><?= escape(formatearEstadoImportacion((string) $product['estado'])) ?></span></td><td><span class="admin-import-result">Producto nuevo</span></td>
        </tr><?php endforeach; ?></tbody></table></div><?php if (count($products) > 50): ?><p class="admin-import-count">Mostrando 50 de <?= escape((string) count($products)) ?> productos validados.</p><?php endif; endif; ?></section>
        <section aria-labelledby="preview-presentations-title"><h3 id="preview-presentations-title">Presentaciones a insertar</h3>
        <?php if ($previewPresentations === []): ?><p class="admin-import-empty">Este archivo no contiene presentaciones para importar.</p><?php else: ?><div class="admin-table-wrap"><table class="admin-import-preview-table"><thead><tr><th>PRODUCTO BASE</th><th>PRESENTACIÓN</th><th>CANTIDAD</th><th>PRECIO</th><th>SKU PRESENTACIÓN</th><th>ESTADO</th><th>ORDEN</th></tr></thead><tbody>
        <?php foreach ($previewPresentations as $presentation): ?><tr><td><strong><?= escape((string) $presentation['sku_producto_base']) ?></strong><?= !empty($presentation['producto_existente']) ? '<small>Producto existente</small>' : '' ?></td><td><?= escape((string) $presentation['nombre_presentacion']) ?></td><td><?= escape(formatearStockImportacion((int) $presentation['cantidad_gramos'], true)) ?></td><td><strong><?= escape(formatearPrecioImportacion($presentation['precio_venta'])) ?></strong></td><td><?= escape($presentation['sku_presentacion'] === '' ? 'Sin SKU' : (string) $presentation['sku_presentacion']) ?></td><td><?= !empty($presentation['activo']) ? 'Activa' : 'Inactiva' ?></td><td><?= escape((string) $presentation['orden']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php if (count($presentations) > 50): ?><p class="admin-import-count">Mostrando 50 de <?= escape((string) count($presentations)) ?> presentaciones validadas.</p><?php endif; endif; ?></section>
    </section>
    <?php if (!$hasErrors): ?><div class="admin-import-confirm"><p>Al confirmar, se insertarán los productos y presentaciones mostrados. Esta acción usará una transacción: si ocurre un error, no se guardará nada.</p><form id="confirmar-importacion-form" method="post" action="<?= escape(appUrl('admin/inventario/importar/confirmar.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="token" value="<?= escape($token) ?>"><button class="admin-button admin-button--primary" type="button" data-admin-confirm-form="confirmar-importacion-form" data-modal-title="Confirmar importación" data-modal-message="Se insertarán los productos y presentaciones mostrados en la vista previa. Si ocurre un error, no se guardará nada." data-modal-primary="Confirmar importación" data-modal-secondary="Cancelar">Confirmar importación</button></form></div><?php endif; ?>
<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
