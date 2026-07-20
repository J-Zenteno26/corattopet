<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once __DIR__ . '/includes/funciones-stock.php';
require_once __DIR__ . '/includes/validaciones-stock.php';
require_once __DIR__ . '/consultas/buscar-producto-stock.php';
require_once __DIR__ . '/consultas/listar-movimientos-producto.php';

requireAuthentication();

$productId = idPositivoStock($_GET['id'] ?? null);

if ($productId === null) {
    header(
        'Location: ' . appUrl(
            'admin/inventario/index.php?mensaje=no_encontrado'
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
        header(
            'Location: ' . appUrl(
                'admin/inventario/index.php?mensaje=no_encontrado'
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
} catch (Throwable $exception) {
    error_log(
        'Stock page query error: '
        . $exception->getMessage()
    );

    header(
        'Location: ' . appUrl(
            'admin/inventario/index.php?mensaje=error'
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

$currentStock = (int) $product['cantidad_actual'];
$minimumStock = (int) $product['stock_minimo'];
$fractionable = esProductoFraccionable($product);

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

    <?php if (($_GET['mensaje'] ?? null) === 'registrado'): ?>
        <div
            class="admin-alert admin-alert--success"
            role="status"
        >
            <strong>
                El movimiento de stock fue registrado correctamente.
            </strong>
        </div>
    <?php endif; ?>

    <?php if ($errors !== [] || $generalError !== null): ?>
        <div
            class="admin-alert admin-alert--error"
            role="alert"
        >
            <strong>
                No fue posible registrar el movimiento de stock.
            </strong>

            <?php if ($generalError !== null): ?>
                <p><?= escape($generalError) ?></p>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <?= escape((string) $error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

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

                    <div
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
                        type="submit"
                    >
                        Registrar movimiento
                    </button>
                </div>
            </form>
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
