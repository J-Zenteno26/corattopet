<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/shared/seguridad.php';
require_once __DIR__ . '/includes/consultas-publicas.php';

$sku = is_scalar($_GET['sku'] ?? null) ? trim((string) $_GET['sku']) : '';
$modoFicha = $sku !== '';
$config = [];
$products = [];
$product = null;
$presentations = [];
$filterOptions = ['categorias' => [], 'marcas' => []];
$databaseError = false;
$searchInput = is_scalar($_GET['buscar'] ?? null) ? trim((string) $_GET['buscar']) : '';
$filters = [
    'buscar' => mb_substr($searchInput, 0, 100),
    'tipo_mascota' => is_scalar($_GET['tipo_mascota'] ?? null) ? trim((string) $_GET['tipo_mascota']) : '',
    'categoria' => is_scalar($_GET['categoria'] ?? null) && ctype_digit((string) $_GET['categoria']) ? (string) $_GET['categoria'] : '',
    'marca' => is_scalar($_GET['marca'] ?? null) ? trim((string) $_GET['marca']) : '',
    'fraccionable' => is_scalar($_GET['fraccionable'] ?? null) ? trim((string) $_GET['fraccionable']) : '',
    'disponibilidad' => is_scalar($_GET['disponibilidad'] ?? null) ? trim((string) $_GET['disponibilidad']) : '',
];

try {
    $pdo = database();
    $config = obtenerConfiguracionPublica($pdo);
    if ($modoFicha) {
        $product = obtenerProductoPublicoPorSku($pdo, $sku);
        if ($product !== null) {
            $presentations = obtenerPresentacionesPublicasProducto($pdo, (int) $product['id_producto']);
        } else {
            http_response_code(404);
        }
    } else {
        $products = obtenerProductosCatalogoPublico($pdo, $filters);
        $filterOptions = obtenerFiltrosCatalogoPublico($pdo);
    }
} catch (Throwable) {
    $databaseError = true;
    http_response_code(503);
}

$whatsappUrl = obtenerWhatsappPublico($config);
$currentPage = 'catalogo';
$petLabels = ['perro' => 'Perro', 'gato' => 'Gato', 'ambos' => 'Perro y gato', 'otro' => 'Otra mascota'];
$petLabel = static fn(mixed $value): string => $petLabels[(string) $value] ?? ucfirst((string) $value);
$money = static fn(mixed $value): string => '$' . number_format((float) $value, 0, ',', '.');
$summary = static function (array $item): string {
    $details = $item['detalles'] ?? [];
    $text = trim((string) ($details['descripcion'] ?? $details['analisis_caracteristicas'] ?? 'Información clara para elegir una alternativa adecuada para tu mascota.'));
    return mb_strlen($text) > 165 ? mb_substr($text, 0, 162) . '…' : $text;
};
$resolveProductImageUrl = static function (mixed $value): string {
    $path = ltrim(
        str_replace('\\', '/', trim((string) $value)),
        '/'
    );

    if ($path === '' || str_contains($path, '..')) {
        return '';
    }

    if (str_starts_with($path, 'public/')) {
        $path = substr($path, 7);
    }

    if (!str_starts_with($path, 'uploads/productos/')) {
        $path = 'uploads/productos/' . $path;
    }

    return is_file(__DIR__ . '/' . $path) ? $path : '';
};
$activeFilters = [];
if (!$modoFicha) {
    $optionName = static function (array $options, string $id): string {
        foreach ($options as $option) {
            if ((string) $option['id'] === $id)
                return (string) $option['nombre'];
        }
        return $id;
    };
    if ($filters['buscar'] !== '')
        $activeFilters[] = ['Búsqueda', $filters['buscar']];
    if ($filters['tipo_mascota'] !== '')
        $activeFilters[] = ['Mascota', $petLabel($filters['tipo_mascota'])];
    if ($filters['categoria'] !== '')
        $activeFilters[] = ['Categoría', $optionName($filterOptions['categorias'], $filters['categoria'])];
    if ($filters['marca'] !== '')
        $activeFilters[] = ['Marca', $optionName($filterOptions['marcas'], $filters['marca'])];
    if ($filters['fraccionable'] !== '')
        $activeFilters[] = ['Fraccionable', $filters['fraccionable'] === 'si' ? 'Sí' : 'No'];
    if ($filters['disponibilidad'] !== '')
        $activeFilters[] = ['Estado', 'Disponible'];
}

