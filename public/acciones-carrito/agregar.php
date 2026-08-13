<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-carrito.php';
require_once dirname(__DIR__) . '/includes/consultas-publicas.php';

/**
 * Regresa a la ficha del producto con un resultado controlado.
 */
function redirigirDespuesDeAgregar(
    string $skuRetorno,
    string $resultado
): never {
    $ruta = 'public/catalogo.php';

    if ($skuRetorno !== '') {
        $ruta .= '?' . http_build_query([
            'sku' => $skuRetorno,
            'carrito' => $resultado,
        ]);
    } else {
        $ruta .= '?' . http_build_query([
            'carrito' => $resultado,
        ]);
    }

    header('Location: ' . appUrl($ruta), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}

$skuRetorno = is_scalar($_POST['sku_retorno'] ?? null)
    ? trim((string) $_POST['sku_retorno'])
    : '';

$skuRetorno = mb_substr($skuRetorno, 0, 150);

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    redirigirDespuesDeAgregar($skuRetorno, 'sesion_expirada');
}

$idProducto = filter_var(
    $_POST['id_producto'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($idProducto === false) {
    redirigirDespuesDeAgregar($skuRetorno, 'producto_invalido');
}

$idPresentacion = null;
$presentacionRecibida = $_POST['id_presentacion'] ?? null;

if (
    $presentacionRecibida !== null
    && $presentacionRecibida !== ''
) {
    $idPresentacionValidada = filter_var(
        $presentacionRecibida,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idPresentacionValidada === false) {
        redirigirDespuesDeAgregar(
            $skuRetorno,
            'presentacion_invalida'
        );
    }

    $idPresentacion = (int) $idPresentacionValidada;
}

try {
    $cantidad = normalizarCantidadCarrito(
        $_POST['cantidad'] ?? null
    );
} catch (InvalidArgumentException) {
    redirigirDespuesDeAgregar($skuRetorno, 'cantidad_invalida');
}

try {
    $pdo = database();

    $item = obtenerItemCarritoPublico(
        $pdo,
        (int) $idProducto,
        $idPresentacion
    );

    if ($item === null) {
        redirigirDespuesDeAgregar(
            $skuRetorno,
            'producto_no_disponible'
        );
    }

    /*
     * Un producto con presentaciones activas no puede agregarse como
     * producto base. El cliente debe elegir uno de sus formatos.
     */
    if (
        $idPresentacion === null
        && (int) ($item['cantidad_presentaciones_activas'] ?? 0) > 0
    ) {
        redirigirDespuesDeAgregar(
            $skuRetorno,
            'selecciona_presentacion'
        );
    }

    $cantidadDisponible = max(
        0,
        (int) ($item['cantidad_disponible'] ?? 0)
    );

    if (
        !($item['disponible'] ?? false)
        || $cantidadDisponible < 1
    ) {
        redirigirDespuesDeAgregar(
            $skuRetorno,
            'sin_stock'
        );
    }

    $carrito = obtenerCarritoSesion();

    $clave = generarClaveItemCarrito(
        (int) $idProducto,
        $idPresentacion
    );

    $cantidadActual = isset($carrito[$clave])
        ? (int) $carrito[$clave]['cantidad']
        : 0;

    if (($cantidadActual + $cantidad) > $cantidadDisponible) {
        redirigirDespuesDeAgregar(
            $skuRetorno,
            'stock_insuficiente'
        );
    }

    agregarItemCarritoSesion(
        (int) $idProducto,
        $idPresentacion,
        $cantidad
    );

    redirigirDespuesDeAgregar($skuRetorno, 'agregado');
} catch (Throwable $exception) {
    error_log(
        'Error al agregar producto al carrito: '
        . $exception->getMessage()
    );

    redirigirDespuesDeAgregar(
        $skuRetorno,
        'error_temporal'
    );
}