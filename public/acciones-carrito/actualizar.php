<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-carrito.php';
require_once dirname(__DIR__) . '/includes/consultas-publicas.php';

/**
 * Redirige nuevamente al carrito con un resultado controlado.
 */
function redirigirDespuesDeActualizar(string $resultado): never
{
    $ruta = 'public/carrito.php?' . http_build_query([
        'resultado' => $resultado,
    ]);

    header('Location: ' . appUrl($ruta), true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    redirigirDespuesDeActualizar('sesion_expirada');
}

$clave = is_scalar($_POST['clave'] ?? null)
    ? trim((string) $_POST['clave'])
    : '';

if ($clave === '' || mb_strlen($clave) > 150) {
    redirigirDespuesDeActualizar('item_invalido');
}

try {
    $cantidad = normalizarCantidadCarrito(
        $_POST['cantidad'] ?? null
    );
} catch (InvalidArgumentException) {
    redirigirDespuesDeActualizar('cantidad_invalida');
}

try {
    /*
     * Los identificadores reales se obtienen desde la sesión.
     * No se confía en id_producto o id_presentacion enviados por el formulario.
     */
    $carrito = obtenerCarritoSesion();

    if (!isset($carrito[$clave])) {
        redirigirDespuesDeActualizar('item_no_encontrado');
    }

    $linea = $carrito[$clave];

    $idProducto = (int) ($linea['id_producto'] ?? 0);

    $idPresentacion = isset($linea['id_presentacion'])
        && $linea['id_presentacion'] !== null
            ? (int) $linea['id_presentacion']
            : null;

    if ($idProducto <= 0) {
        eliminarItemCarritoSesion($clave);
        redirigirDespuesDeActualizar('item_no_disponible');
    }

    $pdo = database();

    $item = obtenerItemCarritoPublico(
        $pdo,
        $idProducto,
        $idPresentacion
    );

    /*
     * Si el producto o presentación dejó de existir, fue desactivado
     * o ya no pertenece al catálogo público, se elimina del carrito.
     */
    if ($item === null) {
        eliminarItemCarritoSesion($clave);

        redirigirDespuesDeActualizar('item_no_disponible');
    }

    /*
     * Un producto que ahora tiene presentaciones activas ya no puede
     * mantenerse como producto base dentro del carrito.
     */
    if (
        $idPresentacion === null
        && (int) ($item['cantidad_presentaciones_activas'] ?? 0) > 0
    ) {
        eliminarItemCarritoSesion($clave);

        redirigirDespuesDeActualizar(
            'presentacion_requerida'
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
        redirigirDespuesDeActualizar('sin_stock');
    }

    if ($cantidad > $cantidadDisponible) {
        redirigirDespuesDeActualizar(
            'stock_insuficiente'
        );
    }

    actualizarCantidadItemCarritoSesion(
        $clave,
        $cantidad
    );

    redirigirDespuesDeActualizar('actualizado');
} catch (Throwable $exception) {
    error_log(
        'Error al actualizar una línea del carrito: '
        . $exception->getMessage()
    );

    redirigirDespuesDeActualizar('error_temporal');
}