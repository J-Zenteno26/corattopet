<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/shared/seguridad.php';
require_once dirname(__DIR__) . '/shared/funciones-carrito.php';
require_once __DIR__ . '/includes/consultas-publicas.php';

$sku = is_scalar($_GET['sku'] ?? null) ? trim((string) $_GET['sku']) : '';
$modoFicha = $sku !== '';
$config = [];
$products = [];
$totalProducts = 0;
$productsPerPage = 24;
$requestedPage = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$currentCatalogPage = $requestedPage === false ? 1 : (int) $requestedPage;
$totalCatalogPages = 1;
$product = null;
$presentations = [];
$productImages = [];
$filterOptions = ['categorias' => [], 'marcas' => [], 'subcategorias' => []];
$databaseError = false;
$searchInput = is_scalar($_GET['buscar'] ?? null) ? trim((string) $_GET['buscar']) : '';
$filters = [
    'buscar' => mb_substr($searchInput, 0, 100),
    'tipo_mascota' => is_scalar($_GET['tipo_mascota'] ?? null) ? trim((string) $_GET['tipo_mascota']) : '',
    'categoria' => is_scalar($_GET['categoria'] ?? null) && ctype_digit((string) $_GET['categoria']) ? (string) $_GET['categoria'] : '',
    'subcategoria' => is_scalar($_GET['subcategoria'] ?? null) ? trim((string) $_GET['subcategoria']) : '',
    'marca' => is_scalar($_GET['marca'] ?? null) ? trim((string) $_GET['marca']) : '',
    'fraccionable' => is_scalar($_GET['fraccionable'] ?? null) ? trim((string) $_GET['fraccionable']) : '',
    'disponibilidad' => is_scalar($_GET['disponibilidad'] ?? null) ? trim((string) $_GET['disponibilidad']) : '',
    'pagina' => (string) $currentCatalogPage,
];
if (mb_strlen($filters['subcategoria']) > 120 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $filters['subcategoria']) !== 1) {
    $filters['subcategoria'] = '';
}
$selectedSubcategories = [];

try {
    $pdo = database();

    if ($modoFicha) {
        $config = obtenerConfiguracionPublica($pdo);
        $product = obtenerProductoPublicoPorSku($pdo, $sku);
        if ($product !== null) {
            $presentations = obtenerPresentacionesPublicasProducto($pdo, (int) $product['id_producto']);
            $productImages = obtenerImagenesPublicasProducto($pdo, (int) $product['id_producto']);
        } else {
            http_response_code(404);
        }
    } else {
        $metadata = obtenerMetadataCatalogoPublico($pdo);
        $config = $metadata['config'];
        $filterOptions = [
            'categorias' => $metadata['categorias'],
            'marcas' => $metadata['marcas'],
            'subcategorias' => $metadata['subcategorias'],
        ];

        if ($filters['categoria'] !== '') {
            foreach ($filterOptions['subcategorias'] as $subcategory) {
                if ((string) $subcategory['id_categoria'] === $filters['categoria']) {
                    $selectedSubcategories[] = $subcategory;
                }
            }
        }
        $validSubcategory = false;
        foreach ($selectedSubcategories as $subcategory) {
            if ((string) $subcategory['slug'] === $filters['subcategoria']) {
                $validSubcategory = true;
                break;
            }
        }
        if (!$validSubcategory) {
            $filters['subcategoria'] = '';
        }

        $pageResult = obtenerPaginaProductosCatalogoPublico(
            $pdo,
            $filters,
            $productsPerPage,
            ($currentCatalogPage - 1) * $productsPerPage
        );
        $totalProducts = (int) $pageResult['total'];
        $products = $pageResult['registros'];
        $totalCatalogPages = max(1, (int) ceil($totalProducts / $productsPerPage));
        if ($currentCatalogPage > $totalCatalogPages) {
            $currentCatalogPage = $totalCatalogPages;
            if ($totalProducts > 0) {
                $pageResult = obtenerPaginaProductosCatalogoPublico(
                    $pdo,
                    $filters,
                    $productsPerPage,
                    ($currentCatalogPage - 1) * $productsPerPage
                );
                $products = $pageResult['registros'];
            }
        }
        $filters['pagina'] = (string) $currentCatalogPage;
    }
} catch (Throwable $exception) {
    error_log(sprintf(
        '[catalogo] Error de carga: %s | code=%s | message=%s',
        get_class($exception),
        (string) $exception->getCode(),
        $exception->getMessage()
    ));

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

    return 'https://corattopet.cl/public/' . $path;
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
    if ($filters['subcategoria'] !== '') {
        foreach ($selectedSubcategories as $subcategory) {
            if ((string) $subcategory['slug'] === $filters['subcategoria']) {
                $activeFilters[] = ['Subcategoría', (string) $subcategory['nombre']];
                break;
            }
        }
    }
    if ($filters['marca'] !== '')
        $activeFilters[] = ['Marca', $optionName($filterOptions['marcas'], $filters['marca'])];
    if ($filters['fraccionable'] !== '')
        $activeFilters[] = ['Fraccionable', $filters['fraccionable'] === 'si' ? 'Sí' : 'No'];
    if ($filters['disponibilidad'] !== '')
        $activeFilters[] = ['Estado', 'Disponible'];
}

$cartResult = is_scalar($_GET['carrito'] ?? null)
    ? trim((string) $_GET['carrito'])
    : '';

$cartMessages = [
    'agregado' => [
        'tipo' => 'exito',
        'texto' => 'Producto añadido correctamente al carrito.',
    ],
    'sesion_expirada' => [
        'tipo' => 'error',
        'texto' => 'La sesión del formulario expiró. Inténtalo nuevamente.',
    ],
    'producto_invalido' => [
        'tipo' => 'error',
        'texto' => 'El producto seleccionado no es válido.',
    ],
    'presentacion_invalida' => [
        'tipo' => 'error',
        'texto' => 'La presentación seleccionada no es válida.',
    ],
    'producto_no_disponible' => [
        'tipo' => 'error',
        'texto' => 'El producto ya no se encuentra disponible.',
    ],
    'selecciona_presentacion' => [
        'tipo' => 'error',
        'texto' => 'Debes elegir una presentación antes de añadir el producto.',
    ],
    'cantidad_invalida' => [
        'tipo' => 'error',
        'texto' => 'La cantidad seleccionada no es válida.',
    ],
    'sin_stock' => [
        'tipo' => 'error',
        'texto' => 'Esta alternativa no tiene stock disponible.',
    ],
    'stock_insuficiente' => [
        'tipo' => 'error',
        'texto' => 'La cantidad solicitada supera el stock disponible.',
    ],
    'error_temporal' => [
        'tipo' => 'error',
        'texto' => 'No pudimos añadir el producto en este momento. Inténtalo nuevamente.',
    ],
];

$cartMessage = $cartMessages[$cartResult] ?? null;


/* =========================================================
   SEO · FICHA DE PRODUCTO
   ========================================================= */

$seoTitle = 'Catálogo Coratto Pet';
$seoDescription = 'Catálogo público de Coratto Pet: productos, ingredientes y presentaciones disponibles.';
$seoCanonical = '';
$seoImageUrl = '';
$productStructuredData = null;

if ($modoFicha && $product !== null) {
    $seoProductName = trim((string) $product['nombre']);
    $seoBrandName = trim((string) $product['marca']);

    /*
     * Evita repetir la marca si el nombre del producto ya la contiene.
     * Ejemplo:
     * "ACANA Adult Dog" no termina como "ACANA ACANA Adult Dog".
     */
    $seoProductLabel = $seoProductName;

    if (
        $seoBrandName !== ''
        && mb_strtolower($seoBrandName) !== 'sin marca'
        && !str_contains(
            mb_strtolower($seoProductName),
            mb_strtolower($seoBrandName)
        )
    ) {
        $seoProductLabel = $seoBrandName . ' ' . $seoProductName;
    }

    $seoTitle = $seoProductLabel . ' | Coratto Pet';

    $seoSummary = trim(
        preg_replace(
            '/\s+/u',
            ' ',
            strip_tags($summary($product))
        ) ?? ''
    );

    $seoDescription = $seoProductLabel . ' en Coratto Pet. ' . $seoSummary;

    if (mb_strlen($seoDescription) > 165) {
        $seoDescription = mb_substr($seoDescription, 0, 162) . '…';
    }

    /*
     * Siempre usamos el SKU del producto padre como URL canónica.
     * Si se entra mediante el SKU de una presentación, ambos terminan
     * apuntando a una única URL SEO.
     */
    $seoCanonical = 'https://corattopet.cl/public/catalogo.php?sku='
        . rawurlencode((string) $product['sku']);

    /*
     * Imágenes públicas del producto.
     */
    $seoImageUrls = [];

    foreach ($productImages as $image) {
        $imageUrl = $resolveProductImageUrl($image['archivo'] ?? '');

        if (
            $imageUrl !== ''
            && !in_array($imageUrl, $seoImageUrls, true)
        ) {
            $seoImageUrls[] = $imageUrl;
        }
    }

    if ($seoImageUrls === []) {
        $fallbackImageUrl = $resolveProductImageUrl(
            $product['imagen'] ?? ''
        );

        if ($fallbackImageUrl !== '') {
            $seoImageUrls[] = $fallbackImageUrl;
        }
    }

    $seoImageUrl = $seoImageUrls[0] ?? '';

    /*
     * Oferta utilizada por los datos estructurados.
     *
     * Si existen presentaciones:
     * - prioriza las que están disponibles;
     * - usa el precio más bajo disponible.
     *
     * Si no existen presentaciones:
     * - usa el precio del producto base.
     */
    $availablePresentationOffers = [];
    $allPresentationOffers = [];

    foreach ($presentations as $presentation) {
        $presentationPrice = (float) (
            $presentation['precio_venta'] ?? 0
        );

        if ($presentationPrice <= 0) {
            continue;
        }

        $presentationAvailable = valorBooleanoPublico(
            $presentation['disponible'] ?? false
        );

        $offer = [
            '@type' => 'Offer',
            'url' => $seoCanonical,
            'priceCurrency' => 'CLP',
            'price' => (int) round($presentationPrice),
            'availability' => $presentationAvailable
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        $allPresentationOffers[] = $offer;

        if ($presentationAvailable) {
            $availablePresentationOffers[] = $offer;
        }
    }

    $seoOffer = null;

    $offerPool = $availablePresentationOffers !== []
        ? $availablePresentationOffers
        : $allPresentationOffers;

    if ($offerPool !== []) {
        usort(
            $offerPool,
            static fn(array $left, array $right): int =>
            $left['price'] <=> $right['price']
        );

        $seoOffer = $offerPool[0];
    } elseif ((float) ($product['precio_venta'] ?? 0) > 0) {
        $seoOffer = [
            '@type' => 'Offer',
            'url' => $seoCanonical,
            'priceCurrency' => 'CLP',
            'price' => (int) round(
                (float) $product['precio_venta']
            ),
            'availability' => !empty($product['disponible'])
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ];
    }

    /*
     * Solo publicamos Product si tenemos los datos mínimos reales
     * necesarios: imagen y oferta con precio válido.
     */
    if ($seoImageUrls !== [] && $seoOffer !== null) {
        $productStructuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $seoCanonical . '#product',
            'name' => $seoProductLabel,
            'image' => $seoImageUrls,
            'description' => $seoSummary,
            'sku' => (string) $product['sku'],
            'category' => (string) $product['categoria'],
            'offers' => $seoOffer,
        ];

        if (
            $seoBrandName !== ''
            && mb_strtolower($seoBrandName) !== 'sin marca'
        ) {
            $productStructuredData['brand'] = [
                '@type' => 'Brand',
                'name' => $seoBrandName,
            ];
        }
    }
}


