<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/shared/seguridad.php';
require_once dirname(__DIR__) . '/shared/funciones-carrito.php';
require_once dirname(__DIR__) . '/shared/funciones-checkout.php';
require_once __DIR__ . '/includes/consultas-publicas.php';

$config = [];
$cartItems = [];
$subtotal = 0;
$databaseError = false;
$removedInvalidItems = false;

try {
    $pdo = database();
    $config = obtenerConfiguracionPublica($pdo);

    foreach (obtenerCarritoSesion() as $clave => $linea) {
        $idProducto = (int) ($linea['id_producto'] ?? 0);
        $idPresentacion = isset($linea['id_presentacion']) && $linea['id_presentacion'] !== null
            ? (int) $linea['id_presentacion']
            : null;
        $cantidad = max(1, (int) ($linea['cantidad'] ?? 1));

        $item = obtenerItemCarritoPublico($pdo, $idProducto, $idPresentacion);

        if ($item === null) {
            eliminarItemCarritoSesion($clave);
            $removedInvalidItems = true;
            continue;
        }

        if (
            $idPresentacion === null
            && (int) ($item['cantidad_presentaciones_activas'] ?? 0) > 0
        ) {
            eliminarItemCarritoSesion($clave);
            $removedInvalidItems = true;
            continue;
        }

        $precioUnitario = max(0, (int) ($item['precio_venta'] ?? 0));
        $cantidadDisponible = max(0, (int) ($item['cantidad_disponible'] ?? 0));
        $itemSubtotal = $precioUnitario * $cantidad;

        $cartItems[] = [
            'clave' => $clave,
            'id_producto' => $idProducto,
            'id_presentacion' => $idPresentacion,
            'nombre_producto' => (string) ($item['nombre_producto'] ?? ''),
            'nombre_item' => (string) ($item['nombre_item'] ?? ''),
            'sku' => (string) ($item['sku'] ?? ''),
            'marca' => (string) ($item['marca'] ?? ''),
            'categoria' => (string) ($item['categoria'] ?? ''),
            'imagen' => (string) ($item['imagen'] ?? ''),
            'tipo_item' => (string) ($item['tipo_item'] ?? 'producto'),
            'cantidad_gramos' => isset($item['cantidad_gramos'])
                ? (int) $item['cantidad_gramos']
                : null,
            'cantidad' => $cantidad,
            'cantidad_disponible' => $cantidadDisponible,
            'precio_unitario' => $precioUnitario,
            'subtotal' => $itemSubtotal,
            'disponible' => (bool) ($item['disponible'] ?? false),
            'stock_insuficiente' => $cantidad > $cantidadDisponible,
        ];

        $subtotal += $itemSubtotal;
    }
} catch (Throwable $exception) {
    error_log('Error al cargar el carrito público: ' . $exception->getMessage());
    $databaseError = true;
    http_response_code(503);
}

$resultado = is_scalar($_GET['resultado'] ?? null)
    ? trim((string) $_GET['resultado'])
    : '';

$messages = [
    'actualizado' => [
        'tipo' => 'exito',
        'texto' => 'La cantidad fue actualizada correctamente.',
    ],
    'eliminado' => [
        'tipo' => 'exito',
        'texto' => 'El producto fue eliminado del carrito.',
    ],
    'sesion_expirada' => [
        'tipo' => 'error',
        'texto' => 'La sesión del formulario expiró. Inténtalo nuevamente.',
    ],
    'item_invalido' => [
        'tipo' => 'error',
        'texto' => 'El producto seleccionado no es válido.',
    ],
    'item_no_encontrado' => [
        'tipo' => 'error',
        'texto' => 'Ese producto ya no se encuentra en tu carrito.',
    ],
    'item_no_disponible' => [
        'tipo' => 'error',
        'texto' => 'El producto ya no está disponible y fue retirado del carrito.',
    ],
    'presentacion_requerida' => [
        'tipo' => 'error',
        'texto' => 'El producto ahora requiere elegir una presentación y fue retirado del carrito.',
    ],
    'cantidad_invalida' => [
        'tipo' => 'error',
        'texto' => 'La cantidad seleccionada no es válida.',
    ],
    'sin_stock' => [
        'tipo' => 'error',
        'texto' => 'El producto seleccionado se encuentra sin stock.',
    ],
    'stock_insuficiente' => [
        'tipo' => 'error',
        'texto' => 'La cantidad solicitada supera el stock disponible.',
    ],
    'error_temporal' => [
        'tipo' => 'error',
        'texto' => 'No pudimos actualizar el carrito en este momento.',
    ],
];

