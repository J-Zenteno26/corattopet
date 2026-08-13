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
    guardarModalAdmin(
        'error',
        'No fue posible abrir la gestión de stock',
        'El producto indicado no es válido.'
    );
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 302);
    exit;
}

try {
    $connection = database();
    $product = buscarProductoStock($connection, $productId);

    if ($product === null) {
        guardarModalAdmin(
            'error',
            'No fue posible abrir la gestión de stock',
            'El producto indicado no existe.'
        );
        header('Location: ' . appUrl('admin/inventario/index.php'), true, 302);
        exit;
    }

    $movements = listarMovimientosProducto($connection, $productId);
    $presentations = presentacionesActivasProducto($connection, $productId);
    $activeLots = listarLotesActivosStock($connection, $productId);
    $suppliers = todosProveedoresActivos($connection);
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Stock page query error', $exception);
    guardarModalAdmin(
        'error',
        'No fue posible abrir la gestión de stock',
        'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.',
        ['reference' => $reference]
    );
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 302);
    exit;
}

$state = consumirEstadoMovimientoStock($productId);
$values = array_merge(
    valoresInicialesMovimientoStock(),
    is_array($state['valores'] ?? null) ? $state['valores'] : []
);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';

if ($errors !== [] || $generalError !== null) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible registrar el movimiento',
        'message' => $errors !== []
            ? 'Revisa los campos marcados antes de continuar.'
            : 'No se pudo completar la acción.',
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
}

$fractionable = esProductoFraccionable($product);
$activeLots = $activeLots ?? [];
$activeLotCount = count($activeLots);
$todayStock = new DateTimeImmutable('today');
$regularizableLots = array_values(array_filter(
    $activeLots,
    static function (array $lot) use ($todayStock): bool {
        try {
            return (new DateTimeImmutable((string) $lot['fecha_vencimiento'])) >= $todayStock;
        } catch (Throwable) {
            return false;
        }
    }
));
$currentStock = $fractionable && $activeLotCount > 0
    ? stockVendibleLotes($connection, $productId)
    : max(0, (int) $product['cantidad_actual']);
$reservedStock = max(0, (int) $product['cantidad_reservada']);
$availableStock = max(0, $currentStock - $reservedStock);
$minimumStock = max(0, (int) $product['stock_minimo']);
$presentations = $presentations ?? [];
$suppliers = $suppliers ?? [];
$legacyStockWithoutLots = $fractionable && $currentStock > 0 && $activeLotCount === 0;

$stockStatus = estadoStockProducto($availableStock, $minimumStock, $fractionable);
$statusClass = claseEstadoStockProducto($availableStock, $minimumStock, $fractionable);
$movementTypes = [
    'entrada' => 'Entrada de stock',
    'salida' => 'Salida manual',
    'ajuste' => 'Regularización',
];
$reasonsByType = motivosMovimientoStock();
$currentReasons = $reasonsByType[$values['tipo_movimiento']] ?? [];
$selectedSupplierId = (string) ($values['id_proveedor'] ?? '');
$selectedExistingLots = is_array($values['lotes_existentes'] ?? null) ? $values['lotes_existentes'] : [];
$csrfToken = csrfToken();
$pageTitle = 'Gestionar stock';
$activeSection = 'inventario';

$stockCssPath = dirname(__DIR__, 3) . '/public/css/admin-stock.css';
$stockCssVersion = is_file($stockCssPath) ? (string) filemtime($stockCssPath) : '1';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= escape(appUrl('public/css/admin-stock.css') . '?v=' . $stockCssVersion) ?>">

