<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-stock-fraccionado.php';
require_once __DIR__ . '/includes/funciones-inventario.php';
require_once __DIR__ . '/consultas/obtener-resumen.php';
require_once __DIR__ . '/consultas/obtener-filtros.php';
require_once __DIR__ . '/consultas/listar-productos.php';

requireAuthentication();

$parameters = normalizarParametrosInventario($_GET);
$summary = ['productos_totales' => 0, 'alimentos_fraccionables' => 0, 'sin_presentaciones' => 0, 'sin_stock' => 0];
$filterOptions = ['categorias' => [], 'marcas' => []];
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
    $summary = obtenerResumenInventario($connection);
    $filterOptions = obtenerFiltrosInventario($connection);
    $listing = listarProductosInventario($connection, $parameters);
} catch (Throwable $exception) {
    $databaseError = true;
    error_log('Inventory query error: ' . $exception->getMessage());
}

$parameters['pagina'] = $listing['pagina_actual'];
$hasActiveFilters = hayFiltrosInventarioActivos($parameters);
$firstRecord = $listing['total_registros'] === 0
    ? 0
    : (($listing['pagina_actual'] - 1) * $listing['por_pagina']) + 1;
$lastRecord = min(
    $listing['pagina_actual'] * $listing['por_pagina'],
    $listing['total_registros']
);
$csrfToken = csrfToken();
$pageTitle = 'Inventario';
$activeSection = 'inventario';

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-title admin-page-title--paw">Inventario</h1>
            <p>Gestiona el stock y la información de los productos</p>
        </div>
        <div class="admin-actions" aria-label="Acciones de inventario">
            <a class="admin-button admin-button--primary" href="<?= escape(appUrl('admin/inventario/productos/crear.php')) ?>">Agregar producto</a>
            <button class="admin-button" type="button">Importar Excel</button>
            <button class="admin-button" type="button">Exportar inventario</button>
        </div>
    </header>

    <?php if (($_GET['creado'] ?? null) === '1'): ?>
        <div class="admin-alert admin-alert--success" role="status">
            <strong>El producto fue registrado correctamente.</strong>
        </div>
    <?php endif; ?>

    <section class="admin-summary-grid admin-inventory-summary" aria-label="Resumen del inventario">
        <article class="admin-summary-card admin-inventory-summary__card">
            <span>PRODUCTOS TOTALES</span>
            <strong><?= escape(number_format((int) $summary['productos_totales'], 0, ',', '.')) ?></strong>
        </article>
        <article class="admin-summary-card admin-summary-card--notice admin-inventory-summary__card">
            <span>ALIMENTOS FRACCIONABLES</span>
            <strong><?= escape(number_format((int) $summary['alimentos_fraccionables'], 0, ',', '.')) ?></strong>
        </article>
        <article class="admin-summary-card admin-summary-card--warning admin-inventory-summary__card">
            <span>SIN PRESENTACIONES</span>
            <strong><?= escape(number_format((int) $summary['sin_presentaciones'], 0, ',', '.')) ?></strong>
        </article>
        <article class="admin-summary-card admin-summary-card--warning admin-inventory-summary__card">
            <span>SIN STOCK</span>
            <strong><?= escape(number_format((int) $summary['sin_stock'], 0, ',', '.')) ?></strong>
        </article>
    </section>

    <section class="admin-panel admin-inventory-panel" aria-label="Listado de inventario">
        <div class="admin-panel__header">
            <h2>Lista de productos</h2>
        </div>

        <form class="admin-toolbar admin-inventory-filters" method="get" action="<?= escape(appUrl('admin/inventario/index.php')) ?>">
            <input type="hidden" name="por_pagina" value="<?= escape((string) $parameters['por_pagina']) ?>">
            <div class="admin-inventory-filter-row admin-inventory-filter-row--primary">
            <div class="admin-field admin-field--search admin-inventory-filter-search">
                <label for="inventory-search">Buscar</label>
                <input
                    id="inventory-search"
                    name="buscar"
                    type="search"
                    value="<?= escape($parameters['buscar']) ?>"
                    placeholder="Buscar por producto, SKU o código"
                >
            </div>
            <div class="admin-field">
                <label for="category-filter">Categoría</label>
                <select id="category-filter" name="id_categoria">
                    <option value="">Todas</option>
                    <?php foreach ($filterOptions['categorias'] as $category): ?>
                        <option
                            value="<?= escape((string) $category['id_categoria']) ?>"
                            <?= $parameters['id_categoria'] === (int) $category['id_categoria'] ? 'selected' : '' ?>
                        ><?= escape((string) $category['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field">
                <label for="brand-filter">Marca</label>
                <select id="brand-filter" name="id_marca">
                    <option value="">Todas</option>
                    <?php foreach ($filterOptions['marcas'] as $brand): ?>
                        <option
                            value="<?= escape((string) $brand['id_marca']) ?>"
                            <?= $parameters['id_marca'] === (int) $brand['id_marca'] ? 'selected' : '' ?>
                        ><?= escape((string) $brand['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            </div>
            <div class="admin-inventory-filter-row admin-inventory-filter-row--secondary">
            <div class="admin-field">
                <label for="pet-filter">Tipo de mascota</label>
                <select id="pet-filter" name="tipo_mascota">
                    <option value="">Todos</option>
                    <option value="perro" <?= $parameters['tipo_mascota'] === 'perro' ? 'selected' : '' ?>>Perro</option>
                    <option value="gato" <?= $parameters['tipo_mascota'] === 'gato' ? 'selected' : '' ?>>Gato</option>
                    <option value="ambos" <?= $parameters['tipo_mascota'] === 'ambos' ? 'selected' : '' ?>>Perro y gato</option>
                    <option value="otro" <?= $parameters['tipo_mascota'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                </select>
            </div>
            <div class="admin-field">
                <label for="stock-filter">Estado de stock</label>
                <select id="stock-filter" name="estado_stock">
                    <option value="">Todos</option>
                    <option value="en_stock" <?= $parameters['estado_stock'] === 'en_stock' ? 'selected' : '' ?>>En stock</option>
                    <option value="stock_bajo" <?= $parameters['estado_stock'] === 'stock_bajo' ? 'selected' : '' ?>>Stock bajo</option>
                    <option value="sin_stock" <?= $parameters['estado_stock'] === 'sin_stock' ? 'selected' : '' ?>>Sin stock</option>
                </select>
            </div>
            <div class="admin-field">
                <label for="stock-type-filter">Tipo de stock</label>
                <select id="stock-type-filter" name="tipo_stock">
                    <option value="">Todos</option>
                    <option value="fraccionable" <?= $parameters['tipo_stock'] === 'fraccionable' ? 'selected' : '' ?>>Fraccionables</option>
                    <option value="unidad" <?= $parameters['tipo_stock'] === 'unidad' ? 'selected' : '' ?>>Por unidades</option>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button type="submit" class="admin-filter-button">
                    <span class="admin-filter-button__icon" aria-hidden="true">▽</span>
                     Filtros
                </button>

                <a
                    class="admin-filter-clear"
                    href="<?= escape(appUrl('admin/inventario/index.php')) ?>"
                >
                    Limpiar filtros
                </a>
            </div>
            </div>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">PRODUCTO</th>
                        <th scope="col">IDENTIFICACIÓN</th>
                        <th scope="col">CLASIFICACIÓN</th>
                        <th scope="col">PRECIO</th>
                        <th scope="col">STOCK</th>
                        <th scope="col">ESTADO</th>
                        <th scope="col">ACTUALIZADO</th>
                        <th scope="col">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listing['registros'] as $product): ?>
                        <?php
                        $imageUrl = urlImagenInventario($product['imagen_principal'] ?? null);
                        $fractionable = esProductoFraccionable($product);
                        $activePresentations = (int) ($product['presentaciones_activas'] ?? 0);
                        $stockState = textoEstadoStockInventario($product);
                        $updatedParts = explode(' ', formatearFechaInventario($product['actualizado_en']), 2);
                        ?>
                        <tr>
                            <td>
                                <div class="admin-product-cell">
                                    <?php if ($imageUrl !== null): ?>
                                        <img class="admin-product-thumb" src="<?= escape($imageUrl) ?>" alt="" width="48" height="48" loading="lazy">
                                    <?php else: ?>
                                        <span class="admin-product-thumb admin-product-thumb--placeholder" aria-hidden="true"><?= escape(mb_strtoupper(mb_substr((string) $product['nombre'], 0, 1))) ?></span>
                                    <?php endif; ?>
                                    <span class="admin-product-main">
                                        <strong class="admin-product-name"><?= escape((string) $product['nombre']) ?></strong>
                                        <span class="admin-product-kind"><?= $fractionable ? 'Alimento fraccionable' : 'Producto por unidad' ?></span>
                                    </span>
                                </div>
                            </td>
                            <td><span class="admin-product-identifiers"><span class="admin-product-code-line"><strong>SKU:</strong> <?= escape($product['sku'] !== null ? (string) $product['sku'] : 'Sin SKU') ?></span><span class="admin-product-code-line"><strong>Código:</strong> <?= escape($product['codigo_barras'] !== null ? (string) $product['codigo_barras'] : 'Sin código') ?></span></span></td>
                            <td><span class="admin-product-classification"><strong><?= escape((string) $product['categoria']) ?></strong><span><?= escape($product['marca'] !== null ? (string) $product['marca'] : 'Sin marca') ?> · <?= escape(textoTipoMascota($product['tipo_mascota'])) ?></span></span></td>
                            <td><?= $fractionable ? '<span class="admin-price-badge admin-price-badge--presentation">Por presentación</span>' : '<strong>' . escape(formatearPrecioClp($product['precio_venta'])) . '</strong>' ?></td>
                            <td class="admin-stock-cell">
                                <strong class="admin-stock-cell__value"><?= escape(formatearCantidadStock((int) $product['cantidad_disponible'], $fractionable)) ?></strong>
                                <?php if ($fractionable): ?><span class="admin-stock-cell__meta">Stock base</span><?php endif; ?>
                            </td>
                            <td><span class="admin-status-badge admin-inventory-status<?= $stockState === 'En stock' ? ' is-active' : ($stockState === 'Sin stock' ? ' is-inactive' : ' admin-inventory-status--attention') ?>"><?= escape($stockState) ?></span></td>
                            <td><time class="admin-inventory-date"><span><?= escape($updatedParts[0]) ?></span><?php if (isset($updatedParts[1])): ?><small><?= escape($updatedParts[1]) ?></small><?php endif; ?></time></td>
                            <td>
                                <div class="admin-icon-actions">
                                    <a class="admin-icon-button admin-icon-button--stock" href="<?= escape(appUrl('admin/inventario/stock/index.php?id=' . $product['id_producto'])) ?>" title="Gestionar stock" aria-label="Gestionar stock de <?= escape((string) $product['nombre']) ?>">
                                        <svg class="admin-icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Zm0 0 8 4.5m8-4.5L12 12m0 9v-9M8 5.25l8 4.5"/></svg><span class="admin-sr-only">Gestionar stock</span>
                                    </a>
                                    <a class="admin-icon-button admin-icon-button--edit" href="<?= escape(appUrl('admin/inventario/productos/editar.php?id=' . $product['id_producto'])) ?>" title="Editar" aria-label="Editar <?= escape((string) $product['nombre']) ?>">
                                        <svg class="admin-icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 4.2-1 10.6-10.6a2.1 2.1 0 0 0-3-3L5.2 16 4 20Zm10.3-13.1 2.8 2.8M13 20h7"/></svg><span class="admin-sr-only">Editar producto</span>
                                    </a>
                                    <?php if ($fractionable): ?>
                                        <a class="admin-icon-button admin-icon-button--view<?= $activePresentations === 0 ? ' admin-icon-button--warning' : '' ?>" href="<?= escape(appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $product['id_producto'])) ?>" title="<?= $activePresentations === 0 ? 'Agregar presentaciones' : 'Ver presentaciones' ?>" aria-label="<?= $activePresentations === 0 ? 'Agregar presentaciones a ' : 'Ver presentaciones de ' ?><?= escape((string) $product['nombre']) ?>">
                                            <svg class="admin-icon-button__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/><?php if ($activePresentations === 0): ?><path d="M17 12v6m-3-3h6"/><?php endif; ?></svg><span class="admin-sr-only"><?= $activePresentations === 0 ? 'Agregar presentaciones' : 'Ver presentaciones' ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php if ($fractionable && $activePresentations === 0): ?><span class="admin-inventory-note">Sin presentaciones</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($listing['registros'] === []): ?>
                        <tr class="admin-empty-state">
                            <td colspan="8">
                                <?php if ($databaseError): ?>
                                    <strong role="alert">No fue posible cargar el inventario en este momento</strong>
                                    <span>Intenta nuevamente más tarde.</span>
                                <?php elseif ($hasActiveFilters && (int) $summary['productos_totales'] > 0): ?>
                                    <strong>No encontramos productos con estos filtros</strong>
                                    <span>Prueba con otros criterios o limpia los filtros seleccionados.</span>
                                    <a class="admin-button" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">Limpiar filtros</a>
                                <?php else: ?>
                                    <strong>Aún no hay productos registrados</strong>
                                    <span>Los productos aparecerán aquí cuando registres o importes el catálogo.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            <p class="admin-pagination__summary">
                Mostrando <?= escape((string) $firstRecord) ?> a <?= escape((string) $lastRecord) ?>
                de <?= escape((string) $listing['total_registros']) ?> productos
            </p>

            <nav class="admin-pagination__pages" aria-label="Paginación del inventario">
                <?php if ($listing['pagina_actual'] > 1): ?>
                    <a
                        class="admin-pagination__button"
                        href="<?= escape(construirUrlInventario($parameters, ['pagina' => $listing['pagina_actual'] - 1])) ?>"
                        aria-label="Página anterior"
                    >‹</a>
                <?php else: ?>
                    <span class="admin-pagination__button" aria-disabled="true">‹</span>
                <?php endif; ?>

                <?php
                $firstPage = max(1, $listing['pagina_actual'] - 2);
                $lastPage = min($listing['total_paginas'], $listing['pagina_actual'] + 2);
                for ($page = $firstPage; $page <= $lastPage; $page++):
                ?>
                    <?php if ($page === $listing['pagina_actual']): ?>
                        <span class="admin-pagination__button is-active" aria-current="page"><?= escape((string) $page) ?></span>
                    <?php else: ?>
                        <a
                            class="admin-pagination__button"
                            href="<?= escape(construirUrlInventario($parameters, ['pagina' => $page])) ?>"
                        ><?= escape((string) $page) ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($listing['pagina_actual'] < $listing['total_paginas']): ?>
                    <a
                        class="admin-pagination__button"
                        href="<?= escape(construirUrlInventario($parameters, ['pagina' => $listing['pagina_actual'] + 1])) ?>"
                        aria-label="Página siguiente"
                    >›</a>
                <?php else: ?>
                    <span class="admin-pagination__button" aria-disabled="true">›</span>
                <?php endif; ?>
            </nav>

            <form class="admin-pagination__size" method="get" action="<?= escape(appUrl('admin/inventario/index.php')) ?>">
                <?php foreach (['buscar', 'id_categoria', 'id_marca', 'tipo_mascota', 'estado_stock', 'tipo_stock'] as $field): ?>
                    <?php if ($parameters[$field] !== '' && $parameters[$field] !== null): ?>
                        <input type="hidden" name="<?= escape($field) ?>" value="<?= escape((string) $parameters[$field]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <label for="per-page">Mostrar</label>
                <select id="per-page" name="por_pagina">
                    <?php foreach ([8, 16, 24] as $quantity): ?>
                        <option value="<?= $quantity ?>" <?= $parameters['por_pagina'] === $quantity ? 'selected' : '' ?>>
                            <?= $quantity ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span>por página</span>
                <button class="admin-button" type="submit">Aplicar</button>
            </form>
        </div>
    </section>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
