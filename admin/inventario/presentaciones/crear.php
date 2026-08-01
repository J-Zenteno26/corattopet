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
    $product = $productId === null ? null : buscarProductoFraccionable($connection, $productId);
    $lots = $product === null ? [] : listarLotesConSaldoPresentacion($connection, $productId);
} catch (Throwable $exception) {
    error_log('Presentation create load error: ' . $exception->getMessage());
    $product = null;
    $lots = [];
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
        <section class="admin-panel admin-presentation-stock" aria-labelledby="presentation-stock-title">
            <div class="admin-panel__header"><h2 id="presentation-stock-title">Stock inicial desde lote</h2><p class="admin-panel__intro">Opcional. Puedes crear la presentación sin unidades y asignar stock después.</p></div>
            <div class="admin-form-grid">
                <div class="admin-field<?= isset($errors['id_lote']) ? ' admin-field--invalid' : '' ?>"><label for="id_lote">Lote de origen</label><select id="id_lote" name="id_lote"><option value="">Sin stock inicial</option><?php foreach($lots as $lot): ?><option value="<?= (int)$lot['id_lote'] ?>" data-saldo="<?= escape((string)$lot['saldo_no_asignado_g']) ?>" <?= (string)$values['id_lote']===(string)$lot['id_lote']?'selected':'' ?>><?= escape((string)$lot['codigo_lote']) ?> · vence <?= escape((new DateTimeImmutable((string)$lot['fecha_vencimiento']))->format('d-m-Y')) ?> · <?= escape(number_format((float)$lot['saldo_no_asignado_g'],3,',','.')) ?> g disponibles</option><?php endforeach; ?></select><?php if(isset($errors['id_lote'])):?><span class="admin-field__error"><?= escape((string)$errors['id_lote']) ?></span><?php endif;?><?php if($lots===[]):?><span class="admin-field__help">No hay lotes vigentes con saldo sin asignar.</span><?php endif;?></div>
                <div class="admin-field<?= isset($errors['unidades_iniciales']) ? ' admin-field--invalid' : '' ?>"><label for="unidades_iniciales">Unidades a crear</label><input id="unidades_iniciales" name="unidades_iniciales" type="number" min="0" step="1" value="<?= escape((string)$values['unidades_iniciales']) ?>"><?php if(isset($errors['unidades_iniciales'])):?><span class="admin-field__error"><?= escape((string)$errors['unidades_iniciales']) ?></span><?php endif;?></div>
                <div class="admin-field admin-field--full"><div class="admin-presentation-calculation" id="presentation-calculation" role="status"><span>Selecciona un lote e ingresa unidades para calcular la asignación.</span></div></div>
            </div>
        </section>
        <section class="admin-panel admin-form-actions"><a class="admin-button"
                href="<?= escape(appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $productId)) ?>">Cancelar</a><button
                class="admin-button admin-button--primary" type="submit">Guardar presentación</button></section>
    </form>
    <script>(()=>{const lot=document.getElementById('id_lote'),units=document.getElementById('unidades_iniciales'),grams=document.getElementById('cantidad_gramos'),output=document.getElementById('presentation-calculation');if(!lot||!units||!grams||!output)return;const format=value=>new Intl.NumberFormat('es-CL',{maximumFractionDigits:3}).format(value);const update=()=>{const balance=Number(lot.selectedOptions[0]?.dataset.saldo||0),quantity=Number(units.value||0),weight=Number(grams.value||0),used=quantity*weight,remaining=balance-used;output.classList.toggle('is-invalid',used>balance&&quantity>0);output.innerHTML=lot.value===''?'<span>Selecciona un lote si deseas crear stock inicial.</span>':`<span>Gramos usados</span><strong>${format(used)} g</strong><span>Saldo restante</span><strong>${format(remaining)} g</strong>${used>balance?'<em>La asignación excede el saldo disponible.</em>':''}`;};lot.addEventListener('change',update);units.addEventListener('input',update);grams.addEventListener('input',update);update();})();</script>
    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