<main class="admin-main admin-stock-page" id="contenido-principal">
    <header class="admin-page-header admin-stock-page__top">
        <div>
            <a class="admin-back-link" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">
                ← Volver al inventario
            </a>
            <span class="admin-stock-eyebrow">Control de inventario</span>
            <h1 class="admin-page-title admin-page-title--paw">Gestionar stock</h1>
            <p>Registra entradas, salidas físicas y regularizaciones sin alterar las reservas de pedidos.</p>
        </div>
    </header>

    <?php if ($legacyStockWithoutLots): ?>
        <div class="admin-stock-warning" role="alert">
            <strong>Este alimento tiene stock histórico sin lotes</strong>
            <p>
                El sistema registra <?= escape(formatearCantidadStock($currentStock, true)) ?>,
                pero no puede asociarlo a un lote. Antes de registrar una entrada o salida utiliza
                <strong>Regularización</strong> e identifica todo el stock físico mediante lotes.
            </p>
        </div>
    <?php endif; ?>

    <section class="admin-stock-product" aria-labelledby="stock-product-title">
        <div>
            <span>Producto</span>
            <h2 id="stock-product-title"><?= escape((string) $product['nombre']) ?></h2>
            <p>
                <?php if (!empty($product['sku'])): ?>
                    <strong>SKU <?= escape((string) $product['sku']) ?></strong>
                    <i aria-hidden="true">·</i>
                <?php endif; ?>
                <span><?= escape((string) $product['categoria']) ?></span>
                <i aria-hidden="true">·</i>
                <span><?= escape((string) ($product['marca'] ?: 'Sin marca')) ?></span>
            </p>
        </div>
        <span class="admin-stock-kind"><?= $fractionable ? 'Stock por peso y lotes' : 'Stock por unidades' ?></span>
    </section>

    <section class="admin-stock-metrics" aria-label="Estado actual del inventario">
        <article>
            <span>Stock físico vigente</span>
            <strong><?= escape(formatearCantidadStock($currentStock, $fractionable)) ?></strong>
            <small><?= $fractionable ? 'Peso registrado en lotes vigentes' : 'Unidades físicas registradas' ?></small>
        </article>
        <article class="is-reserved">
            <span>Reservado</span>
            <strong><?= escape(formatearCantidadStock($reservedStock, $fractionable)) ?></strong>
            <small>Comprometido por pedidos; no puede retirarse manualmente</small>
        </article>
        <article class="is-available">
            <span>Disponible</span>
            <strong><?= escape(formatearCantidadStock($availableStock, $fractionable)) ?></strong>
            <small>Stock libre para venta o salida administrativa</small>
        </article>
        <?php if ($fractionable): ?>
            <article>
                <span>Lotes activos</span>
                <strong><?= escape((string) $activeLotCount) ?></strong>
                <small>FEFO usa primero el lote que vence antes</small>
            </article>
        <?php else: ?>
            <article>
                <span>Estado</span>
                <strong class="admin-status-badge <?= escape($statusClass) ?>"><?= escape($stockStatus) ?></strong>
                <small>Stock mínimo configurado: <?= escape(formatearCantidadStock($minimumStock, false)) ?></small>
            </article>
        <?php endif; ?>
    </section>

    <?php if ($fractionable): ?>
        <section class="admin-stock-card admin-stock-lots" aria-labelledby="stock-lots-title">
            <header>
                <div>
                    <span>Lotes</span>
                    <h2 id="stock-lots-title">Stock identificado</h2>
                    <p>Estos son los lotes físicos que alimentan las presentaciones de venta.</p>
                </div>
            </header>

            <?php if ($activeLots !== []): ?>
                <div class="admin-stock-lots__grid">
                    <?php foreach ($activeLots as $lot): ?>
                        <?php
                        $expirationState = badgeVencimientoLote((string) $lot['fecha_vencimiento']);
                        $expirationClass = match ($expirationState) {
                            'Vencido' => 'is-expired',
                            'Vence pronto' => 'is-warning',
                            'Próximo a vencer' => 'is-near',
                            default => 'is-ok',
                        };
                        ?>
                        <article class="admin-stock-lot <?= escape($expirationClass) ?>">
                            <div>
                                <span>Lote</span>
                                <strong><?= escape((string) $lot['codigo_lote']) ?></strong>
                            </div>
                            <dl>
                                <div>
                                    <dt>Disponible</dt>
                                    <dd><?= escape(formatearCantidadStock((int) round((float) $lot['cantidad_disponible_g']), true)) ?></dd>
                                </div>
                                <div>
                                    <dt>Vencimiento</dt>
                                    <dd><?= escape((new DateTimeImmutable((string) $lot['fecha_vencimiento']))->format('d-m-Y')) ?></dd>
                                </div>
                                <div>
                                    <dt>Estado</dt>
                                    <dd><?= escape($expirationState) ?></dd>
                                </div>
                            </dl>
                            <?php if (!empty($lot['proveedor'])): ?>
                                <small><?= escape((string) $lot['proveedor']) ?></small>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-stock-empty">No existen lotes activos asociados a este producto.</div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="admin-stock-card admin-stock-operation" aria-labelledby="movement-form-title">
        <header>
            <div>
                <span>Movimiento manual</span>
                <h2 id="movement-form-title">Registrar cambio de inventario</h2>
                <p>
                    Elige qué ocurrió físicamente. Las reservas y las ventas Webpay se administran automáticamente y no se modifican aquí.
                </p>
            </div>
        </header>

        <form id="form-movimiento-stock" method="post" action="<?= escape(appUrl('admin/inventario/stock/guardar-movimiento.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <input type="hidden" name="id_producto" value="<?= (int) $productId ?>">

            <div class="admin-stock-operation__selector" role="group" aria-label="Tipo de operación">
                <?php foreach ($movementTypes as $type => $label): ?>
                    <label>
                        <input
                            type="radio"
                            name="tipo_movimiento"
                            value="<?= escape($type) ?>"
                            <?= $values['tipo_movimiento'] === $type ? 'checked' : '' ?>
                            required
                        >
                        <span>
                            <strong><?= escape($label) ?></strong>
                            <small>
                                <?= match ($type) {
                                    'entrada' => $fractionable ? 'Producto que ingresa mediante lotes.' : 'Unidades que ingresan físicamente.',
                                    'salida' => 'Merma, daño, vencimiento, uso interno u otra salida física.',
                                    default => 'Concilia el sistema con un conteo físico real.',
                                } ?>
                            </small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <?php if (isset($errors['tipo_movimiento'])): ?>
                <span class="admin-field__error"><?= escape((string) $errors['tipo_movimiento']) ?></span>
            <?php endif; ?>

            <div class="admin-stock-operation__body">
                <?php if (!$fractionable): ?>
                    <div class="admin-field<?= isset($errors['cantidad']) ? ' admin-field--invalid' : '' ?>">
                        <label for="cantidad" id="cantidad-label">Cantidad</label>
                        <input
                            id="cantidad"
                            name="cantidad"
                            type="number"
                            inputmode="numeric"
                            min="0"
                            step="1"
                            value="<?= escape((string) $values['cantidad']) ?>"
                            placeholder="Ej.: 10"
                            required
                        >
                        <span class="admin-field__help" id="cantidad-help">
                            Para una regularización, ingresa el stock físico final contado.
                        </span>
                        <?php if (isset($errors['cantidad'])): ?>
                            <span class="admin-field__error"><?= escape((string) $errors['cantidad']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($fractionable): ?>
                    <section class="admin-stock-dynamic" data-stock-section="entrada" hidden>
                        <div class="admin-stock-section-note">
                            <strong>Entrada por lote</strong>
                            <span>Todo alimento que ingresa debe quedar identificado con lote y vencimiento.</span>
                        </div>
                        <div class="admin-field">
                            <label for="id_proveedor_entrada">Proveedor <span>(opcional)</span></label>
                            <select id="id_proveedor_entrada" name="id_proveedor" disabled>
                                <option value="">Sin asignar</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option
                                        value="<?= (int) $supplier['id_proveedor'] ?>"
                                        <?= $selectedSupplierId === (string) $supplier['id_proveedor'] ? 'selected' : '' ?>
                                    >
                                        <?= escape((string) $supplier['nombre'] . ($supplier['rut'] ? ' · ' . $supplier['rut'] : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-stock-lot-form" data-lots-mode="entrada">
                            <div class="admin-stock-lot-form__list" data-lots-container></div>
                            <button class="admin-button admin-stock-add-lot" type="button" data-add-lot>+ Agregar lote</button>
                        </div>
                    </section>

                    <section class="admin-stock-dynamic" data-stock-section="salida" hidden>
                        <div class="admin-stock-section-note">
                            <strong>Salida física</strong>
                            <span>Solo puedes retirar stock libre. El sistema descontará los gramos por FEFO.</span>
                        </div>

                        <div class="admin-field">
                            <label for="salida_modo">Cómo registrar la salida</label>
                            <select id="salida_modo" name="salida_modo" disabled>
                                <option value="presentacion" <?= $values['salida_modo'] === 'presentacion' ? 'selected' : '' ?> <?= $presentations === [] ? 'disabled' : '' ?>>Por presentación</option>
                                <option value="gramos" <?= $values['salida_modo'] === 'gramos' ? 'selected' : '' ?>>Por cantidad exacta en gramos</option>
                            </select>
                        </div>

                        <div class="admin-stock-output-mode" data-output-mode="presentacion">
                            <div class="admin-field<?= isset($errors['id_presentacion']) ? ' admin-field--invalid' : '' ?>">
                                <label for="id_presentacion">Presentación</label>
                                <select id="id_presentacion" name="id_presentacion" disabled>
                                    <option value="">Seleccionar presentación</option>
                                    <?php foreach ($presentations as $presentation): ?>
                                        <option
                                            value="<?= (int) $presentation['id_presentacion'] ?>"
                                            <?= (string) $values['id_presentacion'] === (string) $presentation['id_presentacion'] ? 'selected' : '' ?>
                                        >
                                            <?= escape((string) $presentation['nombre'] . ' · ' . formatearCantidadStock((int) $presentation['cantidad_gramos'], true)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['id_presentacion'])): ?>
                                    <span class="admin-field__error"><?= escape((string) $errors['id_presentacion']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-field<?= isset($errors['unidades_presentacion']) ? ' admin-field--invalid' : '' ?>">
                                <label for="unidades_presentacion">Unidades</label>
                                <input
                                    id="unidades_presentacion"
                                    name="unidades_presentacion"
                                    type="number"
                                    min="1"
                                    step="1"
                                    value="<?= escape((string) $values['unidades_presentacion']) ?>"
                                    disabled
                                >
                                <?php if (isset($errors['unidades_presentacion'])): ?>
                                    <span class="admin-field__error"><?= escape((string) $errors['unidades_presentacion']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="admin-stock-output-mode" data-output-mode="gramos" hidden>
                            <div class="admin-field<?= isset($errors['cantidad_gramos_salida']) ? ' admin-field--invalid' : '' ?>">
                                <label for="cantidad_gramos_salida">Cantidad exacta</label>
                                <div class="admin-stock-unit-input">
                                    <input
                                        id="cantidad_gramos_salida"
                                        name="cantidad_gramos_salida"
                                        type="number"
                                        min="1"
                                        step="1"
                                        value="<?= escape((string) $values['cantidad_gramos_salida']) ?>"
                                        placeholder="Ej.: 350"
                                        disabled
                                    >
                                    <span>g</span>
                                </div>
                                <span class="admin-field__help">Útil para merma, daño o una salida que no corresponde a una presentación completa.</span>
                                <?php if (isset($errors['cantidad_gramos_salida'])): ?>
                                    <span class="admin-field__error"><?= escape((string) $errors['cantidad_gramos_salida']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section class="admin-stock-dynamic" data-stock-section="ajuste" hidden>
                        <div class="admin-stock-section-note">
                            <strong>Regularización por conteo físico</strong>
                            <span>Informa cuánto stock vigente existe realmente. El sistema calculará la diferencia.</span>
                        </div>

                        <div class="admin-field<?= isset($errors['stock_fisico_contado']) ? ' admin-field--invalid' : '' ?>">
                            <label for="stock_fisico_contado">Stock físico contado</label>
                            <div class="admin-stock-unit-input">
                                <input
                                    id="stock_fisico_contado"
                                    name="stock_fisico_contado"
                                    type="number"
                                    min="0"
                                    step="1"
                                    value="<?= escape((string) $values['stock_fisico_contado']) ?>"
                                    placeholder="Ej.: 10500"
                                    disabled
                                >
                                <span>g</span>
                            </div>
                            <div class="admin-stock-difference" id="stock-difference" aria-live="polite">
                                Ingresa el conteo físico para calcular la diferencia.
                            </div>
                            <?php if (isset($errors['stock_fisico_contado'])): ?>
                                <span class="admin-field__error"><?= escape((string) $errors['stock_fisico_contado']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-stock-positive-adjustment" id="regularization-lots" hidden>
                            <div class="admin-stock-section-note is-secondary">
                                <strong>Identifica dónde está la diferencia</strong>
                                <span>Selecciona un lote que ya existe para evitar errores de digitación. Si realmente es un lote nuevo, puedes registrarlo aparte.</span>
                            </div>

                            <?php if (!$legacyStockWithoutLots && $regularizableLots !== []): ?>
                                <div class="admin-stock-existing-lots">
                                    <div class="admin-stock-existing-lots__heading">
                                        <div>
                                            <strong>Lotes existentes</strong>
                                            <span>Escoge el lote al que pertenece el stock encontrado y asigna los gramos.</span>
                                        </div>
                                        <button class="admin-button admin-stock-existing-lots__add" type="button" id="add-existing-lot" disabled>
                                            + Agregar otro lote
                                        </button>
                                    </div>
                                    <div class="admin-stock-existing-lots__list" id="existing-lots-list"></div>
                                </div>
                            <?php endif; ?>

                            <div class="admin-stock-new-lot-option">
                                <button class="admin-button admin-stock-new-lot-option__button" type="button" id="show-new-lot" disabled>
                                    + Registrar un lote nuevo
                                </button>
                                <span>Úsalo solo si el lote físico todavía no existe en el sistema.</span>
                            </div>

                            <div class="admin-stock-new-lot-panel" id="regularization-new-lots" hidden>
                                <div class="admin-field">
                                    <label for="id_proveedor_ajuste">Proveedor <span>(opcional)</span></label>
                                    <select id="id_proveedor_ajuste" name="id_proveedor" disabled>
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?= (int) $supplier['id_proveedor'] ?>">
                                                <?= escape((string) $supplier['nombre'] . ($supplier['rut'] ? ' · ' . $supplier['rut'] : '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-stock-lot-form" data-lots-mode="ajuste">
                                    <div class="admin-stock-lot-form__list" data-lots-container></div>
                                    <button class="admin-button admin-stock-add-lot" type="button" data-add-lot>+ Agregar otro lote nuevo</button>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <div class="admin-field<?= isset($errors['motivo']) ? ' admin-field--invalid' : '' ?>">
                    <label for="motivo">Motivo</label>
                    <select id="motivo" name="motivo" required <?= $currentReasons === [] ? 'disabled' : '' ?>>
                        <?php if ($currentReasons === []): ?>
                            <option value="">Selecciona primero la operación</option>
                        <?php endif; ?>
                        <?php foreach ($currentReasons as $reasonKey => $reasonLabel): ?>
                            <option value="<?= escape($reasonKey) ?>" <?= $values['motivo'] === $reasonKey ? 'selected' : '' ?>>
                                <?= escape($reasonLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['motivo'])): ?>
                        <span class="admin-field__error"><?= escape((string) $errors['motivo']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="admin-field admin-stock-observation<?= isset($errors['observacion']) ? ' admin-field--invalid' : '' ?>">
                    <label for="observacion">
                        Observación <span id="observacion-requirement">(opcional)</span>
                    </label>
                    <textarea
                        id="observacion"
                        name="observacion"
                        maxlength="250"
                        rows="3"
                        placeholder="Agrega contexto útil para la trazabilidad del inventario"
                    ><?= escape((string) $values['observacion']) ?></textarea>
                    <?php if (isset($errors['observacion'])): ?>
                        <span class="admin-field__error"><?= escape((string) $errors['observacion']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-stock-operation__footer">
                <div>
                    <strong>Importante</strong>
                    <span>Esta acción modifica inventario físico. El stock reservado por pedidos permanece protegido.</span>
                </div>
                <button class="admin-button admin-button--primary" type="submit">Registrar movimiento</button>
            </div>
        </form>
    </section>

    <section class="admin-stock-card admin-stock-history" aria-labelledby="stock-history-title">
        <header>
            <div>
                <span>Trazabilidad</span>
                <h2 id="stock-history-title">Últimos movimientos</h2>
                <p>Incluye movimientos manuales y operaciones automáticas del ecommerce.</p>
            </div>
        </header>

        <div class="admin-stock-history__wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Movimiento</th>
                        <th>Cantidad</th>
                        <th>Antes</th>
                        <th>Después</th>
                        <th>Detalle</th>
                        <?php if ($fractionable): ?><th>Lote</th><?php endif; ?>
                        <th>Origen</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $movement): ?>
                        <?php
                        $movementQuantity = (int) $movement['cantidad'];
                        $movementType = (string) $movement['tipo_movimiento'];
                        $movementOrigin = (string) ($movement['origen'] ?? '');
                        ?>
                        <tr>
                            <td><?= escape(formatearFechaMovimientoStock($movement['creado_en'])) ?></td>
                            <td>
                                <span class="admin-stock-movement <?= escape(claseTipoMovimientoStock($movementType, $movementOrigin)) ?>">
                                    <?= escape(textoTipoMovimientoStock($movementType, $movementOrigin, (string) ($movement['motivo'] ?? ''))) ?>
                                </span>
                            </td>
                            <td class="<?= $movementQuantity < 0 ? 'is-negative' : ($movementQuantity > 0 ? 'is-positive' : '') ?>">
                                <strong>
                                    <?= escape(($movementQuantity > 0 ? '+' : '') . formatearCantidadStock($movementQuantity, $fractionable)) ?>
                                </strong>
                            </td>
                            <td><?= escape(formatearCantidadStock((int) $movement['stock_anterior'], $fractionable)) ?></td>
                            <td><?= escape(formatearCantidadStock((int) $movement['stock_final'], $fractionable)) ?></td>
                            <td><?= escape(motivoCompletoMovimientoStock($movement['motivo'], $movement['referencia'])) ?></td>
                            <?php if ($fractionable): ?>
                                <td><?= escape((string) ($movement['codigo_lote'] ?: '—')) ?></td>
                            <?php endif; ?>
                            <td><?= escape(ucfirst((string) ($movement['origen'] ?: 'sistema'))) ?></td>
                            <td><?= escape((string) ($movement['usuario'] ?: 'Sistema')) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($movements === []): ?>
                        <tr>
                            <td colspan="<?= $fractionable ? '9' : '8' ?>" class="admin-stock-empty">
                                Aún no hay movimientos registrados para este producto.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php if ($fractionable): ?>
<template id="stock-existing-lot-template">
    <div class="admin-stock-existing-lot" data-existing-lot-row>
        <label class="admin-field">
            Lote del ajuste
            <select data-existing-lot-select required>
                <option value="">Escoge un lote</option>
            </select>
        </label>
        <label class="admin-field">
            Cantidad encontrada
            <div class="admin-stock-unit-input">
                <input data-existing-lot-quantity type="number" min="1" step="1" placeholder="0" required>
                <span>g</span>
            </div>
        </label>
        <div class="admin-stock-existing-lot__summary" data-existing-lot-summary>
            Selecciona un lote para ver su información.
        </div>
        <button type="button" class="admin-stock-existing-lot__remove" data-remove-existing-lot aria-label="Quitar lote">×</button>
    </div>
</template>

<template id="stock-lot-template">
    <fieldset class="admin-stock-new-lot" data-lot>
        <div class="admin-stock-new-lot__header">
            <strong data-lot-title>Nuevo lote</strong>
            <button type="button" data-remove-lot aria-label="Quitar lote">×</button>
        </div>
        <div class="admin-stock-new-lot__grid">
            <label class="admin-field">
                Código de lote
                <input data-lot-field="codigo_lote" maxlength="80" placeholder="Ej.: LT-2026-001" required>
            </label>
            <label class="admin-field">
                Elaboración <span>(opcional)</span>
                <input data-lot-field="fecha_elaboracion" type="date">
            </label>
            <label class="admin-field">
                Vencimiento
                <input data-lot-field="fecha_vencimiento" type="date" required>
            </label>
            <label class="admin-field">
                Cantidad
                <div class="admin-stock-unit-input">
                    <input data-lot-field="cantidad_total_g" type="number" min="1" step="1" placeholder="0" required>
                    <span>g</span>
                </div>
            </label>
        </div>
    </fieldset>
</template>
<?php endif; ?>

<script>
(() => {
    const reasonsByType = <?= json_encode($reasonsByType, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const currentStock = <?= (int) $currentStock ?>;
    const reservedStock = <?= (int) $reservedStock ?>;
    const legacyWithoutLots = <?= $legacyStockWithoutLots ? 'true' : 'false' ?>;
    const fractionable = <?= $fractionable ? 'true' : 'false' ?>;
    const hasPresentations = <?= $presentations !== [] ? 'true' : 'false' ?>;
    const initialLots = <?= json_encode(is_array($values['lotes'] ?? null) ? $values['lotes'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const regularizableLots = <?= json_encode($regularizableLots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const initialExistingLots = <?= json_encode($selectedExistingLots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const radios = [...document.querySelectorAll('input[name="tipo_movimiento"]')];
    const reasonSelect = document.getElementById('motivo');
    const observation = document.getElementById('observacion');
    const observationRequirement = document.getElementById('observacion-requirement');

    const selectedType = () => radios.find((radio) => radio.checked)?.value || '';

    const updateReasons = () => {
        if (!reasonSelect) return;
        const previous = reasonSelect.value;
        const reasons = reasonsByType[selectedType()] || {};
        reasonSelect.replaceChildren();

        if (Object.keys(reasons).length === 0) {
            reasonSelect.add(new Option('Selecciona primero la operación', ''));
            reasonSelect.disabled = true;
        } else {
            for (const [value, label] of Object.entries(reasons)) {
                reasonSelect.add(new Option(label, value));
            }
            reasonSelect.disabled = false;
            if (Object.hasOwn(reasons, previous)) reasonSelect.value = previous;
        }

        updateObservation();
    };

    const updateObservation = () => {
        if (!reasonSelect || !observation || !observationRequirement) return;
        const required = reasonSelect.value === 'otro';
        observation.required = required;
        observationRequirement.textContent = required ? '(obligatoria)' : '(opcional)';
    };

    const toggleElementInputs = (element, enabled) => {
        if (!element) return;
        element.querySelectorAll('input, select, textarea, button').forEach((control) => {
            if (control.matches('[data-add-lot], [data-remove-lot]')) {
                control.disabled = !enabled;
                return;
            }
            control.disabled = !enabled;
        });
    };

    if (!fractionable) {
        const quantityLabel = document.getElementById('cantidad-label');
        const quantityHelp = document.getElementById('cantidad-help');
        const quantity = document.getElementById('cantidad');

        const updateNormalMode = () => {
            const type = selectedType();
            if (!quantityLabel || !quantityHelp || !quantity) return;
            if (type === 'ajuste') {
                quantityLabel.textContent = 'Stock físico final (unidades)';
                quantityHelp.textContent = `Stock actual: ${currentStock} · Reservado: ${reservedStock}. El valor final nunca puede ser menor que lo reservado.`;
                quantity.placeholder = 'Ej.: 18';
            } else {
                quantityLabel.textContent = type === 'salida' ? 'Unidades que salen' : 'Unidades que ingresan';
                quantityHelp.textContent = type === 'salida'
                    ? `Máximo disponible para salida manual: ${Math.max(0, currentStock - reservedStock)} unidades.`
                    : 'Ingresa la cantidad de unidades que entran físicamente.';
                quantity.placeholder = 'Ej.: 10';
            }
        };

        radios.forEach((radio) => radio.addEventListener('change', () => {
            updateReasons();
            updateNormalMode();
        }));
        updateNormalMode();
    } else {
        const sections = [...document.querySelectorAll('[data-stock-section]')];
        const outputModeSelect = document.getElementById('salida_modo');
        const outputModes = [...document.querySelectorAll('[data-output-mode]')];
        const physicalCount = document.getElementById('stock_fisico_contado');
        const difference = document.getElementById('stock-difference');
        const regularizationLots = document.getElementById('regularization-lots');
        const lotTemplate = document.getElementById('stock-lot-template');
        const existingLotTemplate = document.getElementById('stock-existing-lot-template');
        const existingLotsList = document.getElementById('existing-lots-list');
        const addExistingLotButton = document.getElementById('add-existing-lot');
        const showNewLotButton = document.getElementById('show-new-lot');
        const regularizationNewLots = document.getElementById('regularization-new-lots');

        const lotAreas = [...document.querySelectorAll('.admin-stock-lot-form')];

        const renumberLots = (area) => {
            const mode = area.dataset.lotsMode;
            [...area.querySelectorAll('[data-lot]')].forEach((lot, index) => {
                const title = lot.querySelector('[data-lot-title]');
                if (title) title.textContent = `Lote ${index + 1}`;
                lot.querySelectorAll('[data-lot-field]').forEach((input) => {
                    input.name = `lotes[${index}][${input.dataset.lotField}]`;
                });
            });
        };

        const addLot = (area, data = {}) => {
            if (!(lotTemplate instanceof HTMLTemplateElement)) return;
            const container = area.querySelector('[data-lots-container]');
            const fragment = lotTemplate.content.cloneNode(true);
            const lot = fragment.querySelector('[data-lot]');
            lot.querySelectorAll('[data-lot-field]').forEach((input) => {
                const key = input.dataset.lotField;
                if (Object.hasOwn(data, key)) input.value = data[key] ?? '';
            });
            container.append(fragment);
            lot.querySelector('[data-remove-lot]')?.addEventListener('click', () => {
                lot.remove();
                renumberLots(area);
            });
            renumberLots(area);
        };

        lotAreas.forEach((area) => {
            area.querySelector('[data-add-lot]')?.addEventListener('click', () => addLot(area));
        });

        const formatLotOption = (lot) => {
            const available = Number(lot.cantidad_disponible_g || 0);
            const availableText = available >= 1000
                ? `${(available / 1000).toLocaleString('es-CL', { maximumFractionDigits: 3 })} kg`
                : `${available.toLocaleString('es-CL')} g`;
            const date = String(lot.fecha_vencimiento || '').split('-');
            const expiry = date.length === 3 ? `${date[2]}-${date[1]}-${date[0]}` : lot.fecha_vencimiento;
            return `${lot.codigo_lote} · vence ${expiry} · disponible ${availableText}`;
        };

        const refreshExistingLotOptions = () => {
            if (!existingLotsList) return;
            const rows = [...existingLotsList.querySelectorAll('[data-existing-lot-row]')];
            const selected = rows
                .map((row) => row.querySelector('[data-existing-lot-select]')?.value || '')
                .filter(Boolean);

            rows.forEach((row) => {
                const select = row.querySelector('[data-existing-lot-select]');
                if (!select) return;
                const ownValue = select.value;
                [...select.options].forEach((option) => {
                    if (!option.value) return;
                    option.disabled = option.value !== ownValue && selected.includes(option.value);
                });
            });
        };

        const renumberExistingLots = () => {
            if (!existingLotsList) return;
            [...existingLotsList.querySelectorAll('[data-existing-lot-row]')].forEach((row, index) => {
                const select = row.querySelector('[data-existing-lot-select]');
                const quantity = row.querySelector('[data-existing-lot-quantity]');
                if (select) select.name = `lotes_existentes[${index}][id_lote]`;
                if (quantity) quantity.name = `lotes_existentes[${index}][cantidad_g]`;
            });
            refreshExistingLotOptions();
        };

        const updateExistingLotSummary = (row) => {
            const select = row.querySelector('[data-existing-lot-select]');
            const summary = row.querySelector('[data-existing-lot-summary]');
            if (!select || !summary) return;
            const lot = regularizableLots.find((item) => String(item.id_lote) === select.value);
            summary.textContent = lot ? formatLotOption(lot) : 'Selecciona un lote para ver su información.';
        };

        const addExistingLotRow = (data = {}) => {
            if (!existingLotsList || !(existingLotTemplate instanceof HTMLTemplateElement) || regularizableLots.length === 0) return;
            const fragment = existingLotTemplate.content.cloneNode(true);
            const row = fragment.querySelector('[data-existing-lot-row]');
            const select = row.querySelector('[data-existing-lot-select]');
            const quantity = row.querySelector('[data-existing-lot-quantity]');

            regularizableLots.forEach((lot) => {
                const option = new Option(formatLotOption(lot), String(lot.id_lote));
                select.add(option);
            });

            if (data.id_lote) select.value = String(data.id_lote);
            if (data.cantidad_g) quantity.value = String(data.cantidad_g);

            select.addEventListener('change', () => {
                updateExistingLotSummary(row);
                refreshExistingLotOptions();
            });
            row.querySelector('[data-remove-existing-lot]')?.addEventListener('click', () => {
                row.remove();
                renumberExistingLots();
            });

            existingLotsList.append(fragment);
            updateExistingLotSummary(row);
            renumberExistingLots();
        };

        addExistingLotButton?.addEventListener('click', () => addExistingLotRow());

        showNewLotButton?.addEventListener('click', () => {
            if (!regularizationNewLots) return;
            const willShow = regularizationNewLots.hidden;
            regularizationNewLots.hidden = !willShow;
            toggleElementInputs(regularizationNewLots, willShow && selectedType() === 'ajuste');
            showNewLotButton.textContent = willShow ? '− Ocultar lote nuevo' : '+ Registrar un lote nuevo';
            if (willShow) {
                const area = regularizationNewLots.querySelector('.admin-stock-lot-form');
                if (area && area.querySelectorAll('[data-lot]').length === 0) addLot(area);
            }
        });

        const updateOutputMode = () => {
            const mode = outputModeSelect?.value || 'presentacion';
            outputModes.forEach((section) => {
                const enabled = section.dataset.outputMode === mode && selectedType() === 'salida';
                section.hidden = !enabled;
                toggleElementInputs(section, enabled);
            });
        };

        const updateDifference = () => {
            if (!physicalCount || !difference || !regularizationLots) return;
            const raw = physicalCount.value.trim();
            if (raw === '' || !/^\d+$/.test(raw)) {
                difference.textContent = 'Ingresa el conteo físico para calcular la diferencia.';
                difference.className = 'admin-stock-difference';
                regularizationLots.hidden = true;
                toggleElementInputs(regularizationLots, false);
                return;
            }

            const target = Number(raw);
            const delta = target - currentStock;
            const absolute = Math.abs(delta);
            const formatted = absolute >= 1000
                ? `${(absolute / 1000).toLocaleString('es-CL', { maximumFractionDigits: 3 })} kg`
                : `${absolute.toLocaleString('es-CL')} g`;

            if (target < reservedStock) {
                difference.textContent = `No válido: existen ${reservedStock.toLocaleString('es-CL')} g reservados por pedidos.`;
                difference.className = 'admin-stock-difference is-negative';
            } else if (legacyWithoutLots) {
                difference.textContent = 'Debes identificar en lotes todo el stock físico contado.';
                difference.className = 'admin-stock-difference is-warning';
            } else if (delta > 0) {
                difference.textContent = `Diferencia: +${formatted}. Debes identificar ese aumento mediante lote.`;
                difference.className = 'admin-stock-difference is-positive';
            } else if (delta < 0) {
                difference.textContent = `Diferencia: -${formatted}. Se descontará por FEFO sin tocar reservas.`;
                difference.className = 'admin-stock-difference is-negative';
            } else {
                difference.textContent = 'El conteo coincide con el sistema. No hay diferencia que regularizar.';
                difference.className = 'admin-stock-difference';
            }

            const needsLots = legacyWithoutLots || delta > 0;
            regularizationLots.hidden = !needsLots;

            if (!needsLots) {
                toggleElementInputs(regularizationLots, false);
                return;
            }

            const canEdit = selectedType() === 'ajuste';
            addExistingLotButton && (addExistingLotButton.disabled = !canEdit);
            showNewLotButton && (showNewLotButton.disabled = !canEdit);

            if (legacyWithoutLots) {
                if (regularizationNewLots) {
                    regularizationNewLots.hidden = false;
                    toggleElementInputs(regularizationNewLots, canEdit);
                    const area = regularizationNewLots.querySelector('.admin-stock-lot-form');
                    if (area && area.querySelectorAll('[data-lot]').length === 0) {
                        if (initialLots.length > 0) initialLots.forEach((lot) => addLot(area, lot));
                        else addLot(area);
                    }
                }
                return;
            }

            if (existingLotsList && existingLotsList.querySelectorAll('[data-existing-lot-row]').length === 0 && regularizableLots.length > 0) {
                if (initialExistingLots.length > 0) initialExistingLots.forEach((lot) => addExistingLotRow(lot));
                else addExistingLotRow();
            }

            existingLotsList?.querySelectorAll('input, select, button').forEach((control) => {
                control.disabled = !canEdit;
            });

            if (initialLots.length > 0 && regularizationNewLots && regularizationNewLots.hidden) {
                regularizationNewLots.hidden = false;
                toggleElementInputs(regularizationNewLots, canEdit);
                const area = regularizationNewLots.querySelector('.admin-stock-lot-form');
                if (area && area.querySelectorAll('[data-lot]').length === 0) initialLots.forEach((lot) => addLot(area, lot));
                if (showNewLotButton) showNewLotButton.textContent = '− Ocultar lote nuevo';
            }
        };

        const updateFractionableMode = () => {
            const type = selectedType();
            sections.forEach((section) => {
                const enabled = section.dataset.stockSection === type;
                section.hidden = !enabled;
                toggleElementInputs(section, enabled);
            });

            if (type === 'entrada') {
                const area = document.querySelector('[data-lots-mode="entrada"]');
                if (area && area.querySelectorAll('[data-lot]').length === 0) {
                    if (initialLots.length > 0) initialLots.forEach((lot) => addLot(area, lot));
                    else addLot(area);
                }
            }

            if (type === 'salida' && !hasPresentations && outputModeSelect) {
                outputModeSelect.value = 'gramos';
            }

            updateOutputMode();
            updateDifference();
        };

        outputModeSelect?.addEventListener('change', updateOutputMode);
        physicalCount?.addEventListener('input', updateDifference);
        radios.forEach((radio) => radio.addEventListener('change', () => {
            updateReasons();
            updateFractionableMode();
        }));
        updateFractionableMode();
    }

    reasonSelect?.addEventListener('change', updateObservation);
    updateObservation();
})();
</script>

<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
