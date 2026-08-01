<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-lotes.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/proveedores/includes/consultas-proveedores.php';
require_once __DIR__ . '/includes/funciones-stock.php';
require_once __DIR__ . '/includes/validaciones-stock.php';
require_once __DIR__ . '/consultas/buscar-producto-stock.php';
require_once __DIR__ . '/consultas/listar-movimientos-producto.php';

requireAuthentication();

$productId = idPositivoStock($_GET['id'] ?? null);

if ($productId === null) {
    guardarModalAdmin('error', 'No fue posible abrir la gestión de stock', 'El producto indicado no es válido.');
    header(
        'Location: ' . appUrl(
            'admin/inventario/index.php'
        ),
        true,
        302
    );
    exit;
}

try {
    $connection = database();

    $product = buscarProductoStock(
        $connection,
        $productId
    );

    if ($product === null) {
        guardarModalAdmin('error', 'No fue posible abrir la gestión de stock', 'El producto indicado no existe.');
        header(
            'Location: ' . appUrl(
                'admin/inventario/index.php'
            ),
            true,
            302
        );
        exit;
    }

    $movements = listarMovimientosProducto(
        $connection,
        $productId
    );
    $presentations = presentacionesActivasProducto($connection, $productId);
    $lotCountStatement = $connection->prepare('SELECT COUNT(*) FROM stock_lotes WHERE id_producto=:id AND activo=TRUE');
    $lotCountStatement->execute(['id'=>$productId]);
    $activeLotCount = (int)$lotCountStatement->fetchColumn();
    $suppliers = todosProveedoresActivos($connection);
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Stock page query error', $exception);
    guardarModalAdmin('error', 'No fue posible abrir la gestión de stock', 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', ['reference' => $reference]);

    header(
        'Location: ' . appUrl(
            'admin/inventario/index.php'
        ),
        true,
        302
    );
    exit;
}

$state = consumirEstadoMovimientoStock($productId);

$values = array_merge(
    valoresInicialesMovimientoStock(),
    is_array($state['valores'] ?? null)
        ? $state['valores']
        : []
);

$errors = is_array($state['errores'] ?? null)
    ? $state['errores']
    : [];

$generalError = is_string($state['error_general'] ?? null)
    ? $state['error_general']
    : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';
if ($errors !== [] || $generalError !== null) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible registrar el movimiento',
        'message' => $errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.',
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
}

$currentStock = (int) $product['cantidad_actual'];
$minimumStock = (int) $product['stock_minimo'];
$fractionable = esProductoFraccionable($product);
$presentations = $presentations ?? [];
$activeLotCount = $activeLotCount ?? 0;
$suppliers = $suppliers ?? [];
$selectedSupplierId = (string) ($state['valores']['id_proveedor'] ?? '');

$stockStatus = estadoStockProducto(
    $currentStock,
    $minimumStock,
    $fractionable
);

$statusClass = claseEstadoStockProducto(
    $currentStock,
    $minimumStock,
    $fractionable
);

$movementTypes = [
    'entrada' => 'Entrada',
    'salida' => 'Salida',
    'ajuste' => 'Ajuste',
];
$reasonsByType = motivosMovimientoStock();
$currentReasons = $reasonsByType[$values['tipo_movimiento']] ?? [];

$csrfToken = csrfToken();
$pageTitle = 'Gestionar stock';
$activeSection = 'inventario';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>

