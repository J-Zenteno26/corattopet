<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 3);

require_once $projectRoot . '/config/app.php';
require_once $projectRoot . '/shared/seguridad.php';
require_once $projectRoot . '/config/database.php';
require_once __DIR__ . '/includes/funciones-asignaciones-despacho.php';

requireAuthentication();

$parameters = normalizarParametrosAsignacionesDespacho($_GET);
$options = [
    'categorias' => [],
    'marcas' => [],
    'categorias_despacho' => [],
];
$summary = ['pendientes' => 0, 'asignados' => 0, 'automaticos' => 0];
$listing = [
    'registros' => [],
    'total_registros' => 0,
    'total_paginas' => 1,
    'pagina_actual' => 1,
    'por_pagina' => $parameters['por_pagina'],
];
$databaseError = false;

try {
    $connection = database();
    $options = obtenerOpcionesAsignacionesDespacho($connection);
    $summary = obtenerResumenAsignacionesDespacho($connection);
    $listing = listarProductosAsignacionesDespacho($connection, $parameters);
} catch (Throwable $exception) {
    $databaseError = true;
    error_log('Shipping product assignments query error: ' . $exception->getMessage());
}

$parameters['pagina'] = $listing['pagina_actual'];
$hasActiveFilters = hayFiltrosAsignacionesDespacho($parameters);
$firstRecord = $listing['total_registros'] === 0
    ? 0
    : (($listing['pagina_actual'] - 1) * $listing['por_pagina']) + 1;
$lastRecord = min(
    $listing['pagina_actual'] * $listing['por_pagina'],
    $listing['total_registros']
);

$csrfToken = csrfToken();
$pageTitle = 'Asignaciones de despacho';
$activeSection = 'despachos';

$assignmentCssPath = $projectRoot . '/public/css/admin-despachos-asignaciones.css';
$assignmentCssVersion = is_file($assignmentCssPath)
    ? (string) filemtime($assignmentCssPath)
    : '1';

require $projectRoot . '/shared/admin-header.php';
?>
<link
    rel="stylesheet"
    href="<?= escape(appUrl('public/css/admin-despachos-asignaciones.css') . '?v=' . $assignmentCssVersion) ?>"
>
<?php require $projectRoot . '/shared/admin-sidebar.php'; ?>