$quickFilterUrl = static function (string $key, string $value) use ($filters): string {
    $parameters = array_filter(
        $filters,
        static fn(string $filterValue): bool => $filterValue !== ''
    );

    if (($parameters[$key] ?? '') === $value) {
        unset($parameters[$key]);
    } else {
        $parameters[$key] = $value;
    }

    return 'catalogo.php' . ($parameters ? '?' . http_build_query($parameters) : '');
};
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description"
        content="Catálogo público de Coratto Pet: productos, ingredientes y presentaciones disponibles.">
    <title><?= $modoFicha && $product ? e($product['nombre']) . ' | ' : '' ?>Catálogo Coratto Pet</title>
    <link rel="stylesheet" href="assets/css/home.css?v=<?= filemtime(__DIR__ . '/assets/css/home.css') ?>">
    <link rel="stylesheet"
        href="assets/css/public-pages.css?v=<?= filemtime(__DIR__ . '/assets/css/public-pages.css') ?>">
    <link rel="stylesheet" href="assets/css/catalogo.css?v=<?= filemtime(__DIR__ . '/assets/css/catalogo.css') ?>">
</head>

<body class="catalog-page">
    <svg class="icon-sprite" aria-hidden="true">
        <symbol id="i-search" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
        </symbol>
        <symbol id="i-filter" viewBox="0 0 24 24">
            <path d="M4 6h16M7 12h10M10 18h4" />
        </symbol>
        <symbol id="i-qr" viewBox="0 0 24 24">
            <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v6h-6v-2M14 18h2" />
        </symbol>
        <symbol id="i-paw" viewBox="0 0 24 24">
            <path d="M12 12c-4 0-7 3-7 6 0 2 1.7 3 3.6 2.2 2.2-.9 4.6-.9 6.8 0C17.3 21 19 20 19 18c0-3-3-6-7-6Z" />
            <ellipse cx="6" cy="10" rx="2" ry="3" />
            <ellipse cx="18" cy="10" rx="2" ry="3" />
            <ellipse cx="10" cy="6" rx="2" ry="3" />
            <ellipse cx="14" cy="6" rx="2" ry="3" />
        </symbol>
        <symbol id="i-box" viewBox="0 0 24 24">
            <path d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10" />
        </symbol>
        <symbol id="i-check" viewBox="0 0 24 24">
            <path d="m5 12 4 4L19 6" />
        </symbol>
        <symbol id="i-arrow" viewBox="0 0 24 24">
            <path d="M5 12h14m-5-5 5 5-5 5" />
        </symbol>
    </svg>
    <?php require __DIR__ . '/includes/public-header.php'; ?>
    <main id="contenido">
        <?php if ($databaseError): ?>
            <section class="state-panel"><span>Catálogo temporalmente no disponible</span>
                <h1>No pudimos cargar los productos</h1>
                <p>Inténtalo nuevamente en unos minutos o escríbenos para recibir orientación.</p><a class="button"
                    href="<?= e($whatsappUrl) ?>">Hablar con Coratto</a>
            </section>
        <?php elseif ($modoFicha && $product === null): ?>
            <section class="state-panel"><span>Producto no encontrado</span>
                <h1>Este código no corresponde a una ficha disponible</h1>
                <p>Puede que el producto ya no esté activo o que el código esté incompleto.</p>
                <div class="actions"><a class="button" href="catalogo.php">Ver catálogo</a><a class="button button-light"
                        href="<?= e($whatsappUrl) ?>">Pedir ayuda</a></div>
            </section>
        <?php elseif ($modoFicha):
            $details = $product['detalles'];
            $productImageUrl = $resolveProductImageUrl($product['imagen'] ?? '');
            ?>
            <div class="detail-shell">
                <div class="detail-topline"><a class="back-link" href="catalogo.php">← Volver al catálogo</a><span
                        class="qr-badge"><svg>
                            <use href="#i-qr" />
                        </svg>Ficha escaneada por QR</span></div>
                <?php if (!empty($product['presentacion_detectada_nombre'])): ?>
                    <div class="scan-banner"><strong>Formato escaneado:</strong>
                        <?= e($product['presentacion_detectada_nombre']) ?>. Esta ficha corresponde al producto original.</div>
                <?php endif; ?>
                <section class="product-hero">
                    <div class="detail-image">
                        <?php if ($productImageUrl !== ''): ?>
                            <img src="<?= e($productImageUrl) ?>" alt="<?= e($product['nombre']) ?>">
                        <?php else: ?>
                            <div class="image-placeholder">
                                Coratto Pet
                                <small>Imagen no disponible</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="detail-intro"><span class="eyebrow"><?= e($product['marca']) ?> ·
                            <?= e($product['categoria']) ?></span>
                        <h1><?= e($product['nombre']) ?></h1>
                        <div class="chips"><span><svg>
                                    <use href="#i-paw" />
                                </svg><?= e($petLabel($product['tipo_mascota'])) ?></span><?php if ($product['fraccionable']): ?><span><svg>
                                        <use href="#i-box" />
                                    </svg>Disponible fraccionado</span><?php endif; ?><span
                                class="<?= $product['disponible'] ? 'available' : '' ?>"><svg>
                                    <use href="#i-check" />
                                </svg><?= $product['disponible'] ? 'Disponible' : 'Consultar disponibilidad' ?></span></div>
                        <p class="lead"><?= e($summary($product)) ?></p>
                        <dl class="identity">
                            <div>
                                <dt>SKU producto padre</dt>
                                <dd><?= e($product['sku']) ?></dd>
                            </div>
                            <div>
                                <dt>Marca</dt>
                                <dd><?= e($product['marca']) ?></dd>
                            </div>
                            <div>
                                <dt>Categoría</dt>
                                <dd><?= e($product['categoria']) ?></dd>
                            </div>
                        </dl>
                        <div class="detail-actions"><a class="button" href="<?= e($whatsappUrl) ?>">Pedir orientación <svg>
                                    <use href="#i-arrow" />
                                </svg></a><small>Te ayudamos a revisar si esta alternativa puede adaptarse a tu
                                mascota.</small></div>
                    </div>
                </section>
                <section class="why-block">
                    <div class="why-icon"><svg>
                            <use href="#i-paw" />
                        </svg></div>
                    <div><span class="eyebrow">Una mirada rápida</span>
                        <h2>¿Por qué puede ser una buena opción?</h2>
                        <p><?= e((string) ($details['etapa_vida_tamano'] ?? $details['descripcion'] ?? 'Su formulación puede ser una alternativa según la etapa, tamaño y necesidades de tu mascota.')) ?>
                        </p>
                    </div>
                </section>
                <section class="presentations">
                    <div><span class="eyebrow">Formatos Coratto</span>
                        <h2>Presentaciones disponibles</h2>
                        <p>El código QR siempre abre esta ficha del producto original.</p>
                    </div>
                    <?php if ($presentations): ?>
                        <div class="presentation-list">
                            <?php foreach ($presentations as $presentation):
                                $scanned = !empty($product['presentacion_detectada_sku']) && mb_strtolower(trim((string) $product['presentacion_detectada_sku'])) === mb_strtolower(trim((string) $presentation['sku'])); ?>
                                <article class="<?= $scanned ? 'is-scanned' : '' ?>" tabindex="0"><?php if ($scanned): ?><span
                                            class="scanned-label"><svg>
                                                <use href="#i-qr" />
                                            </svg>Formato escaneado</span><?php endif; ?>
                                    <div><strong><?= e($presentation['nombre']) ?></strong><span><?= e(number_format((int) $presentation['cantidad_gramos'], 0, ',', '.')) ?>
                                            g</span></div>
                                    <div>
                                        <?php if ((float) $presentation['precio_venta'] > 0): ?><strong><?= e($money($presentation['precio_venta'])) ?></strong><?php endif; ?><span
                                            class="format-status"><?= valorBooleanoPublico($presentation['disponible']) ? 'Disponible' : 'Consultar' ?></span>
                                    </div><?php if (!empty($presentation['sku'])): ?><small>SKU formato:
                                            <?= e($presentation['sku']) ?></small><?php endif; ?>
                                </article><?php endforeach; ?>
                        </div><?php else: ?>
                        <p class="empty-note">No hay presentaciones adicionales publicadas. Consulta por el formato disponible.
                        </p><?php endif; ?>
                </section>
                <?php if (!empty($details['ingredientes_materiales']) || !empty($details['analisis_caracteristicas'])): ?>
                    <section class="product-details">
                        <div class="section-heading"><span class="eyebrow">Información del producto</span>
                            <h2>Conoce mejor su formulación</h2>
                        </div><?php if (!empty($details['ingredientes_materiales'])): ?>
                            <details open>
                                <summary><span><b>01</b>Ingredientes o composición</span><i>+</i></summary>
                                <div><?= nl2br(e($details['ingredientes_materiales'])) ?></div>
                            </details><?php endif; ?><?php if (!empty($details['analisis_caracteristicas'])): ?>
                            <details>
                                <summary><span><b>02</b>Análisis y características</span><i>+</i></summary>
                                <div><?= nl2br(e($details['analisis_caracteristicas'])) ?></div>
                            </details><?php endif; ?>
                    </section><?php endif; ?>
                <aside class="responsible-note"><strong>Una elección informada</strong>
                    <p>La información de esta ficha es orientativa y no reemplaza la recomendación de un médico veterinario.
                    </p>
                </aside>
            </div>
        <?php else: ?>
            <section class="catalog-title" aria-labelledby="catalog-title">
                <div class="catalog-title__inner">
                    <div class="catalog-title__copy">
                        <span class="eyebrow">Selección Coratto</span>
                        <h1 id="catalog-title">Encuentra una alternativa <span>con sentido</span></h1>
                        <p>Compara productos, formatos e información para elegir con mayor claridad.</p>
                    </div>

                    <div class="catalog-title__aside" aria-label="Resultados del catálogo">
                        <svg aria-hidden="true">
                            <use href="#i-paw" />
                        </svg>
                        <strong><?= count($products) ?></strong>
                        <span>producto<?= count($products) === 1 ? '' : 's' ?>
                            disponible<?= count($products) === 1 ? '' : 's' ?></span>
                    </div>

                    <form class="catalog-search" method="get" action="catalogo.php" role="search">
                        <?php foreach (['tipo_mascota', 'categoria', 'marca', 'fraccionable', 'disponibilidad'] as $filterKey): ?>
                            <?php if ($filters[$filterKey] !== ''): ?>
                                <input type="hidden" name="<?= e($filterKey) ?>" value="<?= e($filters[$filterKey]) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <label for="catalog-search-input">Buscar en el catálogo</label>
                        <div class="catalog-search__control">
                            <svg aria-hidden="true">
                                <use href="#i-search" />
                            </svg>
                            <input id="catalog-search-input" type="search" name="buscar"
                                value="<?= e($filters['buscar']) ?>" placeholder="Buscar producto, marca o SKU">
                            <button type="submit">Buscar</button>
                        </div>
                    </form>

                    <nav class="quick-filters" aria-label="Filtros rápidos">
                        <a href="catalogo.php" <?= !$activeFilters ? 'aria-current="page"' : '' ?>>Todos</a>
                        <a class="quick-filter--dog<?= $filters['tipo_mascota'] === 'perro' ? ' is-active' : '' ?>"
                            href="<?= e($quickFilterUrl('tipo_mascota', 'perro')) ?>" <?= $filters['tipo_mascota'] === 'perro' ? 'aria-current="page"' : '' ?>>Perros</a>
                        <a class="quick-filter--cat<?= $filters['tipo_mascota'] === 'gato' ? ' is-active' : '' ?>"
                            href="<?= e($quickFilterUrl('tipo_mascota', 'gato')) ?>" <?= $filters['tipo_mascota'] === 'gato' ? 'aria-current="page"' : '' ?>>Gatos</a>
                        <a class="<?= $filters['fraccionable'] === 'si' ? 'is-active' : '' ?>"
                            href="<?= e($quickFilterUrl('fraccionable', 'si')) ?>" <?= $filters['fraccionable'] === 'si' ? 'aria-current="page"' : '' ?>>Fraccionables</a>
                        <a class="<?= $filters['disponibilidad'] === 'disponible' ? 'is-active' : '' ?>"
                            href="<?= e($quickFilterUrl('disponibilidad', 'disponible')) ?>"
                            <?= $filters['disponibilidad'] === 'disponible' ? 'aria-current="page"' : '' ?>>Disponibles</a>
                    </nav>
                </div>
            </section>

            <div class="catalog-layout">
                <section class="filters" aria-label="Filtros del catálogo">
                    <details open>
                        <summary><svg aria-hidden="true">
                                <use href="#i-filter" />
                            </svg>Más filtros <span aria-hidden="true">+</span></summary>
                        <form method="get" action="catalogo.php">
                            <?php if ($filters['buscar'] !== ''): ?>
                                <input type="hidden" name="buscar" value="<?= e($filters['buscar']) ?>">
                            <?php endif; ?>
                            <?php if ($filters['tipo_mascota'] !== ''): ?>
                                <input type="hidden" name="tipo_mascota" value="<?= e($filters['tipo_mascota']) ?>">
                            <?php endif; ?>

                            <label>
                                Categoría
                                <select name="categoria">
                                    <option value="">Todas</option>
                                    <?php foreach ($filterOptions['categorias'] as $option): ?>
                                        <option value="<?= e($option['id']) ?>" <?= $filters['categoria'] === (string) $option['id'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Marca
                                <select name="marca">
                                    <option value="">Todas</option>
                                    <?php foreach ($filterOptions['marcas'] as $option): ?>
                                        <option value="<?= e($option['id']) ?>" <?= $filters['marca'] === (string) $option['id'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Fraccionable
                                <select name="fraccionable">
                                    <option value="">Todos</option>
                                    <option value="si" <?= $filters['fraccionable'] === 'si' ? 'selected' : '' ?>>Sí</option>
                                    <option value="no" <?= $filters['fraccionable'] === 'no' ? 'selected' : '' ?>>No</option>
                                </select>
                            </label>

                            <label>
                                Disponibilidad
                                <select name="disponibilidad">
                                    <option value="">Todos</option>
                                    <option value="disponible" <?= $filters['disponibilidad'] === 'disponible' ? 'selected' : '' ?>>Disponibles</option>
                                </select>
                            </label>

                            <button class="button" type="submit">Aplicar filtros</button>
                            <a class="clear-link" href="catalogo.php">Limpiar filtros</a>
                        </form>
                    </details>
                </section>

                <?php if ($activeFilters): ?>
                    <div class="active-filters">
                        <span>Filtros aplicados</span>
                        <?php foreach ($activeFilters as [$label, $value]): ?>
                            <span class="filter-chip"><b><?= e($label) ?>:</b> <?= e($value) ?></span>
                        <?php endforeach; ?>
                        <a href="catalogo.php">Quitar todos</a>
                    </div>
                <?php endif; ?>

                <section class="catalog-content" aria-label="Productos">
                    <?php if ($products): ?>
                        <div class="catalog-grid">
                            <?php foreach ($products as $item): ?>
                                <?php $itemImageUrl = $resolveProductImageUrl($item['imagen'] ?? ''); ?>
                                <?php
                                $petType = (string) ($item['tipo_mascota'] ?? 'otro');
                                $petClass = in_array($petType, ['perro', 'gato', 'ambos'], true) ? $petType : 'otro';
                                $productUrl = 'catalogo.php?sku=' . rawurlencode((string) $item['sku']);
                                ?>
                                <article class="catalog-card catalog-card--<?= e($petClass) ?>">
                                    <a class="card-image" href="<?= e($productUrl) ?>">
                                        <?php if ($itemImageUrl !== ''): ?>
                                            <img
                                                src="<?= e($itemImageUrl) ?>"
                                                alt="<?= e($item['nombre']) ?>"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        <?php else: ?>
                                            <div class="image-placeholder">Coratto Pet<small>Imagen no disponible</small></div>
                                        <?php endif; ?>
                                        <?php if ($item['fraccionable']): ?>
                                            <span class="fraction-badge">Fraccionable</span>
                                        <?php endif; ?>
                                    </a>

                                    <div class="card-body">
                                        <div class="card-meta">
                                            <span><?= e($item['marca']) ?></span>
                                            <span><?= e($petLabel($item['tipo_mascota'])) ?></span>
                                        </div>
                                        <h2><a href="<?= e($productUrl) ?>"><?= e($item['nombre']) ?></a></h2>
                                        <span class="category-label"><?= e($item['categoria']) ?></span>
                                        <p><?= e($summary($item)) ?></p>
                                        <?php if ($item['presentaciones_resumen']): ?>
                                            <small class="formats"><b>Presentaciones
                                                    Coratto</b><?= e($item['presentaciones_resumen']) ?></small>
                                        <?php endif; ?>
                                        <a class="card-link" href="<?= e($productUrl) ?>">Conocer este producto <svg
                                                aria-hidden="true">
                                                <use href="#i-arrow" />
                                            </svg></a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-results">
                            <h2>No encontramos productos</h2>
                            <p>Prueba quitando algunos filtros o pide orientación a nuestro equipo.</p>
                            <a href="catalogo.php">Ver todos los productos</a>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="catalog-guidance">
                    <svg aria-hidden="true">
                        <use href="#i-paw" />
                    </svg>
                    <div class="catalog-guidance__content">
                        <span>Orientación cercana</span>
                        <h2>¿No sabes cuál elegir?</h2>
                        <p>Cuéntanos sobre tu mascota y te ayudamos a revisar las alternativas disponibles.</p>
                    </div>
                    <a class="catalog-guidance__action" href="<?= e($whatsappUrl) ?>">Hablar con Coratto</a>
                </section>
            </div>
        <?php endif; ?>
    </main>
    <?php require __DIR__ . '/includes/public-footer.php'; ?>
    <script src="assets/js/public-navigation.js?v=<?= filemtime(__DIR__ . '/assets/js/public-navigation.js') ?>"
        defer></script>
    <script>if (window.matchMedia('(max-width: 850px)').matches) { document.querySelector('.filters details')?.removeAttribute('open'); }</script>
</body>

</html>