$message = $messages[$resultado] ?? null;

if ($removedInvalidItems && $message === null) {
    $message = [
        'tipo' => 'aviso',
        'texto' => 'Retiramos del carrito algunos productos que ya no se encuentran disponibles.',
    ];
}

$money = static function (mixed $value): string {
    return '$' . number_format((float) $value, 0, ',', '.');
};

$resolveProductImageUrl = static function (mixed $value): string {
    $path = ltrim(str_replace('\\', '/', trim((string) $value)), '/');

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

$currentPage = 'carrito';
$whatsappUrl = obtenerWhatsappPublico($config);
$totalUnits = contarUnidadesCarritoSesion();
$csrfToken = csrfToken();

$checkoutIssues = array_filter(
    $cartItems,
    static fn (array $item): bool =>
        !$item['disponible'] || $item['stock_insuficiente']
);

$modalidadesEntrega = modalidadesEntregaCheckout($config);

$minimosDisponibles = [];

if (isset($modalidadesEntrega['despacho'])) {
    $minimosDisponibles['despacho'] = obtenerMontoMinimoCheckout(
        $config,
        'despacho'
    );
}

if (isset($modalidadesEntrega['retiro_en_tienda'])) {
    $minimosDisponibles['retiro_en_tienda'] = obtenerMontoMinimoCheckout(
        $config,
        'retiro_en_tienda'
    );
}

$montoMinimoParaContinuar = $minimosDisponibles !== []
    ? min($minimosDisponibles)
    : null;

$metodoMinimoParaContinuar = $montoMinimoParaContinuar !== null
    ? array_search(
        $montoMinimoParaContinuar,
        $minimosDisponibles,
        true
    )
    : false;

$textoMetodoMinimo = $metodoMinimoParaContinuar === 'retiro_en_tienda'
    ? 'retiro en tienda'
    : 'despacho';

$canContinueToCheckout = (
    !$databaseError
    && $cartItems !== []
    && $montoMinimoParaContinuar !== null
    && $subtotal >= $montoMinimoParaContinuar
    && $checkoutIssues === []
);

$missingForMinimum = $montoMinimoParaContinuar !== null
    ? max(0, $montoMinimoParaContinuar - $subtotal)
    : 0;

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Revisa los productos seleccionados en tu carrito Coratto Pet.">
    <title>Carrito | Coratto Pet</title>

    <link rel="stylesheet"
        href="<?= e(appUrl('public/assets/css/home.css')) ?>?v=<?= filemtime(__DIR__ . '/assets/css/home.css') ?>">
    <link rel="stylesheet"
        href="<?= e(appUrl('public/assets/css/public-pages.css')) ?>?v=<?= filemtime(__DIR__ . '/assets/css/public-pages.css') ?>">
    <link rel="stylesheet"
        href="<?= e(appUrl('public/assets/css/carrito.css')) ?>?v=<?= filemtime(__DIR__ . '/assets/css/carrito.css') ?>">
</head>

<body class="cart-page">
    <?php require __DIR__ . '/includes/public-header.php'; ?>

    <main id="contenido">
        <section class="cart-shell">
            <header class="cart-heading">
                <span>Tu selección Coratto</span>
                <h1>Carrito de compra</h1>
                <p>Revisa las cantidades antes de continuar con los datos de despacho.</p>
            </header>

            <?php if ($message !== null): ?>
                <div class="cart-feedback cart-feedback--<?= e($message['tipo']) ?>"
                    role="<?= $message['tipo'] === 'error' ? 'alert' : 'status' ?>">
                    <?= e($message['texto']) ?>
                </div>
            <?php endif; ?>

            <?php if ($databaseError): ?>
                <section class="state-panel">
                    <span>Carrito temporalmente no disponible</span>
                    <h2>No pudimos cargar tus productos</h2>
                    <p>Inténtalo nuevamente en unos minutos o escríbenos para recibir ayuda.</p>
                    <a class="button" href="<?= e($whatsappUrl) ?>">Hablar con Coratto</a>
                </section>

            <?php elseif ($cartItems === []): ?>
                <section class="empty-cart">
                    <span>Tu carrito está vacío</span>
                    <h2>Aún no has añadido productos</h2>
                    <p>Explora nuestro catálogo y selecciona las presentaciones que necesites.</p>
                    <a class="button" href="<?= e(appUrl('public/catalogo.php')) ?>">Ver catálogo</a>
                </section>

            <?php else: ?>
                <div class="cart-layout">
                    <section class="cart-items" aria-label="Productos del carrito">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $imageUrl = $resolveProductImageUrl($item['imagen']);
                            $productUrl = appUrl(
                                'public/catalogo.php?sku=' . rawurlencode($item['sku'])
                            );
                            $quantityInputId = 'cantidad-' . md5($item['clave']);
                            ?>

                            <article class="cart-item">
                                <div class="cart-item__image">
                                    <?php if ($imageUrl !== ''): ?>
                                        <img src="<?= e($imageUrl) ?>" alt="<?= e($item['nombre_producto']) ?>">
                                    <?php else: ?>
                                        <div class="image-placeholder">
                                            Coratto Pet
                                            <small>Imagen no disponible</small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="cart-item__information">
                                    <span class="cart-item__meta">
                                        <?= e($item['marca']) ?> · <?= e($item['categoria']) ?>
                                    </span>

                                    <h2>
                                        <a href="<?= e($productUrl) ?>">
                                            <?= e($item['nombre_producto']) ?>
                                        </a>
                                    </h2>

                                    <p class="cart-item__presentation">
                                        <span>Presentación:</span>
                                        <strong>
                                            <?= e(
                                                $item['tipo_item'] === 'presentacion'
                                                    ? $item['nombre_item']
                                                    : 'Unidad'
                                            ) ?>
                                        </strong>

                                        <?php if (
                                            $item['tipo_item'] === 'presentacion'
                                            && $item['cantidad_gramos'] !== null
                                            && $item['cantidad_gramos'] > 0
                                        ): ?>
                                            <span>
                                                <?= e(number_format($item['cantidad_gramos'], 0, ',', '.')) ?> g
                                            </span>
                                        <?php endif; ?>
                                    </p>

                                    <?php if ($item['sku'] !== ''): ?>
                                        <small>SKU: <?= e($item['sku']) ?></small>
                                    <?php endif; ?>

                                    <?php if (!$item['disponible']): ?>
                                        <p class="cart-item__warning" role="alert">
                                            Este producto se encuentra temporalmente sin stock.
                                        </p>
                                    <?php elseif ($item['stock_insuficiente']): ?>
                                        <p class="cart-item__warning" role="alert">
                                            Solo quedan <?= e($item['cantidad_disponible']) ?> unidades disponibles.
                                        </p>
                                    <?php endif; ?>
                                    <div class="cart-item__actions">
                                        <form class="cart-quantity-form" method="post"
                                            action="<?= e(appUrl('public/acciones-carrito/actualizar.php')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="clave" value="<?= e($item['clave']) ?>">

                                            <label for="<?= e($quantityInputId) ?>">Cantidad</label>

                                            <div class="cart-quantity-control">
                                                <button class="cart-quantity-button" type="button"
                                                    data-quantity-action="decrease" aria-label="Disminuir cantidad">
                                                    −
                                                </button>

                                                <input id="<?= e($quantityInputId) ?>" class="cart-quantity-input" type="number"
                                                    name="cantidad" value="<?= e($item['cantidad']) ?>" min="1" max="<?= e(max(
                                                          1,
                                                          min(
                                                              CARRITO_CANTIDAD_MAXIMA,
                                                              $item['cantidad_disponible']
                                                          )
                                                      )) ?>" inputmode="numeric" required>

                                                <button class="cart-quantity-button" type="button"
                                                    data-quantity-action="increase" aria-label="Aumentar cantidad">
                                                    +
                                                </button>
                                            </div>

                                            <button type="submit" class="button cart-update-button">
                                                Actualizar
                                            </button>
                                        </form>

                                        <form class="cart-remove-form" method="post"
                                            action="<?= e(appUrl('public/acciones-carrito/eliminar.php')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="clave" value="<?= e($item['clave']) ?>">

                                            <button
                                                type="submit"
                                                class="cart-remove-button"
                                                aria-label="Eliminar producto del carrito"
                                                title="Eliminar producto"
                                            >
                                                <svg
                                                    aria-hidden="true"
                                                    viewBox="0 0 24 24"
                                                    focusable="false"
                                                >
                                                    <path
                                                        d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-2 6h10l-.7 10.1A2 2 0 0 1 14.3 21H9.7a2 2 0 0 1-2-1.9L7 9Zm3 2v7h2v-7h-2Zm4 0v7h2v-7h-2Z"
                                                    />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="cart-item__price">
                                    <span><?= e($money($item['precio_unitario'])) ?> c/u</span>
                                    <strong><?= e($money($item['subtotal'])) ?></strong>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <aside class="cart-summary">
                        <span>Resumen</span>
                        <h2>Tu compra</h2>

                        <dl>
                            <div>
                                <dt>Productos</dt>
                                <dd>
                                    <?= e($totalUnits) ?>
                                    unidad<?= $totalUnits === 1 ? '' : 'es' ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Subtotal</dt>
                                <dd><?= e($money($subtotal)) ?></dd>
                            </div>

                            <div>
                                <dt>Despacho</dt>
                                <dd>Se calculará después</dd>
                            </div>
                        </dl>

                        <div class="cart-summary__total">
                            <span>Total parcial</span>
                            <strong><?= e($money($subtotal)) ?></strong>
                        </div>

                        <p>
                            El valor final se calculará cuando selecciones región,
                            comuna y modalidad de despacho.
                        </p>

                        <?php if ($canContinueToCheckout): ?>
                            <a
                                class="button"
                                href="<?= e(appUrl('public/checkout.php')) ?>"
                            >
                                ELEGIR ENTREGA
                            </a>
                        <?php else: ?>
                            <button class="button" type="button" disabled>
                                ELEGIR ENTREGA
                            </button>

                            <?php if ($missingForMinimum > 0): ?>
                                <small class="cart-summary__minimum">
                                    Te faltan <?= e($money($missingForMinimum)) ?>
                                    para alcanzar la compra mínima de
                                    <?= e($textoMetodoMinimo) ?>.
                                </small>
                            <?php elseif ($checkoutIssues !== []): ?>
                                <small class="cart-summary__minimum">
                                    Revisa disponibilidad y cantidades antes de continuar.
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a class="cart-summary__continue" href="<?= e(appUrl('public/catalogo.php')) ?>">
                            Seguir comprando
                        </a>
                    </aside>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require __DIR__ . '/includes/public-footer.php'; ?>

    <script
        src="<?= e(appUrl('public/assets/js/public-navigation.js')) ?>?v=<?= filemtime(__DIR__ . '/assets/js/public-navigation.js') ?>"
        defer></script>

    <script src="assets/js/carrito.js?v=<?= filemtime(__DIR__ . '/assets/js/carrito.js') ?>" defer></script>
</body>

</html>