$catalogFilterUrl = static function (array $changes = [], array $remove = []) use ($filters): string {
    $parameters = array_filter(
        $filters,
        static fn(string $value): bool => $value !== ''
    );

    foreach ($remove as $key) {
        unset($parameters[$key]);
    }

    if (!array_key_exists('pagina', $changes)) {
        unset($parameters['pagina']);
    }

    foreach ($changes as $key => $value) {
        if ($value === '') {
            unset($parameters[$key]);
            continue;
        }

        $parameters[$key] = $value;
    }

    return 'catalogo.php' . ($parameters ? '?' . http_build_query($parameters) : '');
};

$categoryUrl = static function (string $categoryId) use ($catalogFilterUrl): string {
    return $catalogFilterUrl(
        $categoryId === '' ? [] : ['categoria' => $categoryId],
        $categoryId === '' ? ['categoria', 'subcategoria'] : ['subcategoria']
    );
};

$petUrl = static function (string $petType) use ($catalogFilterUrl): string {
    return $catalogFilterUrl(
        $petType === '' ? [] : ['tipo_mascota' => $petType],
        $petType === '' ? ['tipo_mascota'] : []
    );
};

$subcategoryUrl = static function (string $slug) use ($catalogFilterUrl): string {
    return $catalogFilterUrl(
        $slug === '' ? [] : ['subcategoria' => $slug],
        $slug === '' ? ['subcategoria'] : []
    );
};
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="<?= e($seoDescription) ?>">

    <title><?= e($seoTitle) ?></title>

    <?php if ($seoCanonical !== ''): ?>
        <link rel="canonical" href="<?= e($seoCanonical) ?>">
    <?php endif; ?>

    <?php if ($modoFicha && $product !== null): ?>
        <meta property="og:title" content="<?= e($seoTitle) ?>">

        <meta property="og:description" content="<?= e($seoDescription) ?>">

        <meta property="og:type" content="website">

        <meta property="og:url" content="<?= e($seoCanonical) ?>">

        <?php if ($seoImageUrl !== ''): ?>
            <meta property="og:image" content="<?= e($seoImageUrl) ?>">
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($productStructuredData !== null): ?>
        <script type="application/ld+json">
            <?= json_encode(
                $productStructuredData,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            ) ?>
        </script>
    <?php endif; ?>
    <meta name="description" content="<?= e($seoDescription) ?>">

    <title><?= e($seoTitle) ?></title>

    <?php if ($seoCanonical !== ''): ?>
        <link rel="canonical" href="<?= e($seoCanonical) ?>">
    <?php endif; ?>

    <?php if ($modoFicha && $product !== null): ?>
        <meta property="og:title" content="<?= e($seoTitle) ?>">

        <meta property="og:description" content="<?= e($seoDescription) ?>">

        <meta property="og:type" content="website">

        <meta property="og:url" content="<?= e($seoCanonical) ?>">

        <?php if ($seoImageUrl !== ''): ?>
            <meta property="og:image" content="<?= e($seoImageUrl) ?>">
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($productStructuredData !== null): ?>
        <script type="application/ld+json">
            <?= json_encode(
                $productStructuredData,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            ) ?>
        </script>
    <?php endif; ?>
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
        <symbol id="i-format-small" viewBox="0 0 24 24">
            <path d="M8 4h8l1 3v11.5A1.5 1.5 0 0 1 15.5 20h-7A1.5 1.5 0 0 1 7 18.5V7l1-3Z" />
            <path d="M8 8h8M9.5 13h5M10.5 16h3" />
        </symbol>
        <symbol id="i-format-medium" viewBox="0 0 24 24">
            <path d="M7 3.5h10l1.2 4.2-.7 11A1.5 1.5 0 0 1 16 20H8a1.5 1.5 0 0 1-1.5-1.3l-.7-11L7 3.5Z" />
            <path d="M7 8h10M9 12h6M8.5 16.5h7" />
        </symbol>
        <symbol id="i-format-sack" viewBox="0 0 24 24">
            <path d="M8 3h8l-1 3c2.5 2.3 4 5.4 4 9 0 3.7-2.2 6-7 6s-7-2.3-7-6c0-3.6 1.5-6.7 4-9L8 3Z" />
            <path d="M8 6h8M8.5 11.5c2.1-1 4.9-1 7 0M9 16h6" />
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
            $showPresentationFlow = !empty($product['fraccionable']) || $presentations !== [];
            $galleryImages = [];
            foreach ($productImages as $image) {
                $imageUrl = $resolveProductImageUrl($image['archivo'] ?? '');
                if ($imageUrl === '') {
                    continue;
                }

                $galleryImages[] = [
                    'url' => $imageUrl,
                    'alt' => trim((string) ($image['texto_alternativo'] ?? '')) ?: (string) $product['nombre'],
                ];
            }

            if ($galleryImages === []) {
                $productImageUrl = $resolveProductImageUrl($product['imagen'] ?? '');
                if ($productImageUrl !== '') {
                    $galleryImages[] = [
                        'url' => $productImageUrl,
                        'alt' => (string) $product['nombre'],
                    ];
                }
            }
            ?>
            <div class="detail-shell">
                <?php if ($cartMessage !== null): ?>
                    <div class="cart-feedback cart-feedback--<?= e($cartMessage['tipo']) ?>"
                        role="<?= $cartMessage['tipo'] === 'error' ? 'alert' : 'status' ?>">
                        <?= e($cartMessage['texto']) ?>
                    </div>
                <?php endif; ?>
                <div class="detail-topline"><a class="back-link" href="catalogo.php">← Volver al catálogo</a><span
                        class="qr-badge"><svg>
                            <use href="#i-qr" />
                        </svg>Ficha escaneada por QR</span></div>
                <?php if (!empty($product['presentacion_detectada_nombre'])): ?>
                    <div class="scan-banner"><strong>Formato escaneado:</strong>
                        <?= e($product['presentacion_detectada_nombre']) ?>. Esta ficha corresponde al producto original.
                    </div>
                <?php endif; ?>
                <section class="product-hero">
                    <div class="detail-image<?= count($galleryImages) > 1 ? ' detail-image--gallery' : '' ?>"
                        data-product-gallery data-gallery-count="<?= count($galleryImages) ?>">
                        <?php if ($galleryImages !== []): ?>
                            <div class="product-gallery__viewport" aria-live="polite">
                                <?php foreach ($galleryImages as $imageIndex => $galleryImage): ?>
                                    <figure class="product-gallery__slide<?= $imageIndex === 0 ? ' is-active' : '' ?>"
                                        data-gallery-slide aria-hidden="<?= $imageIndex === 0 ? 'false' : 'true' ?>">
                                        <img src="<?= e($galleryImage['url']) ?>" alt="<?= e($galleryImage['alt']) ?>"
                                            <?= $imageIndex === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                                    </figure>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($galleryImages) > 1): ?>
                                <button class="product-gallery__arrow product-gallery__arrow--prev" type="button" data-gallery-prev
                                    aria-label="Ver imagen anterior">
                                    <span aria-hidden="true">‹</span>
                                </button>

                                <button class="product-gallery__arrow product-gallery__arrow--next" type="button" data-gallery-next
                                    aria-label="Ver imagen siguiente">
                                    <span aria-hidden="true">›</span>
                                </button>

                                <div class="product-gallery__footer">
                                    <div class="product-gallery__dots" aria-label="Imágenes del producto">
                                        <?php foreach ($galleryImages as $imageIndex => $galleryImage): ?>
                                            <button type="button"
                                                class="product-gallery__dot<?= $imageIndex === 0 ? ' is-active' : '' ?>"
                                                data-gallery-dot="<?= $imageIndex ?>"
                                                aria-label="Ver imagen <?= $imageIndex + 1 ?> de <?= count($galleryImages) ?>"
                                                aria-current="<?= $imageIndex === 0 ? 'true' : 'false' ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="product-gallery__counter" data-gallery-counter>1 /
                                        <?= count($galleryImages) ?></span>
                                </div>
                            <?php endif; ?>
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
                <?php if (!empty($product['fraccionable'])): ?>
                    <section class="nutrition-product-panel" id="nutrition-product-panel"
                        data-product-id="<?= e((string) $product['id_producto']) ?>">
                        <div class="nutrition-product-panel__default" data-nutrition-default>
                            <span class="eyebrow">Guía personalizada</span>

                            <div class="nutrition-product-panel__copy">
                                <h2>¿Cuánto debería comer tu mascota?</h2>
                                <p>
                                    Obtén una pauta estimada según su edad, peso, actividad y condición corporal,
                                    y descubre qué alimentos pueden ajustarse mejor a su perfil.
                                </p>
                            </div>

                            <a class="button" href="<?= e(appUrl('public/calculadora.php')) ?>">
                                Calcular su porción
                            </a>
                        </div>

                        <div class="nutrition-product-panel__result" data-nutrition-result hidden>
                            <div>
                                <span class="eyebrow">Pauta personalizada</span>
                                <h2 data-nutrition-title>Tu pauta estimada</h2>
                                <p data-nutrition-description>
                                    Esta pauta fue calculada con los datos que ingresaste anteriormente.
                                </p>
                            </div>

                            <div class="nutrition-product-panel__numbers">
                                <div>
                                    <strong data-nutrition-grams-day>—</strong>
                                    <span>g al día</span>
                                </div>

                                <div>
                                    <strong data-nutrition-meals>—</strong>
                                    <span>comidas</span>
                                </div>

                                <div>
                                    <strong data-nutrition-grams-meal>—</strong>
                                    <span>g por comida</span>
                                </div>
                            </div>

                            <div class="nutrition-product-panel__actions">
                                <button type="button" class="button" data-feeding-sheet-open>
                                    Ver ficha de alimentación
                                </button>

                                <a class="button button-light" href="<?= e(appUrl('public/calculadora.php')) ?>">
                                    Recalcular
                                </a>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($showPresentationFlow): ?>
                    <section class="why-block" data-presentation-quick-block>
                        <div class="why-icon"><svg>
                                <use href="#i-paw" />
                            </svg></div>
                        <div class="why-content">
                            <span class="eyebrow">Una mirada rápida</span>

                            <h2>
                                <span data-presentation-quick-title>
                                    Elige el formato que mejor se adapte a tu rutina
                                </span>

                                <span class="quick-format-badge" data-presentation-quick-badge hidden></span>
                            </h2>

                            <p data-presentation-quick-message>
                                Selecciona una presentación y te contamos qué ventajas puede ofrecerte según el tamaño que
                                elijas.
                            </p>

                            <div class="quick-format-ideal" data-presentation-quick-ideal hidden>
                                <span>Ideal para</span>
                                <strong data-presentation-quick-ideal-text></strong>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
                <section class="presentations<?= $showPresentationFlow ? '' : ' presentations--standard' ?>">
                    <?php if ($showPresentationFlow): ?>
                        <div>
                            <span class="eyebrow">Formatos Coratto</span>
                            <h2>Presentaciones disponibles</h2>
                            <p>El código QR siempre abre esta ficha del producto original.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($presentations): ?>
                        <form class="product-purchase-form" method="post"
                            action="<?= e(appUrl('public/acciones-carrito/agregar.php')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                            <input type="hidden" name="id_producto" value="<?= e($product['id_producto']) ?>">

                            <input type="hidden" name="sku_retorno" value="<?= e($product['sku']) ?>">

                            <fieldset class="presentation-fieldset">
                                <legend>Selecciona una presentación</legend>

                                <div class="presentation-list">
                                    <?php foreach ($presentations as $presentation): ?>
                                        <?php
                                        $presentationAvailable = valorBooleanoPublico(
                                            $presentation['disponible']
                                        );

                                        $scanned = !empty(
                                            $product['presentacion_detectada_sku']
                                        ) && mb_strtolower(
                                                trim(
                                                    (string) $product['presentacion_detectada_sku']
                                                )
                                            ) === mb_strtolower(
                                                trim((string) $presentation['sku'])
                                            );

                                        $presentationClasses = [];

                                        if ($scanned) {
                                            $presentationClasses[] = 'is-scanned';
                                        }

                                        if (!$presentationAvailable) {
                                            $presentationClasses[] = 'is-unavailable';
                                        }

                                        $presentationClass = implode(
                                            ' ',
                                            $presentationClasses
                                        );

                                        $presentationInputId = 'presentation-'
                                            . (int) $presentation['id_presentacion'];

                                        $presentationGrams = (int) $presentation['cantidad_gramos'];
                                        $presentationIcon = '#i-format-medium';
                                        $presentationKind = 'Formato Coratto';

                                        if ($presentationGrams === 250) {
                                            $presentationIcon = '#i-format-small';
                                            $presentationKind = 'Formato práctico';
                                        } elseif ($presentationGrams === 1000) {
                                            $presentationIcon = '#i-format-medium';
                                            $presentationKind = 'Formato semanal';
                                        } elseif ($presentationGrams > 1000) {
                                            $presentationIcon = '#i-format-sack';
                                            $presentationKind = 'Saco completo';
                                        }
                                        ?>

                                        <div class="<?= e($presentationClass) ?>">
                                            <?php if ($presentationAvailable && $scanned): ?>
                                                <input id="<?= e($presentationInputId) ?>" type="radio" name="id_presentacion"
                                                    value="<?= e($presentation['id_presentacion']) ?>"
                                                    data-presentation-grams="<?= e((int) $presentation['cantidad_gramos']) ?>" checked
                                                    required>
                                            <?php elseif ($presentationAvailable): ?>
                                                <input id="<?= e($presentationInputId) ?>" type="radio" name="id_presentacion"
                                                    value="<?= e($presentation['id_presentacion']) ?>"
                                                    data-presentation-grams="<?= e((int) $presentation['cantidad_gramos']) ?>" required>
                                            <?php else: ?>
                                                <input id="<?= e($presentationInputId) ?>" type="radio" name="id_presentacion"
                                                    value="<?= e($presentation['id_presentacion']) ?>"
                                                    data-presentation-grams="<?= e((int) $presentation['cantidad_gramos']) ?>" disabled>
                                            <?php endif; ?>

                                            <label for="<?= e($presentationInputId) ?>">
                                                <span class="presentation-format-icon" aria-hidden="true">
                                                    <svg>
                                                        <use href="<?= e($presentationIcon) ?>" />
                                                    </svg>
                                                </span>

                                                <span class="presentation-content">
                                                    <?php if ($scanned): ?>
                                                        <span class="scanned-label">
                                                            <svg>
                                                                <use href="#i-qr" />
                                                            </svg>
                                                            Formato escaneado
                                                        </span>
                                                    <?php endif; ?>

                                                    <span class="presentation-content__heading">
                                                        <span class="presentation-content__title">
                                                            <strong><?= e($presentation['nombre']) ?></strong>
                                                            <small><?= e($presentationKind) ?></small>
                                                        </span>

                                                        <span class="presentation-weight">
                                                            <?= e(number_format($presentationGrams, 0, ',', '.')) ?> g
                                                        </span>
                                                    </span>

                                                    <span class="presentation-content__meta">
                                                        <?php if ((float) $presentation['precio_venta'] > 0): ?>
                                                            <strong><?= e($money($presentation['precio_venta'])) ?></strong>
                                                        <?php endif; ?>

                                                        <span class="format-status">
                                                            <?= $presentationAvailable ? 'Disponible' : 'Sin stock' ?>
                                                        </span>
                                                    </span>

                                                    <?php if (!empty($presentation['sku'])): ?>
                                                        <small class="presentation-sku">
                                                            SKU formato: <?= e($presentation['sku']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </span>

                                                <span class="presentation-selected">
                                                    <svg>
                                                        <use href="#i-check" />
                                                    </svg>
                                                    Seleccionado
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                            <div class="purchase-controls">
                                <div class="purchase-quantity">
                                    <label for="purchase-quantity">Cantidad</label>

                                    <div class="purchase-quantity__control">
                                        <button type="button" data-purchase-quantity="decrease"
                                            aria-label="Disminuir cantidad">−</button>

                                        <input id="purchase-quantity" type="number" name="cantidad" value="1" min="1"
                                            max="<?= e(CARRITO_CANTIDAD_MAXIMA) ?>" inputmode="numeric" required>

                                        <button type="button" data-purchase-quantity="increase"
                                            aria-label="Aumentar cantidad">+</button>
                                    </div>
                                </div>

                                <button class="button purchase-submit" type="submit">
                                    <svg aria-hidden="true">
                                        <use href="#i-paw" />
                                    </svg>
                                    Añadir al carrito
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <?php if ($showPresentationFlow): ?>
                            <p class="empty-note">
                                No hay presentaciones adicionales publicadas.
                            </p>
                        <?php endif; ?>
                        <?php if ((float) $product['precio_venta'] > 0): ?>
                            <div class="single-product-purchase__info">
                                <span class="single-product-purchase__label">Precio</span>
                                <strong class="single-product-purchase__price">
                                    <?= e($money($product['precio_venta'])) ?>
                                </strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['disponible']): ?>
                            <form class="product-purchase-form" method="post"
                                action="<?= e(appUrl('public/acciones-carrito/agregar.php')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                                <input type="hidden" name="id_producto" value="<?= e($product['id_producto']) ?>">

                                <input type="hidden" name="sku_retorno" value="<?= e($product['sku']) ?>">

                                <div class="purchase-controls">
                                    <div class="purchase-quantity">
                                        <label for="purchase-quantity">Cantidad</label>

                                        <div class="purchase-quantity__control">
                                            <button type="button" data-purchase-quantity="decrease"
                                                aria-label="Disminuir cantidad">−</button>

                                            <input id="purchase-quantity" type="number" name="cantidad" value="1" min="1"
                                                max="<?= e(CARRITO_CANTIDAD_MAXIMA) ?>" inputmode="numeric" required>

                                            <button type="button" data-purchase-quantity="increase"
                                                aria-label="Aumentar cantidad">+</button>
                                        </div>
                                    </div>

                                    <button class="button purchase-submit" type="submit">
                                        <svg aria-hidden="true">
                                            <use href="#i-paw" />
                                        </svg>
                                        Añadir al carrito
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="empty-note">
                                Este producto no tiene stock disponible actualmente.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
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
            <section class="catalog-hero-compact" aria-labelledby="catalog-title">
                <div class="catalog-hero-compact__inner">
                    <div class="catalog-hero-compact__copy">
                        <span class="eyebrow">Catálogo Coratto</span>

                        <h1 id="catalog-title">
                            Encuentra lo que necesita tu mascota
                        </h1>

                        <p>
                            Explora alimentos, accesorios, juguetes y más.
                            Usa los filtros solo cuando necesites afinar tu búsqueda.
                        </p>
                    </div>

                    <form class="catalog-search" method="get" action="catalogo.php" role="search">
                        <label for="catalog-search-input">Buscar en el catálogo</label>

                        <div class="catalog-search__control">
                            <svg aria-hidden="true">
                                <use href="#i-search" />
                            </svg>

                            <input id="catalog-search-input" type="search" name="buscar"
                                value="<?= e($filters['buscar']) ?>" placeholder="¿Qué estás buscando para tu mascota?">

                            <button type="submit">Buscar</button>
                        </div>
                    </form>
                </div>
            </section>

            <div class="catalog-shop-shell">
                <button class="catalog-mobile-filter-trigger" type="button" data-catalog-filter-open
                    aria-controls="catalog-sidebar" aria-expanded="false">
                    <svg aria-hidden="true">
                        <use href="#i-filter" />
                    </svg>
                    Filtrar catálogo
                </button>

                <button class="catalog-sidebar-backdrop" type="button" data-catalog-filter-close aria-label="Cerrar filtros"
                    hidden></button>

                <div class="catalog-shop-layout">
                    <aside class="catalog-sidebar" id="catalog-sidebar" aria-label="Filtros del catálogo">
                        <div class="catalog-sidebar__top">
                            <div>
                                <span class="catalog-sidebar__eyebrow">Explora</span>
                                <h2>Filtrar catálogo</h2>
                            </div>

                            <button class="catalog-sidebar__close" type="button" data-catalog-filter-close
                                aria-label="Cerrar filtros">
                                ×
                            </button>
                        </div>

                        <section class="catalog-sidebar__section" aria-labelledby="sidebar-category-title">
                            <div class="catalog-sidebar__heading">
                                <span>01</span>
                                <div>
                                    <h3 id="sidebar-category-title">Categorías</h3>
                                    <p>Empieza por el tipo de producto que buscas.</p>
                                </div>
                            </div>

                            <nav class="catalog-sidebar__categories" aria-label="Categorías del catálogo">
                                <a href="<?= e($categoryUrl('')) ?>"
                                    class="catalog-sidebar__category<?= $filters['categoria'] === '' ? ' is-active' : '' ?>"
                                    <?= $filters['categoria'] === '' ? 'aria-current="page"' : '' ?>>
                                    <span>Todo el catálogo</span>
                                </a>

                                <?php foreach ($filterOptions['categorias'] as $option): ?>
                                    <?php
                                    $categoryIsActive =
                                        $filters['categoria'] === (string) $option['id'];
                                    ?>

                                    <a href="<?= e($categoryUrl((string) $option['id'])) ?>"
                                        class="catalog-sidebar__category<?= $categoryIsActive ? ' is-active' : '' ?>"
                                        <?= $categoryIsActive ? 'aria-current="page"' : '' ?>>
                                        <span><?= e((string) $option['nombre']) ?></span>
                                    </a>

                                    <?php if ($categoryIsActive && $selectedSubcategories !== []): ?>
                                        <div class="catalog-sidebar__subcategories"
                                            aria-label="Subcategorías de <?= e((string) $option['nombre']) ?>">
                                            <span class="catalog-sidebar__subcategories-title">
                                                Dentro de <?= e((string) $option['nombre']) ?>
                                            </span>

                                            <a href="<?= e($subcategoryUrl('')) ?>"
                                                class="<?= $filters['subcategoria'] === '' ? 'is-active' : '' ?>"
                                                <?= $filters['subcategoria'] === '' ? 'aria-current="page"' : '' ?>>
                                                Todas
                                            </a>

                                            <?php foreach ($selectedSubcategories as $subcategory): ?>
                                                <a href="<?= e($subcategoryUrl((string) $subcategory['slug'])) ?>"
                                                    class="<?= $filters['subcategoria'] === (string) $subcategory['slug'] ? 'is-active' : '' ?>"
                                                    <?= $filters['subcategoria'] === (string) $subcategory['slug'] ? 'aria-current="page"' : '' ?>>
                                                    <?= e((string) $subcategory['nombre']) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </nav>
                        </section>

                        <section class="catalog-sidebar__section" aria-labelledby="sidebar-pet-title">
                            <div class="catalog-sidebar__heading">
                                <span>02</span>
                                <div>
                                    <h3 id="sidebar-pet-title">Para tu mascota</h3>
                                    <p>Limita la selección según para quién estás buscando.</p>
                                </div>
                            </div>

                            <nav class="catalog-sidebar__pets" aria-label="Filtrar por tipo de mascota">
                                <a href="<?= e($petUrl('')) ?>"
                                    class="<?= $filters['tipo_mascota'] === '' ? 'is-active' : '' ?>"
                                    <?= $filters['tipo_mascota'] === '' ? 'aria-current="page"' : '' ?>>
                                    Todos
                                </a>

                                <a href="<?= e($petUrl('perro')) ?>"
                                    class="<?= $filters['tipo_mascota'] === 'perro' ? 'is-active' : '' ?>"
                                    <?= $filters['tipo_mascota'] === 'perro' ? 'aria-current="page"' : '' ?>>
                                    Perros
                                </a>

                                <a href="<?= e($petUrl('gato')) ?>"
                                    class="<?= $filters['tipo_mascota'] === 'gato' ? 'is-active' : '' ?>"
                                    <?= $filters['tipo_mascota'] === 'gato' ? 'aria-current="page"' : '' ?>>
                                    Gatos
                                </a>
                            </nav>
                        </section>

                        <section class="catalog-sidebar__section" aria-labelledby="sidebar-extra-title">
                            <div class="catalog-sidebar__heading">
                                <span>03</span>
                                <div>
                                    <h3 id="sidebar-extra-title">Afinar resultados</h3>
                                    <p>Usa estas opciones solo si necesitas reducir aún más la selección.</p>
                                </div>
                            </div>

                            <form class="catalog-sidebar__form" method="get" action="catalogo.php">
                                <?php foreach (['buscar', 'tipo_mascota', 'categoria', 'subcategoria'] as $filterKey): ?>
                                    <?php if ($filters[$filterKey] !== ''): ?>
                                        <input type="hidden" name="<?= e($filterKey) ?>" value="<?= e($filters[$filterKey]) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <label class="catalog-sidebar__field">
                                    <span>Marca</span>

                                    <select name="marca">
                                        <option value="">Todas las marcas</option>

                                        <?php foreach ($filterOptions['marcas'] as $option): ?>
                                            <option value="<?= e($option['id']) ?>" <?= $filters['marca'] === (string) $option['id'] ? 'selected' : '' ?>>
                                                <?= e($option['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="catalog-sidebar__check">
                                    <input type="checkbox" name="fraccionable" value="si" <?= $filters['fraccionable'] === 'si' ? 'checked' : '' ?>>

                                    <span>
                                        <strong>Fraccionables</strong>
                                        <small>Alimentos disponibles en formatos Coratto.</small>
                                    </span>
                                </label>

                                <label class="catalog-sidebar__check">
                                    <input type="checkbox" name="disponibilidad" value="disponible"
                                        <?= $filters['disponibilidad'] === 'disponible' ? 'checked' : '' ?>>

                                    <span>
                                        <strong>Solo disponibles</strong>
                                        <small>Oculta productos sin stock actual.</small>
                                    </span>
                                </label>

                                <button class="button catalog-sidebar__submit" type="submit">
                                    Aplicar filtros
                                </button>

                                <?php if (
                                    $filters['marca'] !== ''
                                    || $filters['fraccionable'] !== ''
                                    || $filters['disponibilidad'] !== ''
                                ): ?>
                                    <a class="catalog-sidebar__clear"
                                        href="<?= e($catalogFilterUrl([], ['marca', 'fraccionable', 'disponibilidad'])) ?>">
                                        Limpiar filtros adicionales
                                    </a>
                                <?php endif; ?>
                            </form>
                        </section>

                        <?php if ($activeFilters): ?>
                            <a class="catalog-sidebar__reset" href="catalogo.php">
                                Ver todo el catálogo
                            </a>
                        <?php endif; ?>
                    </aside>

                    <section class="catalog-products" aria-labelledby="catalog-results-title">
                        <header class="catalog-products__header">
                            <div class="catalog-products__heading">
                                <span class="eyebrow">Selección disponible</span>

                                <h2 id="catalog-results-title">
                                    <?= $totalProducts ?>
                                    producto<?= $totalProducts === 1 ? '' : 's' ?>
                                </h2>

                                <p>
                                    Abre una ficha para conocer detalles, presentaciones y disponibilidad.
                                </p>
                            </div>

                            <?php if ($activeFilters): ?>
                                <div class="catalog-applied" aria-label="Filtros aplicados">
                                    <span class="catalog-applied__label">Estás viendo</span>

                                    <?php foreach ($activeFilters as [$label, $value]): ?>
                                        <span class="catalog-applied__chip">
                                            <b><?= e($label) ?>:</b>
                                            <?= e($value) ?>
                                        </span>
                                    <?php endforeach; ?>

                                    <a href="catalogo.php">Quitar filtros</a>
                                </div>
                            <?php endif; ?>
                        </header>

                        <div class="catalog-content">
                            <?php if ($products): ?>
                                <div class="catalog-grid">
                                    <?php foreach ($products as $item): ?>
                                        <?php
                                        $itemImageUrl = $resolveProductImageUrl($item['imagen'] ?? '');
                                        $petType = (string) ($item['tipo_mascota'] ?? 'otro');
                                        $petClass = in_array($petType, ['perro', 'gato', 'ambos'], true)
                                            ? $petType
                                            : 'otro';
                                        $productUrl = 'catalogo.php?sku='
                                            . rawurlencode((string) $item['sku']);
                                        $categoryCardClass = match (mb_strtoupper(trim((string) $item['categoria']))) {
                                            'ALIMENTOS' => 'catalog-card--alimentos',
                                            'ACCESORIOS' => 'catalog-card--accesorios',
                                            'JUGUETES' => 'catalog-card--juguetes',
                                            default => 'catalog-card--general',
                                        };
                                        $itemDetails = is_array($item['detalles'] ?? null)
                                            ? $item['detalles']
                                            : [];
                                        $isHarnessCollarsLeashes = $categoryCardClass === 'catalog-card--accesorios'
                                            && (
                                                trim((string) ($itemDetails['subcategoria_codigo'] ?? '')) === 'arnes-collares-y-correas'
                                                || mb_strtoupper(trim((string) ($itemDetails['subcategoria'] ?? ''))) === 'ARNÉS, COLLARES Y CORREAS'
                                            );
                                        if ($isHarnessCollarsLeashes) {
                                            $categoryCardClass .= ' catalog-card--arnes-collares-correas';
                                        }
                                        ?>

                                        <article
                                            class="catalog-card catalog-card--<?= e($petClass) ?> <?= e($categoryCardClass) ?>">
                                            <a class="card-image" href="<?= e($productUrl) ?>"
                                                aria-label="Ver <?= e($item['nombre']) ?>">
                                                <?php if ($itemImageUrl !== ''): ?>
                                                    <img src="<?= e($itemImageUrl) ?>" alt="<?= e($item['nombre']) ?>" loading="lazy"
                                                        decoding="async">
                                                <?php else: ?>
                                                    <div class="image-placeholder">
                                                        Coratto Pet
                                                        <small>Imagen no disponible</small>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($item['fraccionable']): ?>
                                                    <span class="fraction-badge">
                                                        Fraccionable
                                                    </span>
                                                <?php endif; ?>
                                            </a>

                                            <div class="card-body">
                                                <div class="card-meta">
                                                    <span><?= e($item['marca']) ?></span>
                                                    <span><?= e($petLabel($item['tipo_mascota'])) ?></span>
                                                </div>

                                                <h2>
                                                    <a href="<?= e($productUrl) ?>">
                                                        <?= e($item['nombre']) ?>
                                                    </a>
                                                </h2>

                                                <span class="category-label">
                                                    <?= e($item['categoria']) ?>
                                                </span>

                                                <p><?= e($summary($item)) ?></p>

                                                <?php if ($item['presentaciones_resumen']): ?>
                                                    <small class="formats">
                                                        <b>Presentaciones Coratto</b>
                                                        <?= e($item['presentaciones_resumen']) ?>
                                                    </small>
                                                <?php endif; ?>
                                                <br>
                                                <a class="card-link" href="<?= e($productUrl) ?>">
                                                    Ver detalles y opciones

                                                    <svg aria-hidden="true">
                                                        <use href="#i-arrow" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($totalCatalogPages > 1): ?>
                                    <nav class="quick-filters" aria-label="Paginación del catálogo">
                                        <?php if ($currentCatalogPage > 1): ?>
                                            <a href="<?= e($catalogFilterUrl(['pagina' => (string) ($currentCatalogPage - 1)])) ?>">
                                                Anterior
                                            </a>
                                        <?php endif; ?>

                                        <?php for ($page = max(1, $currentCatalogPage - 2); $page <= min($totalCatalogPages, $currentCatalogPage + 2); $page++): ?>
                                            <a href="<?= e($catalogFilterUrl(['pagina' => (string) $page])) ?>"
                                                class="<?= $page === $currentCatalogPage ? 'is-active' : '' ?>"
                                                <?= $page === $currentCatalogPage ? 'aria-current="page"' : '' ?>>
                                                <?= $page ?>
                                            </a>
                                        <?php endfor; ?>

                                        <?php if ($currentCatalogPage < $totalCatalogPages): ?>
                                            <a href="<?= e($catalogFilterUrl(['pagina' => (string) ($currentCatalogPage + 1)])) ?>">
                                                Siguiente
                                            </a>
                                        <?php endif; ?>
                                    </nav>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="empty-results">
                                    <span class="eyebrow">Sin coincidencias</span>

                                    <h2>No encontramos productos con esos filtros</h2>

                                    <p>
                                        Prueba cambiando la categoría, quitando algún filtro
                                        o buscando con menos palabras.
                                    </p>

                                    <a href="catalogo.php">
                                        Volver a ver todo el catálogo
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="catalog-guidance">
                    <svg aria-hidden="true">
                        <use href="#i-paw" />
                    </svg>

                    <div class="catalog-guidance__content">
                        <span>Orientación cercana</span>
                        <h2>¿No sabes cuál elegir?</h2>
                        <p>
                            Cuéntanos sobre tu mascota y te ayudamos a revisar
                            las alternativas disponibles.
                        </p>
                    </div>

                    <a class="catalog-guidance__action" href="<?= e($whatsappUrl) ?>">
                        Hablar con Coratto
                    </a>
                </section>
            </div>

            <script>
                (() => {
                    const sidebar = document.querySelector('#catalog-sidebar');
                    const openButton = document.querySelector('[data-catalog-filter-open]');
                    const closeButtons = document.querySelectorAll('[data-catalog-filter-close]');
                    const backdrop = document.querySelector('.catalog-sidebar-backdrop');

                    if (!(sidebar instanceof HTMLElement)
                        || !(openButton instanceof HTMLButtonElement)
                        || !(backdrop instanceof HTMLButtonElement)
                    ) {
                        return;
                    }

                    const setOpen = (open) => {
                        sidebar.classList.toggle('is-open', open);
                        document.body.classList.toggle('catalog-filters-open', open);
                        openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                        backdrop.hidden = !open;

                        if (open) {
                            sidebar.querySelector('a, button, select, input')?.focus();
                        } else {
                            openButton.focus();
                        }
                    };

                    openButton.addEventListener('click', () => setOpen(true));

                    closeButtons.forEach((button) => {
                        button.addEventListener('click', () => setOpen(false));
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                            setOpen(false);
                        }
                    });
                })();
            </script>
        <?php endif; ?>
    </main>
    <?php if ($modoFicha && $product !== null): ?>
        <dialog class="feeding-sheet-dialog" id="feeding-sheet-dialog" aria-labelledby="feeding-sheet-title">
            <article class="feeding-sheet" data-feeding-sheet-content></article>

            <div class="feeding-sheet__actions">
                <button type="button" class="button" data-feeding-sheet-download>
                    Guardar como PDF
                </button>

                <button type="button" class="button button-light" data-feeding-sheet-close>
                    Cerrar
                </button>
            </div>
        </dialog>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/public-footer.php'; ?>
    <script src="assets/js/public-navigation.js?v=<?= filemtime(__DIR__ . '/assets/js/public-navigation.js') ?>"
        defer></script>
    <script>if (window.matchMedia('(max-width: 850px)').matches) { document.querySelector('.filters details')?.removeAttribute('open'); }</script>
    <?php if ($modoFicha && $product !== null): ?>
        <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
        <script>
            'use strict';

            const productGallery = document.querySelector('[data-product-gallery]');

            if (productGallery instanceof HTMLElement) {
                const gallerySlides = [...productGallery.querySelectorAll('[data-gallery-slide]')];
                const galleryDots = [...productGallery.querySelectorAll('[data-gallery-dot]')];
                const galleryPrev = productGallery.querySelector('[data-gallery-prev]');
                const galleryNext = productGallery.querySelector('[data-gallery-next]');
                const galleryCounter = productGallery.querySelector('[data-gallery-counter]');
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                let galleryIndex = 0;
                let galleryTimer = null;

                const showGalleryImage = (nextIndex) => {
                    if (gallerySlides.length < 2) {
                        return;
                    }

                    galleryIndex = (nextIndex + gallerySlides.length) % gallerySlides.length;

                    gallerySlides.forEach((slide, index) => {
                        const isActive = index === galleryIndex;
                        slide.classList.toggle('is-active', isActive);
                        slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                    });

                    galleryDots.forEach((dot, index) => {
                        const isActive = index === galleryIndex;
                        dot.classList.toggle('is-active', isActive);
                        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
                    });

                    if (galleryCounter) {
                        galleryCounter.textContent = `${galleryIndex + 1} / ${gallerySlides.length}`;
                    }
                };

                const stopGalleryAutoplay = () => {
                    if (galleryTimer !== null) {
                        window.clearInterval(galleryTimer);
                        galleryTimer = null;
                    }
                };

                const startGalleryAutoplay = () => {
                    stopGalleryAutoplay();
                    if (gallerySlides.length < 2 || reduceMotion || document.hidden) {
                        return;
                    }
                    galleryTimer = window.setInterval(() => showGalleryImage(galleryIndex + 1), 4800);
                };

                galleryPrev?.addEventListener('click', () => {
                    showGalleryImage(galleryIndex - 1);
                    startGalleryAutoplay();
                });

                galleryNext?.addEventListener('click', () => {
                    showGalleryImage(galleryIndex + 1);
                    startGalleryAutoplay();
                });

                galleryDots.forEach((dot) => {
                    dot.addEventListener('click', () => {
                        showGalleryImage(Number.parseInt(dot.dataset.galleryDot || '0', 10));
                        startGalleryAutoplay();
                    });
                });

                productGallery.addEventListener('mouseenter', stopGalleryAutoplay);
                productGallery.addEventListener('mouseleave', startGalleryAutoplay);
                productGallery.addEventListener('focusin', stopGalleryAutoplay);
                productGallery.addEventListener('focusout', (event) => {
                    if (!(event.relatedTarget instanceof Node) || !productGallery.contains(event.relatedTarget)) {
                        startGalleryAutoplay();
                    }
                });

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        stopGalleryAutoplay();
                    } else {
                        startGalleryAutoplay();
                    }
                });

                startGalleryAutoplay();
            }

            const nutritionPanel = document.querySelector('#nutrition-product-panel');
            const nutritionDefault = nutritionPanel?.querySelector('[data-nutrition-default]');
            const nutritionResult = nutritionPanel?.querySelector('[data-nutrition-result]');
            const feedingSheetDialog = document.querySelector('#feeding-sheet-dialog');
            const feedingSheetContent = feedingSheetDialog?.querySelector('[data-feeding-sheet-content]');

            let activeNutritionCalculation = null;
            let activeNutritionRecommendation = null;

            const escapeNutritionHtml = (value) => String(value ?? '').replace(
                /[&<>'"]/g,
                character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#039;',
                    '"': '&quot;'
                })[character]
            );

            const nutritionLabels = {
                species: { perro: 'Perro', gato: 'Gato' },
                sex: { female: 'Hembra', male: 'Macho' },
                size: { small: 'Pequeño', medium: 'Mediano', large: 'Grande', giant: 'Gigante' },
                bodyCondition: { thin: 'Bajo peso', ideal: 'Ideal', overweight: 'Sobrepeso', obese: 'Obesidad marcada' },
                activity: { low: 'Baja', normal: 'Moderada', high: 'Alta' },
                breedType: { mixed: 'Mestiza', defined: 'Raza definida' },
                health: {
                    healthy: 'Sin condición informada',
                    sensitive: 'Sensibilidad digestiva o cutánea',
                    medical: 'Enfermedad o condición médica',
                    pregnancy: 'Gestación o lactancia'
                },
                allergy: {
                    pollo: 'Pollo',
                    pescado: 'Pescado',
                    cordero: 'Cordero',
                    vacuno: 'Vacuno',
                    pavo: 'Pavo',
                    cerdo: 'Cerdo',
                    grain: 'Cereales'
                },
                stage: { puppy: 'Cachorro', kitten: 'Gatito', adult: 'Adulto', senior: 'Senior' }
            };

            const scalarNutritionValue = (value) =>
                ['string', 'number', 'boolean'].includes(typeof value) ? value : '';

            const nutritionRow = (label, value) =>
                value === '' || value === null || value === undefined
                    ? ''
                    : `<div><dt>${escapeNutritionHtml(label)}</dt><dd>${escapeNutritionHtml(value)}</dd></div>`;

            const buildFeedingSheet = (calculation, recommendation) => {
                if (!(feedingSheetContent instanceof HTMLElement)) {
                    return false;
                }

                const profile = calculation.profile;
                const result = calculation.result;

                if (!profile || typeof profile !== 'object' || !result || typeof result !== 'object') {
                    return false;
                }

                const calculatedDate = new Date(calculation.calculatedAt);

                if (Number.isNaN(calculatedDate.getTime())) {
                    return false;
                }

                const age = Number(profile.age);
                const ageUnit = profile.ageUnit === 'months'
                    ? (age === 1 ? 'mes' : 'meses')
                    : (age === 1 ? 'año' : 'años');

                const petName = String(scalarNutritionValue(profile.petName)).trim().slice(0, 60);

                const petRows = [
                    nutritionRow('Especie', nutritionLabels.species[profile.species] || scalarNutritionValue(profile.species)),
                    nutritionRow('Sexo', nutritionLabels.sex[profile.sex] || scalarNutritionValue(profile.sex)),
                    Number.isFinite(age) && age > 0 ? nutritionRow('Edad', `${age} ${ageUnit}`) : '',
                    Number.isFinite(Number(profile.weight)) ? nutritionRow('Peso actual', `${Number(profile.weight)} kg`) : '',
                    profile.idealWeight && Number.isFinite(Number(profile.idealWeight))
                        ? nutritionRow('Peso ideal', `${Number(profile.idealWeight)} kg`)
                        : '',
                    profile.species === 'perro'
                        ? nutritionRow('Tamaño', nutritionLabels.size[profile.size] || scalarNutritionValue(profile.size))
                        : '',
                    nutritionRow(
                        'Condición corporal',
                        nutritionLabels.bodyCondition[profile.bodyCondition] || scalarNutritionValue(profile.bodyCondition)
                    ),
                    nutritionRow('Actividad', nutritionLabels.activity[profile.activity] || scalarNutritionValue(profile.activity)),
                    nutritionRow('Esterilización', profile.sterilized === true ? 'Sí' : profile.sterilized === false ? 'No' : ''),
                    nutritionRow('Tipo de raza', nutritionLabels.breedType[profile.breedType] || scalarNutritionValue(profile.breedType)),
                    String(scalarNutritionValue(profile.breed)).trim()
                        ? nutritionRow('Raza', String(profile.breed).trim().slice(0, 80))
                        : '',
                    nutritionRow(
                        'Condición de salud',
                        nutritionLabels.health[profile.health] || scalarNutritionValue(profile.health)
                    ),
                    profile.allergy && profile.allergy !== 'none'
                        ? nutritionRow(
                            'Proteína o componente evitado',
                            nutritionLabels.allergy[profile.allergy] || scalarNutritionValue(profile.allergy)
                        )
                        : ''
                ].join('');

                const kcalKg = Number(recommendation.kcalKg);
                const energyLabel = Number.isFinite(kcalKg) && kcalKg > 0
                    ? `${Math.round(kcalKg).toLocaleString('es-CL')} kcal/kg${recommendation.energiaVerificada === false ? ' · Energía referencial' : ''
                }`
                    : '';

                const kcalDayLabel = Number.isFinite(Number(result.kcalDay))
                    ? Math.round(Number(result.kcalDay)).toLocaleString('es-CL')
                    : '—';

                const gramsDayLabel = Number.isFinite(Number(recommendation.gramsDay))
                    ? Math.round(Number(recommendation.gramsDay)).toLocaleString('es-CL')
                    : '—';

                const gramsMealLabel = Number.isFinite(Number(recommendation.gramsMeal))
                    ? Math.round(Number(recommendation.gramsMeal)).toLocaleString('es-CL')
                    : '—';

                const mealsLabel = Number(result.meals) > 0
                    ? Number(result.meals)
                    : '—';

                const stageLabel = nutritionLabels.stage[result.stage]
                    || scalarNutritionValue(result.stage)
                    || '—';

                feedingSheetContent.innerHTML = `
                    <header class="feeding-sheet__header">
                        <div class="feeding-sheet__brand">
                            <span class="feeding-sheet__brand-mark" aria-hidden="true">✦</span>
                            <div>
                                <span>CORATTO PET</span>
                                <p>PAUTA DE ALIMENTACIÓN</p>
                            </div>
                        </div>

                        <div class="feeding-sheet__heading">
                            <span>GUÍA PERSONALIZADA</span>
                            <h2 id="feeding-sheet-title">${escapeNutritionHtml(
                petName
                    ? `Pauta de alimentación de ${petName}`
                    : 'Pauta de alimentación'
            )
                }</h2>
                            <p>
                                ${escapeNutritionHtml(
                    petName
                        ? `Una referencia diaria preparada con los datos ingresados para ${petName}.`
                        : 'Una referencia diaria preparada con los datos ingresados en la calculadora.'
                )}
                            </p>
                        </div>
                    </header>

                    <section class="feeding-sheet__summary" aria-label="Resumen de la pauta">
                        <div>
                            <strong>${escapeNutritionHtml(gramsDayLabel)}</strong>
                            <span>g al día</span>
                        </div>
                        <div>
                            <strong>${escapeNutritionHtml(gramsMealLabel)}</strong>
                            <span>g por comida</span>
                        </div>
                        <div>
                            <strong>${escapeNutritionHtml(mealsLabel)}</strong>
                            <span>comidas</span>
                        </div>
                        <div>
                            <strong>${escapeNutritionHtml(stageLabel)}</strong>
                            <span>etapa estimada</span>
                        </div>
                    </section>

                    <div class="feeding-sheet__content-grid">
                        <section class="feeding-sheet__card">
                            <div class="feeding-sheet__section-title">
                                <span aria-hidden="true">01</span>
                                <h3>Datos de la mascota</h3>
                            </div>
                            <dl>${petRows}</dl>
                        </section>

                        <section class="feeding-sheet__card feeding-sheet__card--food">
                            <div class="feeding-sheet__section-title">
                                <span aria-hidden="true">02</span>
                                <h3>Alimento seleccionado</h3>
                            </div>
                            <dl>
                                ${nutritionRow('Marca', scalarNutritionValue(recommendation.marca))}
                                ${nutritionRow('Nombre', scalarNutritionValue(recommendation.nombre))}
                                ${nutritionRow('SKU', scalarNutritionValue(recommendation.sku))}
                                ${nutritionRow('Energía', energyLabel)}
                            </dl>
                        </section>
                    </div>

                    <section class="feeding-sheet__result-card">
                        <div>
                            <span class="feeding-sheet__result-eyebrow">RESUMEN DEL CÁLCULO</span>
                            <h3>La referencia diaria de ${escapeNutritionHtml(petName || 'tu mascota')}</h3>
                            <p>
                                ${escapeNutritionHtml(kcalDayLabel)} kcal estimadas al día,
                                distribuidas en ${escapeNutritionHtml(mealsLabel)} comidas.
                            </p>
                        </div>

                        <div class="feeding-sheet__result-focus">
                            <strong>${escapeNutritionHtml(gramsDayLabel)} g</strong>
                            <span>cantidad diaria estimada</span>
                        </div>
                    </section>

                    <div class="feeding-sheet__footer-info">
                        <p class="feeding-sheet__date">
                            Calculado el ${escapeNutritionHtml(
                    calculatedDate.toLocaleString('es-CL', {
                        dateStyle: 'long',
                        timeStyle: 'short'
                    })
                )
                }
                        </p>

                        <aside>
                            <strong>Importante</strong>
                            <span>
                                Esta pauta es una estimación orientativa y no reemplaza la evaluación
                                ni recomendación de un médico veterinario.
                            </span>
                        </aside>
                    </div>
                `;

                return true;
            };

            const loadNutritionCalculation = () => {
                if (!nutritionPanel) {
                    return;
                }

                try {
                    const stored = JSON.parse(
                        sessionStorage.getItem('coratto_nutrition_last_calculation') || 'null'
                    );

                    const currentProductId = Number(nutritionPanel.dataset.productId);

                    if (
                        !stored
                        || typeof stored !== 'object'
                        || Number(stored.selectedProductId) !== currentProductId
                        || !Array.isArray(stored.recommendations)
                    ) {
                        return;
                    }

                    const recommendation = stored.recommendations.find(
                        item =>
                            item
                            && typeof item === 'object'
                            && Number(item.productId) === currentProductId
                    );

                    if (
                        !recommendation
                        || !Number.isFinite(Number(recommendation.gramsDay))
                        || Number(recommendation.gramsDay) <= 0
                    ) {
                        return;
                    }

                    activeNutritionCalculation = stored;
                    activeNutritionRecommendation = recommendation;

                    const petName = String(stored.profile?.petName || '').trim();

                    nutritionPanel.querySelector('[data-nutrition-title]').textContent =
                        petName
                            ? `Tu pauta estimada para ${petName}`
                            : 'Tu pauta estimada para este alimento';

                    const nutritionDescription = nutritionPanel.querySelector(
                        '[data-nutrition-description]'
                    );

                    if (nutritionDescription) {
                        nutritionDescription.textContent = petName
                            ? `Esta pauta fue calculada con los datos que ingresaste para ${petName}.`
                            : 'Esta pauta fue calculada con los datos que ingresaste anteriormente.';
                    }

                    nutritionPanel.querySelector('[data-nutrition-grams-day]').textContent =
                        String(recommendation.gramsDay);

                    nutritionPanel.querySelector('[data-nutrition-meals]').textContent =
                        String(stored.result?.meals || '—');

                    nutritionPanel.querySelector('[data-nutrition-grams-meal]').textContent =
                        String(recommendation.gramsMeal || '—');

                    if (nutritionDefault) {
                        nutritionDefault.hidden = true;
                    }

                    if (nutritionResult) {
                        nutritionResult.hidden = false;
                    }

                    buildFeedingSheet(stored, recommendation);
                } catch (_) {
                    // Si storage falla, la invitación a calcular permanece visible.
                }
            };

            const addPdfLine = (doc, label, value, y) => {
                if (!value) {
                    return y;
                }

                doc.setFont('helvetica', 'bold');
                doc.setTextColor(92, 68, 51);
                doc.text(`${label}:`, 18, y);

                doc.setFont('helvetica', 'normal');
                doc.setTextColor(58, 45, 37);
                const lines = doc.splitTextToSize(String(value), 125);
                doc.text(lines, 58, y);

                return y + Math.max(6, lines.length * 5);
            };

            const downloadNutritionPdf = () => {
                if (!activeNutritionCalculation || !activeNutritionRecommendation) {
                    return;
                }

                const jsPDF = window.jspdf?.jsPDF;

                if (!jsPDF) {
                    alert('No pudimos preparar el PDF en este momento. Inténtalo nuevamente.');
                    return;
                }

                const calculation = activeNutritionCalculation;
                const recommendation = activeNutritionRecommendation;
                const profile = calculation.profile || {};
                const result = calculation.result || {};
                const petName = String(profile.petName || '').trim();

                const doc = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });

                const palette = {
                    brown: [81, 53, 38],
                    brownSoft: [106, 71, 52],
                    gold: [197, 138, 55],
                    cream: [251, 243, 231],
                    paper: [255, 250, 242],
                    muted: [118, 103, 94],
                    line: [226, 207, 183],
                    white: [255, 250, 242]
                };

                const lookup = (map, value) => map[value] || value || '';
                const age = Number(profile.age);
                const ageText = Number.isFinite(age) && age > 0
                    ? `${age} ${profile.ageUnit === 'months'
                    ? (age === 1 ? 'mes' : 'meses')
                    : (age === 1 ? 'año' : 'años')
                }`
                    : '';

                const gramsDay = Number.isFinite(Number(recommendation.gramsDay))
                    ? Math.round(Number(recommendation.gramsDay))
                    : null;

                const gramsMeal = Number.isFinite(Number(recommendation.gramsMeal))
                    ? Math.round(Number(recommendation.gramsMeal))
                    : null;

                const meals = Number(result.meals) > 0
                    ? Number(result.meals)
                    : null;

                const kcalDay = Number.isFinite(Number(result.kcalDay))
                    ? Math.round(Number(result.kcalDay))
                    : null;

                const stage = lookup(nutritionLabels.stage, result.stage) || '—';
                const calculatedDate = new Date(calculation.calculatedAt);

                doc.setFillColor(...palette.cream);
                doc.rect(0, 0, 210, 297, 'F');

                doc.setFillColor(...palette.brown);
                doc.roundedRect(10, 10, 190, 48, 5, 5, 'F');

                doc.setTextColor(...palette.gold);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8.5);
                doc.text('CORATTO PET  ·  PAUTA DE ALIMENTACIÓN', 18, 22);

                doc.setTextColor(...palette.white);
                doc.setFont('times', 'bold');
                doc.setFontSize(22);
                doc.text(
                    petName
                        ? `Pauta de alimentación de ${petName}`
                        : 'Pauta de alimentación',
                    18,
                    36,
                    { maxWidth: 165 }
                );

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8.5);
                doc.setTextColor(238, 220, 202);
                doc.text(
                    petName
                        ? `Referencia diaria preparada con los datos ingresados para ${petName}.`
                        : 'Referencia diaria preparada con los datos ingresados en la calculadora.',
                    18,
                    48,
                    { maxWidth: 160 }
                );

                const metricY = 67;
                const metricGap = 4;
                const metricWidth = (174 - metricGap * 3) / 4;
                const metrics = [
                    [gramsDay !== null ? `${gramsDay} g` : '—', 'al día'],
                    [gramsMeal !== null ? `${gramsMeal} g` : '—', 'por comida'],
                    [meals !== null ? String(meals) : '—', 'comidas'],
                    [stage, 'etapa']
                ];

                metrics.forEach(([value, label], index) => {
                    const x = 18 + index * (metricWidth + metricGap);

                    doc.setFillColor(...palette.brownSoft);
                    doc.roundedRect(x, metricY, metricWidth, 24, 3, 3, 'F');

                    doc.setDrawColor(...palette.gold);
                    doc.setLineWidth(.6);
                    doc.line(
                        x + metricWidth / 2 - 6,
                        metricY + 5,
                        x + metricWidth / 2 + 6,
                        metricY + 5
                    );

                    doc.setTextColor(...palette.white);
                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(index === 3 ? 12 : 15);
                    doc.text(
                        doc.splitTextToSize(String(value), metricWidth - 8),
                        x + metricWidth / 2,
                        metricY + 13,
                        { align: 'center' }
                    );

                    doc.setTextColor(235, 214, 195);
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(7.5);
                    doc.text(label, x + metricWidth / 2, metricY + 20, { align: 'center' });
                });

                const addSectionTitle = (number, title, x, y, width) => {
                    doc.setTextColor(...palette.gold);
                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(8);
                    doc.text(number, x, y);

                    doc.setTextColor(...palette.brown);
                    doc.setFontSize(10.5);
                    doc.text(title, x + 9, y);

                    doc.setDrawColor(...palette.line);
                    doc.setLineWidth(.35);
                    doc.line(x, y + 3, x + width, y + 3);
                };

                const drawKeyValue = (label, value, x, y, width) => {
                    if (value === '' || value === null || value === undefined) {
                        return y;
                    }

                    doc.setTextColor(...palette.muted);
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(7.5);
                    doc.text(label, x, y);

                    doc.setTextColor(...palette.brown);
                    doc.setFont('helvetica', 'bold');
                    doc.setFontSize(8.2);

                    const lines = doc.splitTextToSize(String(value), width - 28);
                    doc.text(lines, x + 28, y);

                    return y + Math.max(6, lines.length * 4.2);
                };

                const cardY = 101;
                const cardGap = 5;
                const cardWidth = (174 - cardGap) / 2;

                doc.setFillColor(...palette.paper);
                doc.roundedRect(18, cardY, cardWidth, 90, 4, 4, 'F');
                doc.setFillColor(...palette.paper);
                doc.roundedRect(18 + cardWidth + cardGap, cardY, cardWidth, 90, 4, 4, 'F');

                addSectionTitle('01', 'Datos de la mascota', 24, 113, cardWidth - 12);

                let leftY = 123;
                leftY = drawKeyValue('Especie', lookup(nutritionLabels.species, profile.species), 24, leftY, cardWidth - 12);
                leftY = drawKeyValue('Sexo', lookup(nutritionLabels.sex, profile.sex), 24, leftY, cardWidth - 12);
                leftY = drawKeyValue('Edad', ageText, 24, leftY, cardWidth - 12);
                leftY = drawKeyValue('Peso actual', profile.weight ? `${profile.weight} kg` : '', 24, leftY, cardWidth - 12);
                leftY = drawKeyValue('Peso ideal', profile.idealWeight ? `${profile.idealWeight} kg` : '', 24, leftY, cardWidth - 12);
                leftY = drawKeyValue('Condición', lookup(nutritionLabels.bodyCondition, profile.bodyCondition), 24, leftY, cardWidth - 12);
                leftY = drawKeyValue('Actividad', lookup(nutritionLabels.activity, profile.activity), 24, leftY, cardWidth - 12);
                leftY = drawKeyValue(
                    'Esterilización',
                    profile.sterilized === true ? 'Sí' : profile.sterilized === false ? 'No' : '',
                    24,
                    leftY,
                    cardWidth - 12
                );

                const rightX = 24 + cardWidth + cardGap;
                addSectionTitle('02', 'Alimento seleccionado', rightX, 113, cardWidth - 12);

                let rightY = 123;
                rightY = drawKeyValue('Marca', recommendation.marca, rightX, rightY, cardWidth - 12);
                rightY = drawKeyValue('Producto', recommendation.nombre, rightX, rightY, cardWidth - 12);
                rightY = drawKeyValue('SKU', recommendation.sku, rightX, rightY, cardWidth - 12);
                rightY = drawKeyValue(
                    'Energía',
                    recommendation.kcalKg
                        ? `${Math.round(Number(recommendation.kcalKg)).toLocaleString('es-CL')} kcal/kg${recommendation.energiaVerificada === false ? ' · referencial' : ''
                    }`
                        : '',
                    rightX,
                    rightY,
                    cardWidth - 12
                );
                rightY = drawKeyValue('Etapa', stage, rightX, rightY, cardWidth - 12);

                doc.setFillColor(244, 230, 211);
                doc.roundedRect(18, 201, 174, 38, 4, 4, 'F');

                doc.setTextColor(...palette.gold);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8);
                doc.text('RESUMEN DEL CÁLCULO', 24, 212);

                doc.setTextColor(...palette.brown);
                doc.setFont('times', 'bold');
                doc.setFontSize(14);
                doc.text(
                    petName
                        ? `La referencia diaria de ${petName}`
                        : 'La referencia diaria estimada',
                    24,
                    223
                );

                doc.setTextColor(...palette.muted);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.text(
                    kcalDay !== null && meals !== null
                        ? `${kcalDay.toLocaleString('es-CL')} kcal estimadas al día · ${meals} comidas recomendadas`
                        : 'Referencia orientativa según los datos ingresados.',
                    24,
                    231
                );

                doc.setTextColor(...palette.brown);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(18);
                doc.text(
                    gramsDay !== null ? `${gramsDay} g/día` : '—',
                    183,
                    222,
                    { align: 'right' }
                );

                doc.setTextColor(...palette.muted);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7.5);
                doc.text('cantidad diaria estimada', 183, 229, { align: 'right' });

                if (!Number.isNaN(calculatedDate.getTime())) {
                    doc.setTextColor(...palette.muted);
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(7.5);
                    doc.text(
                        `Calculado el ${calculatedDate.toLocaleString('es-CL', {
                        dateStyle: 'long',
                        timeStyle: 'short'
                    })}`,
                        18,
                        250
                    );
                }

                doc.setFillColor(237, 218, 194);
                doc.roundedRect(18, 258, 174, 20, 3, 3, 'F');

                doc.setTextColor(...palette.brown);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8);
                doc.text('IMPORTANTE', 24, 266);

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7.5);
                const disclaimer = doc.splitTextToSize(
                    'Esta pauta es una estimación orientativa y no reemplaza la evaluación ni recomendación de un médico veterinario.',
                    132
                );
                doc.text(disclaimer, 54, 266);

                const safePetName = petName
                    ? petName
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-|-$/g, '')
                    : 'mascota';

                doc.save(`pauta-coratto-${safePetName || 'mascota'}.pdf`);
            };

            document.querySelector('[data-feeding-sheet-open]')?.addEventListener('click', () => {
                if (
                    feedingSheetDialog instanceof HTMLDialogElement
                    && feedingSheetContent?.childElementCount
                ) {
                    feedingSheetDialog.showModal();
                }
            });

            document.querySelector('[data-feeding-sheet-close]')?.addEventListener('click', () => {
                feedingSheetDialog?.close();
            });

            document.querySelector('[data-feeding-sheet-download]')?.addEventListener(
                'click',
                downloadNutritionPdf
            );

            feedingSheetDialog?.addEventListener('click', (event) => {
                if (event.target === feedingSheetDialog) {
                    feedingSheetDialog.close();
                }
            });

            const quickPresentationBlock = document.querySelector('[data-presentation-quick-block]');
            const quickPresentationTitle = document.querySelector('[data-presentation-quick-title]');
            const quickPresentationMessage = document.querySelector('[data-presentation-quick-message]');
            const quickPresentationBadge = document.querySelector('[data-presentation-quick-badge]');
            const quickPresentationIdeal = document.querySelector('[data-presentation-quick-ideal]');
            const quickPresentationIdealText = document.querySelector('[data-presentation-quick-ideal-text]');

            if (
                quickPresentationBlock
                && quickPresentationTitle
                && quickPresentationMessage
                && quickPresentationBadge
                && quickPresentationIdeal
                && quickPresentationIdealText
            ) {
                const defaultQuickState = {
                    title: 'Elige el formato que mejor se adapte a tu rutina',
                    message: 'Selecciona una presentación y te contamos qué ventajas puede ofrecerte según el tamaño que elijas.'
                };

                const presentationMessages = {
                    practical: {
                        badge: '250 g',
                        title: 'Formato práctico & testeo',
                        message: 'Perfecto para viajes, snacks o como primera prueba de aceptación para tu mascota.',
                        ideal: 'Probar el alimento antes de pasar a un formato mayor.'
                    },
                    conservation: {
                        badge: '1 kg',
                        title: 'Conservación y economía',
                        message: 'Excelente opción de regalo o planificación semanal. Mantiene intactos el aroma, sabor y calidad del alimento Súper Premium de la máxima categoría.',
                        ideal: 'Consumo semanal y almacenamiento sencillo.'
                    },
                    fullBag: {
                        badge: 'Saco completo',
                        title: 'Formato Maxisabor',
                        message: 'Abastecimiento integral de larga duración.',
                        ideal: 'Consumo frecuente y reducir la necesidad de reposición.'
                    }
                };

                const renderQuickState = (state, active = true) => {
                    quickPresentationBlock.classList.remove('is-changing');
                    void quickPresentationBlock.offsetWidth;
                    quickPresentationBlock.classList.add('is-changing');
                    quickPresentationBlock.classList.toggle('is-active', active);

                    quickPresentationTitle.textContent = state.title;
                    quickPresentationMessage.textContent = state.message;

                    if (active && state.badge && state.ideal) {
                        quickPresentationBadge.textContent = state.badge;
                        quickPresentationBadge.hidden = false;
                        quickPresentationIdealText.textContent = state.ideal;
                        quickPresentationIdeal.hidden = false;
                    } else {
                        quickPresentationBadge.hidden = true;
                        quickPresentationIdeal.hidden = true;
                    }
                };

                const updateQuickPresentationMessage = (input) => {
                    if (!(input instanceof HTMLInputElement)) {
                        renderQuickState(defaultQuickState, false);
                        return;
                    }

                    const grams = Number.parseInt(input.dataset.presentationGrams || '', 10);

                    if (grams === 250) {
                        renderQuickState(presentationMessages.practical);
                        return;
                    }

                    if (grams === 1000) {
                        renderQuickState(presentationMessages.conservation);
                        return;
                    }

                    if (grams > 1000) {
                        const state = { ...presentationMessages.fullBag };
                        state.badge = `${new Intl.NumberFormat('es-CL').format(grams)} g · Saco completo`;
                        renderQuickState(state);
                        return;
                    }

                    renderQuickState(defaultQuickState, false);
                };

                const presentationInputs = document.querySelectorAll(
                    '.presentation-fieldset input[name="id_presentacion"][data-presentation-grams]'
                );

                presentationInputs.forEach((input) => {
                    input.addEventListener('change', () => {
                        if (input.checked) {
                            updateQuickPresentationMessage(input);
                        }
                    });
                });

                updateQuickPresentationMessage(
                    document.querySelector(
                        '.presentation-fieldset input[name="id_presentacion"][data-presentation-grams]:checked'
                    )
                );
            }

            document.querySelectorAll('.product-purchase-form').forEach((form) => {
                const input = form.querySelector('input[name="cantidad"]');
                const decrease = form.querySelector('[data-purchase-quantity="decrease"]');
                const increase = form.querySelector('[data-purchase-quantity="increase"]');

                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const clampQuantity = (value) => {
                    const minimum = Number.parseInt(input.min || '1', 10);
                    const maximum = Number.parseInt(input.max || '99', 10);

                    return Math.min(maximum, Math.max(minimum, value));
                };

                decrease?.addEventListener('click', () => {
                    const current = Number.parseInt(input.value || '1', 10);
                    input.value = String(clampQuantity(current - 1));
                });

                increase?.addEventListener('click', () => {
                    const current = Number.parseInt(input.value || '1', 10);
                    input.value = String(clampQuantity(current + 1));
                });

                input.addEventListener('change', () => {
                    const current = Number.parseInt(input.value || '1', 10);
                    input.value = String(
                        clampQuantity(Number.isFinite(current) ? current : 1)
                    );
                });
            });

            loadNutritionCalculation();
        </script>
    <?php endif; ?>
</body>

</html>