<main class="admin-main" id="contenido-principal">
    <?php if ($fractionable && $currentStock > 0 && $activeLotCount === 0): ?>
        <div class="admin-alert admin-alert--warning" role="alert"><strong>Stock antiguo sin lotes</strong><p>Este alimento seco tiene stock histórico. No se recalculó ni eliminó; regularízalo con lotes antes de vender por presentación.</p></div>
    <?php endif; ?>

    <header class="admin-page-header">
        <div>
            <a
                class="admin-back-link"
                href="<?= escape(
                    appUrl('admin/inventario/index.php')
                ) ?>"
            >
                ← Volver al inventario
            </a>

            <h1 class="admin-page-title admin-page-title--paw">
                Gestionar stock
            </h1>

            <p>
                <?= escape((string) $product['nombre']) ?>
            </p>
        </div>
    </header>

    <section
        class="admin-stock-module"
        aria-labelledby="stock-module-title"
    >

        <header class="admin-stock-header">
            <div>
                <span class="admin-stock-header__eyebrow">
                    Control de inventario
                </span>

                <h2 id="stock-module-title">
                    <?= escape((string) $product['nombre']) ?>
                </h2>

                <p class="admin-stock-meta">
                    <?php if (
                        $product['sku'] !== null
                        && $product['sku'] !== ''
                    ): ?>
                        <span>
                            SKU: <?= escape((string) $product['sku']) ?>
                        </span>
                    <?php endif; ?>

                    <span>
                        <?= escape((string) $product['categoria']) ?>
                    </span>

                    <span>
                        <?= escape(
                            $product['marca'] !== null
                                ? (string) $product['marca']
                                : 'Sin marca'
                        ) ?>
                    </span>
                </p>
            </div>
        </header>
        <div class="admin-stock-overview">

            <div class="admin-stock-overview__item">
                <span class="admin-stock-overview__label">
                    Stock actual
                </span>

                <strong class="admin-stock-overview__value">
                    <?= escape(formatearCantidadStock($currentStock, $fractionable)) ?>
                </strong>

                <span class="admin-stock-overview__detail">
                    <?= $fractionable ? 'Peso disponible' : 'Unidades disponibles' ?>
                </span>
            </div>

            <div class="admin-stock-overview__item">
                <span class="admin-stock-overview__label">
                    Stock mínimo
                </span>

                <strong class="admin-stock-overview__value">
                    <?= escape(formatearCantidadStock($minimumStock, $fractionable)) ?>
                </strong>

                <span class="admin-stock-overview__detail">
                    Nivel de alerta configurado
                </span>
            </div>

            <div class="admin-stock-overview__item">
                <span class="admin-stock-overview__label">
                    Estado
                </span>

                <div class="admin-stock-overview__status">
                    <span
                        class="admin-status-badge <?= escape(
                            $statusClass
                        ) ?>"
                    >
                        <?= escape($stockStatus) ?>
                    </span>
                </div>

                <span class="admin-stock-overview__detail">
                    Según la cantidad disponible
                </span>
            </div>

        </div>

        <section
            class="admin-stock-section"
            aria-labelledby="movement-form-title"
        >
            <header class="admin-stock-section__header">
                <div>
                    <h3 id="movement-form-title">
                        Registrar movimiento
                    </h3>

                    <p>
                        <?= $fractionable ? 'Entrada suma peso, salida descuenta y ajuste establece el peso real final.' : 'Entrada suma unidades, salida descuenta y ajuste establece el stock real final.' ?>
                    </p>
                </div>
            </header>

            <form
                id="form-movimiento-stock"
                class="admin-product-form"
                method="post"
                action="<?= escape(
                    appUrl(
                        'admin/inventario/stock/guardar-movimiento.php'
                    )
                ) ?>"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="id_producto"
                    value="<?= (int) $productId ?>"
                >
                <?php if ($fractionable && $activeLotCount === 0 && $currentStock > 0): ?>
                    <label class="admin-alert admin-alert--warning"><input id="confirmar_regularizacion" type="checkbox" name="confirmar_regularizacion" value="1" required> Confirmo que esta entrada regularizará el stock histórico y que el nuevo stock vendible quedará determinado solo por las unidades asignadas a lotes.</label>
                <?php endif; ?>

                <div class="admin-stock-form-grid">

                    <div
                        class="admin-field<?= isset(
                            $errors['tipo_movimiento']
                        )
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="tipo_movimiento">
                            Tipo de movimiento
                        </label>

                        <select
                            id="tipo_movimiento"
                            name="tipo_movimiento"
                            required
                        >
                            <option value="">
                                Seleccionar
                            </option>

                            <?php foreach (
                                $movementTypes as $type => $label
                            ): ?>
                                <option
                                    value="<?= escape($type) ?>"
                                    <?= $values['tipo_movimiento'] === $type
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= escape($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if (
                            isset($errors['tipo_movimiento'])
                        ): ?>
                            <span class="admin-field__error">
                                <?= escape(
                                    (string) $errors['tipo_movimiento']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div <?= $fractionable ? 'hidden' : '' ?>
                        class="admin-field<?= isset(
                            $errors['cantidad']
                        )
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="cantidad">
                            <?= $fractionable ? 'Cantidad en gramos' : 'Cantidad en unidades' ?>
                        </label>

                        <input
                            id="cantidad"
                            name="cantidad"
                            type="number"
                            inputmode="numeric"
                            min="0"
                            step="1"
                            placeholder="<?= $fractionable ? 'Ej.: 1000' : 'Ej.: 10' ?>"
                            required
                            <?= $fractionable ? 'disabled' : '' ?>
                            value="<?= escape(
                                (string) $values['cantidad']
                            ) ?>"
                        >

                        <?php if (isset($errors['cantidad'])): ?>
                            <span class="admin-field__error">
                                <?= escape(
                                    (string) $errors['cantidad']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-field">
                        <span class="admin-field__help"><?= $fractionable ? 'Ejemplo: 1 kg = 1000 g, 250 g = 250.' : 'Ingresa una cantidad entera de unidades.' ?></span>
                    </div>

                    <?php if ($fractionable): ?>
                    <div class="admin-field admin-field--full" id="salida-presentacion-fields" hidden>
                        <label for="id_presentacion">Presentación física</label>
                        <select id="id_presentacion" name="id_presentacion">
                            <option value="">Seleccionar presentación</option>
                            <?php foreach($presentations as $presentation): ?>
                                <option value="<?= (int)$presentation['id_presentacion'] ?>"><?= escape((string)$presentation['nombre'].' · '.formatearCantidadStock((int)$presentation['cantidad_gramos'],true)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="unidades_presentacion">Unidades a descontar</label>
                        <input id="unidades_presentacion" name="unidades_presentacion" type="number" min="1" step="1">
                    </div>
                    <div class="admin-field admin-field--full admin-lots-panel admin-lots-panel--stock" id="entrada-lotes-fields" hidden>
                        <div class="admin-lots-panel__header"><span class="admin-lots-panel__icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span><div><span class="admin-lots-panel__badge">Solo alimento seco</span><h4>Lotes y presentaciones</h4><p>Registra el origen, vencimiento y distribución física de esta entrada.</p></div></div>
                        <div class="admin-field admin-lots-provider"><label for="id_proveedor"><i class="bi bi-truck" aria-hidden="true"></i> Proveedor</label><select id="id_proveedor" name="id_proveedor"><option value="">Asignar después</option><?php foreach($suppliers as $supplier): ?><option value="<?= (int)$supplier['id_proveedor'] ?>" <?= $selectedSupplierId===(string)$supplier['id_proveedor']?'selected':'' ?>><?= escape((string)$supplier['nombre'].($supplier['rut']?' · '.$supplier['rut']:'')) ?></option><?php endforeach; ?></select><?php if($suppliers===[]):?><span class="admin-field__help"><i class="bi bi-info-circle" aria-hidden="true"></i> Aún no hay proveedores registrados.</span><?php else:?><span class="admin-field__help">Opcional. Puedes asignarlo ahora o dejarlo para después.</span><?php endif;?></div>
                        <?php if($presentations===[]): ?><p class="admin-alert admin-alert--warning">Primero debes crear al menos una presentación activa.</p><?php endif; ?>
                        <div id="lotes-container" class="admin-lots-list"></div>
                        <button class="admin-button admin-lots-add" type="button" id="agregar-lote"><i class="bi bi-plus-circle" aria-hidden="true"></i> Agregar lote</button>
                    </div>
                    <template id="lote-template"><fieldset class="admin-lot-card" data-lote><legend><span class="admin-lot-card__number"><i class="bi bi-box" aria-hidden="true"></i></span><span><strong>Nuevo lote</strong><small>Identificación, fechas y unidades</small></span></legend>
                        <div class="admin-lot-fields"><label class="admin-field admin-lot-code">Código de lote<input data-name="codigo_lote" maxlength="80" required placeholder="Ej.: LT-2026-001"></label>
                        <label class="admin-field">Fecha elaboración<input data-name="fecha_elaboracion" type="date"></label>
                        <label class="admin-field">Fecha vencimiento<input data-name="fecha_vencimiento" type="date" required></label>
                        <label class="admin-field admin-lot-quantity">Cantidad total (g)<input data-name="cantidad_total_g" type="number" min="0.001" step="0.001" required placeholder="0"></label></div>
                        <?php if($presentations!==[]):?><div class="admin-lot-presentations"><strong>Distribución por presentación</strong><div><?php foreach($presentations as $presentation): ?><label class="admin-field"><?= escape((string)$presentation['nombre']) ?> <small><?= escape((string)$presentation['cantidad_gramos']) ?> g</small><input data-presentation="<?= (int)$presentation['id_presentacion'] ?>" type="number" min="0" step="1" value="0"></label><?php endforeach; ?></div></div><?php endif;?>
                        <div class="admin-lot-card__footer"><p data-lote-resumen><i class="bi bi-calculator" aria-hidden="true"></i> Asignados: 0 g · saldo no asignado: 0 g</p><button type="button" class="admin-button admin-button--small" data-quitar-lote><i class="bi bi-trash3" aria-hidden="true"></i> Quitar</button></div>
                    </fieldset></template>
                    <?php endif; ?>

                    <div
                        class="admin-field<?= isset(
                            $errors['motivo']
                        )
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="motivo">
                            Motivo
                        </label>

                        <select
                            id="motivo"
                            name="motivo"
                            required
                            <?= $currentReasons === [] ? 'disabled' : '' ?>
                        >
                            <?php if ($currentReasons === []): ?>
                                <option value="">Selecciona primero el tipo de movimiento</option>
                            <?php endif; ?>
                            <?php foreach ($currentReasons as $reasonKey => $reasonLabel): ?>
                                <option
                                    value="<?= escape($reasonKey) ?>"
                                    <?= $values['motivo'] === $reasonKey
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= escape($reasonLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if (isset($errors['motivo'])): ?>
                            <span class="admin-field__error">
                                <?= escape(
                                    (string) $errors['motivo']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div
                        class="admin-field admin-stock-form-grid__full<?= isset(
                            $errors['observacion']
                        )
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="observacion">
                            Observación
                            <span id="observacion-requirement">(opcional)</span>
                        </label>

                        <textarea
                            id="observacion"
                            name="observacion"
                            maxlength="150"
                            rows="3"
                            <?= $values['motivo'] === 'otro' ? 'required' : '' ?>
                        ><?= escape(
                            (string) $values['observacion']
                        ) ?></textarea>

                        <span class="admin-field__help">
                            Si seleccionas “Otro”, explica aquí el movimiento.
                        </span>

                        <?php if (
                            isset($errors['observacion'])
                        ): ?>
                            <span class="admin-field__error">
                                <?= escape(
                                    (string) $errors['observacion']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="admin-stock-actions">
                    <a
                        class="admin-stock-actions__back"
                        href="<?= escape(
                            appUrl('admin/inventario/index.php')
                        ) ?>"
                    >
                        ← Volver al inventario
                    </a>

                    <button
                        class="admin-button admin-button--primary"
                        type="button"
                        data-stock-confirm-form="form-movimiento-stock"
                        data-stock-product-name="<?= escape((string) $product['nombre']) ?>"
                        data-stock-fractionable="<?= $fractionable ? '1' : '0' ?>"
                    >
                        Registrar movimiento
                    </button>
                </div>
            </form>
            <template id="stock-confirm-template">
                <div class="admin-stock-confirm-summary" data-stock-confirm-summary>
                    <div class="admin-stock-confirm-summary__row"><span class="admin-stock-confirm-summary__label">Producto</span><strong class="admin-stock-confirm-summary__value" data-stock-summary-product></strong></div>
                    <div class="admin-stock-confirm-summary__row"><span class="admin-stock-confirm-summary__label">Tipo de movimiento</span><strong class="admin-stock-confirm-summary__value" data-stock-summary-type></strong></div>
                    <div class="admin-stock-confirm-summary__row"><span class="admin-stock-confirm-summary__label">Cantidad</span><strong class="admin-stock-confirm-summary__value" data-stock-summary-quantity></strong></div>
                    <div class="admin-stock-confirm-summary__row"><span class="admin-stock-confirm-summary__label">Motivo</span><strong class="admin-stock-confirm-summary__value" data-stock-summary-reason></strong></div>
                    <div class="admin-stock-confirm-summary__row" data-stock-summary-observation-row><span class="admin-stock-confirm-summary__label">Observación</span><strong class="admin-stock-confirm-summary__value" data-stock-summary-observation></strong></div>
                </div>
            </template>
            <script>
            (() => {
                const reasonsByType = <?= json_encode($reasonsByType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                const typeSelect = document.getElementById('tipo_movimiento');
                const reasonSelect = document.getElementById('motivo');
                const observation = document.getElementById('observacion');
                const requirement = document.getElementById('observacion-requirement');
                if (!typeSelect || !reasonSelect || !observation || !requirement) return;

                const updateObservation = () => {
                    const required = reasonSelect.value === 'otro';
                    observation.required = required;
                    requirement.textContent = required ? '(obligatoria)' : '(opcional)';
                };
                const updateReasons = () => {
                    const previousReason = reasonSelect.value;
                    const reasons = reasonsByType[typeSelect.value] ?? {};
                    reasonSelect.replaceChildren();
                    for (const [value, label] of Object.entries(reasons)) {
                        const option = new Option(label, value);
                        reasonSelect.add(option);
                    }
                    reasonSelect.disabled = Object.keys(reasons).length === 0;
                    if (Object.hasOwn(reasons, previousReason)) reasonSelect.value = previousReason;
                    updateObservation();
                };
                typeSelect.addEventListener('change', updateReasons);
                reasonSelect.addEventListener('change', updateObservation);
                updateObservation();
                <?php if($fractionable): ?>
                const entrada=document.getElementById('entrada-lotes-fields'); const salida=document.getElementById('salida-presentacion-fields');
                const container=document.getElementById('lotes-container'); const template=document.getElementById('lote-template');
                const renumerar=()=>[...container.children].forEach((lote,i)=>{ lote.querySelectorAll('[data-name]').forEach(el=>el.name=`lotes[${i}][${el.dataset.name}]`); lote.querySelectorAll('[data-presentation]').forEach(el=>el.name=`lotes[${i}][presentaciones][${el.dataset.presentation}]`); });
                const resumen=lote=>{const total=Number(lote.querySelector('[data-name="cantidad_total_g"]').value||0);let asignados=0;lote.querySelectorAll('[data-presentation]').forEach(el=>{const label=el.closest('label').textContent;const g=Number((label.match(/\(([\d.]+) g\)/)||[])[1]||0);asignados+=Number(el.value||0)*g;});lote.querySelector('[data-lote-resumen]').textContent=`Asignados: ${asignados} g · saldo no asignado: ${Math.max(0,total-asignados)} g`;};
                const agregar=()=>{const lote=template.content.firstElementChild.cloneNode(true);container.append(lote);lote.addEventListener('input',()=>resumen(lote));lote.querySelector('[data-quitar-lote]').onclick=()=>{lote.remove();renumerar();};renumerar();};
                document.getElementById('agregar-lote').onclick=agregar;
                const updateLotMode=()=>{const esEntrada=typeSelect.value==='entrada',esSalida=typeSelect.value==='salida';entrada.hidden=!esEntrada;salida.hidden=!esSalida;if(esEntrada&&container.children.length===0)agregar();entrada.querySelectorAll('input').forEach(el=>el.disabled=!esEntrada);salida.querySelectorAll('select,input').forEach(el=>el.disabled=!esSalida);document.getElementById('id_presentacion').required=esSalida;document.getElementById('unidades_presentacion').required=esSalida;const confirmacion=document.getElementById('confirmar_regularizacion');if(confirmacion)confirmacion.disabled=!esEntrada;};
                typeSelect.addEventListener('change',updateLotMode);updateLotMode();
                <?php endif; ?>
            })();
            </script>
        </section>

        <section
            class="admin-stock-history"
            aria-labelledby="stock-history-title"
        >
            <header class="admin-stock-section__header">
                <div>
                    <h3 id="stock-history-title">
                        Últimos movimientos
                    </h3>

                    <p>
                        Se muestran los 10 registros más recientes.
                    </p>
                </div>
            </header>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Cantidad</th>
                            <th scope="col">Stock anterior</th>
                            <th scope="col">Stock resultante</th>
                            <th scope="col">Motivo</th>
                            <th scope="col">Usuario</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach (
                            $movements as $movement
                        ): ?>
                            <?php
                            $movementQuantity = (int) $movement['cantidad'];
                            ?>

                            <tr>
                                <td>
                                    <?= escape(
                                        formatearFechaMovimientoStock(
                                            $movement['creado_en']
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        textoTipoMovimientoStock(
                                            (string) $movement[
                                                'tipo_movimiento'
                                            ]
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= escape(
                                            ($movementQuantity > 0 ? '+' : '')
                                            . formatearCantidadStock($movementQuantity, $fractionable)
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= escape(
                                        formatearCantidadStock((int) $movement['stock_anterior'], $fractionable)
                                    ) ?>
                                </td>

                                <td>
                                    <?= escape(
                                        formatearCantidadStock((int) $movement['stock_final'], $fractionable)
                                    ) ?>
                                </td>

                                <td>
                                    <span>
                                        <?= escape(
                                            $movement['motivo'] !== null
                                                ? (string) $movement[
                                                    'motivo'
                                                ]
                                                : 'Sin motivo'
                                        ) ?>
                                    </span>

                                    <?php if (
                                        $movement['referencia'] !== null
                                        && $movement['referencia'] !== ''
                                    ): ?>
                                        <span class="admin-field__help">
                                            <?= escape(
                                                (string) $movement[
                                                    'referencia'
                                                ]
                                            ) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= escape(
                                        $movement['usuario'] !== null
                                            ? (string) $movement['usuario']
                                            : 'Usuario no disponible'
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($movements === []): ?>
                            <tr class="admin-empty-state">
                                <td colspan="7">
                                    <strong>
                                        Aún no hay movimientos registrados
                                    </strong>

                                    <span>
                                        Los movimientos de este producto aparecerán aquí.
                                    </span>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </section>

<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