<main class="admin-main shipping-assignment-page" id="contenido-principal">
    <header class="admin-page-header shipping-assignment-header">
        <div>
            <span class="shipping-assignment-eyebrow">Configuración logística</span>
            <h1 class="admin-page-title admin-page-title--paw">Asignación de productos</h1>
            <p>Clasifica en lote los productos que no tienen un peso definido por presentación.</p>
        </div>

        <div class="admin-actions" aria-label="Navegación del módulo de despachos">
            <a
                class="admin-button"
                href="<?= escape(appUrl('admin/despachos/categorias/index.php')) ?>"
            >
                Categorías de despacho
            </a>
        </div>
    </header>

    <section class="shipping-assignment-summary" aria-label="Resumen de clasificaciones">
        <article class="admin-summary-card shipping-assignment-summary__card is-pending">
            <span>PENDIENTES</span>
            <strong><?= escape(number_format((int) $summary['pendientes'], 0, ',', '.')) ?></strong>
            <small>Requieren categoría</small>
        </article>

        <article class="admin-summary-card shipping-assignment-summary__card is-assigned">
            <span>ASIGNADOS</span>
            <strong><?= escape(number_format((int) $summary['asignados'], 0, ',', '.')) ?></strong>
            <small>Con categoría logística</small>
        </article>

        <article class="admin-summary-card shipping-assignment-summary__card is-automatic">
            <span>PESO AUTOMÁTICO</span>
            <strong><?= escape(number_format((int) $summary['automaticos'], 0, ',', '.')) ?></strong>
            <small>Usan gramos de presentación</small>
        </article>
    </section>

    <section class="admin-panel shipping-assignment-panel">
        <div class="admin-panel__header shipping-assignment-panel__header">
            <div>
                <h2>Productos para clasificar</h2>
                <p>Selecciona varios productos y aplica una categoría de despacho en una sola acción.</p>
            </div>
        </div>

        <form
            class="admin-toolbar shipping-assignment-filters"
            method="get"
            action="<?= escape(appUrl('admin/despachos/asignaciones/index.php')) ?>"
        >
            <input type="hidden" name="por_pagina" value="<?= escape((string) $parameters['por_pagina']) ?>">

            <div class="admin-field shipping-assignment-search">
                <label for="shipping-product-search">Buscar</label>
                <input
                    id="shipping-product-search"
                    name="buscar"
                    type="search"
                    value="<?= escape($parameters['buscar']) ?>"
                    placeholder="Nombre o SKU"
                >
            </div>

            <div class="admin-field">
                <label for="shipping-commercial-category">Categoría comercial</label>
                <select id="shipping-commercial-category" name="id_categoria">
                    <option value="">Todas</option>
                    <?php foreach ($options['categorias'] as $category): ?>
                        <?php $categoryId = (int) $category['id_categoria']; ?>
                        <option
                            value="<?= escape((string) $categoryId) ?>"
                            <?= $parameters['id_categoria'] === $categoryId ? 'selected' : '' ?>
                        >
                            <?= escape((string) $category['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field">
                <label for="shipping-brand">Marca</label>
                <select id="shipping-brand" name="id_marca">
                    <option value="">Todas</option>
                    <?php foreach ($options['marcas'] as $brand): ?>
                        <?php $brandId = (int) $brand['id_marca']; ?>
                        <option
                            value="<?= escape((string) $brandId) ?>"
                            <?= $parameters['id_marca'] === $brandId ? 'selected' : '' ?>
                        >
                            <?= escape((string) $brand['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field">
                <label for="shipping-assignment-status">Clasificación</label>
                <select id="shipping-assignment-status" name="estado_asignacion">
                    <option value="pendientes" <?= $parameters['estado_asignacion'] === 'pendientes' ? 'selected' : '' ?>>Pendientes</option>
                    <option value="asignados" <?= $parameters['estado_asignacion'] === 'asignados' ? 'selected' : '' ?>>Asignados</option>
                    <option value="automaticos" <?= $parameters['estado_asignacion'] === 'automaticos' ? 'selected' : '' ?>>Peso automático</option>
                    <option value="todos" <?= $parameters['estado_asignacion'] === 'todos' ? 'selected' : '' ?>>Todos</option>
                </select>
            </div>

            <div class="shipping-assignment-filter-actions">
                <button class="admin-button admin-button--primary" type="submit">Filtrar</button>

                <?php if ($hasActiveFilters): ?>
                    <a
                        class="admin-button"
                        href="<?= escape(appUrl('admin/despachos/asignaciones/index.php')) ?>"
                    >
                        Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($databaseError): ?>
            <div class="shipping-assignment-state" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <strong>No pudimos cargar los productos</strong>
                <p>Revisa la conexión con la base de datos e inténtalo nuevamente.</p>
            </div>

        <?php elseif ($listing['registros'] === []): ?>
            <div class="shipping-assignment-state">
                <i class="bi bi-paw" aria-hidden="true"></i>
                <strong>No hay productos para esta búsqueda</strong>
                <p>Ajusta los filtros o revisa otra clasificación.</p>
            </div>

        <?php else: ?>
            <form
                class="shipping-assignment-form"
                method="post"
                action="<?= escape(appUrl('admin/despachos/asignaciones/guardar.php')) ?>"
                data-shipping-assignment-form
            >
                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                <input type="hidden" name="return_query" value="<?= escape(http_build_query($parameters)) ?>">

                <div class="shipping-assignment-bulkbar">
                    <div class="shipping-assignment-selection">
                        <strong data-selected-count>0 seleccionados</strong>
                        <span>Marca los productos que compartirán la misma categoría.</span>
                    </div>

                    <div class="shipping-assignment-bulk-controls">
                        <div class="admin-field">
                            <label for="shipping-category-target">Categoría de despacho</label>
                            <select id="shipping-category-target" name="id_categoria_despacho" required>
                                <option value="">Selecciona una categoría</option>
                                <?php foreach ($options['categorias_despacho'] as $shippingCategory): ?>
                                    <option value="<?= escape((string) $shippingCategory['id_categoria_despacho']) ?>">
                                        <?= escape((string) $shippingCategory['nombre']) ?>
                                        · <?= escape(formatearPesoCategoriaDespacho($shippingCategory['peso_estimado_gramos'])) ?>
                                        · <?= escape(ucfirst((string) $shippingCategory['tamano'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button
                            class="admin-button admin-button--primary"
                            type="submit"
                            data-assign-button
                            disabled
                        >
                            Asignar seleccionados
                        </button>
                    </div>
                </div>

                <div class="admin-table-wrap shipping-assignment-table-wrap">
                    <table class="admin-table shipping-assignment-table">
                        <thead>
                            <tr>
                                <th class="shipping-assignment-check-column">
                                    <input
                                        type="checkbox"
                                        data-select-all
                                        aria-label="Seleccionar todos los productos disponibles en esta página"
                                    >
                                </th>
                                <th>Producto</th>
                                <th>Categoría comercial</th>
                                <th>Marca</th>
                                <th>Clasificación actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listing['registros'] as $product): ?>
                                <?php
                                $automaticWeight = (int) $product['presentaciones_con_peso'] > 0;
                                $imageUrl = urlImagenAsignacionDespacho($product['imagen_principal']);
                                ?>
                                <tr class="<?= $automaticWeight ? 'is-automatic' : '' ?>">
                                    <td data-label="Seleccionar" class="shipping-assignment-check-cell">
                                        <input
                                            type="checkbox"
                                            name="productos[]"
                                            value="<?= escape((string) $product['id_producto']) ?>"
                                            data-product-checkbox
                                            aria-label="Seleccionar <?= escape((string) $product['nombre']) ?>"
                                            <?= $automaticWeight ? 'disabled' : '' ?>
                                        >
                                    </td>

                                    <td data-label="Producto">
                                        <div class="shipping-assignment-product">
                                            <?php if ($imageUrl !== null): ?>
                                                <img
                                                    src="<?= escape($imageUrl) ?>"
                                                    alt=""
                                                    width="58"
                                                    height="58"
                                                    loading="lazy"
                                                >
                                            <?php else: ?>
                                                <span class="shipping-assignment-product__placeholder" aria-hidden="true">
                                                    <i class="bi bi-paw"></i>
                                                </span>
                                            <?php endif; ?>

                                            <div>
                                                <strong><?= escape((string) $product['nombre']) ?></strong>
                                                <small>SKU: <?= escape((string) ($product['sku'] ?: 'Sin SKU')) ?></small>
                                            </div>
                                        </div>
                                    </td>

                                    <td data-label="Categoría comercial">
                                        <?= escape((string) $product['categoria']) ?>
                                    </td>

                                    <td data-label="Marca">
                                        <?= escape((string) $product['marca']) ?>
                                    </td>

                                    <td data-label="Clasificación actual">
                                        <?php if ($automaticWeight): ?>
                                            <span class="shipping-assignment-badge is-automatic">
                                                Peso por presentación
                                            </span>
                                        <?php elseif ($product['id_categoria_despacho'] !== null): ?>
                                            <div class="shipping-assignment-current">
                                                <span class="shipping-assignment-badge is-assigned">
                                                    <?= escape((string) $product['categoria_despacho']) ?>
                                                </span>
                                                <small>
                                                    <?= escape(formatearPesoCategoriaDespacho($product['peso_estimado_gramos'])) ?>
                                                    · <?= escape(ucfirst((string) $product['tamano'])) ?>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <span class="shipping-assignment-badge is-pending">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <nav class="admin-pagination" aria-label="Paginación de productos">
                <p class="admin-pagination__summary">
                    Mostrando <?= escape((string) $firstRecord) ?>–<?= escape((string) $lastRecord) ?>
                    de <?= escape((string) $listing['total_registros']) ?> productos
                </p>

                <div class="admin-pagination__pages">
                    <?php
                    $previousPage = max(1, $listing['pagina_actual'] - 1);
                    $nextPage = min($listing['total_paginas'], $listing['pagina_actual'] + 1);
                    ?>
                    <a
                        class="admin-pagination__button"
                        href="<?= escape(construirUrlAsignacionesDespacho($parameters, ['pagina' => $previousPage])) ?>"
                        <?= $listing['pagina_actual'] <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                    >
                        Anterior
                    </a>

                    <span class="admin-pagination__button is-active">
                        <?= escape((string) $listing['pagina_actual']) ?> / <?= escape((string) $listing['total_paginas']) ?>
                    </span>

                    <a
                        class="admin-pagination__button"
                        href="<?= escape(construirUrlAsignacionesDespacho($parameters, ['pagina' => $nextPage])) ?>"
                        <?= $listing['pagina_actual'] >= $listing['total_paginas'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                    >
                        Siguiente
                    </a>
                </div>

                <form class="admin-pagination__size" method="get">
                    <?php foreach ($parameters as $key => $value): ?>
                        <?php if ($key !== 'por_pagina' && $key !== 'pagina' && $value !== '' && $value !== null): ?>
                            <input type="hidden" name="<?= escape($key) ?>" value="<?= escape((string) $value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <label for="shipping-per-page">Por página</label>
                    <select id="shipping-per-page" name="por_pagina" onchange="this.form.submit()">
                        <?php foreach ([12, 24, 48] as $quantity): ?>
                            <option
                                value="<?= $quantity ?>"
                                <?= $listing['por_pagina'] === $quantity ? 'selected' : '' ?>
                            >
                                <?= $quantity ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php
$assignmentJsPath = $projectRoot . '/public/js/admin-despachos-asignaciones.js';
$assignmentJsVersion = is_file($assignmentJsPath)
    ? (string) filemtime($assignmentJsPath)
    : '1';
?>
<script
    src="<?= escape(appUrl('public/js/admin-despachos-asignaciones.js') . '?v=' . $assignmentJsVersion) ?>"
    defer
></script>
<?php require $projectRoot . '/shared/admin-footer.php'; ?>
