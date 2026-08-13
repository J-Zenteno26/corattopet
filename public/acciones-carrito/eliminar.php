<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/config/app.php';
require_once $projectRoot . '/shared/seguridad.php';
require_once $projectRoot . '/shared/funciones-carrito.php';

/**
 * Redirige nuevamente al carrito con un resultado controlado.
 */
function redirigirDespuesDeEliminar(string $resultado): never
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
    redirigirDespuesDeEliminar('sesion_expirada');
}

$clave = is_scalar($_POST['clave'] ?? null)
    ? trim((string) $_POST['clave'])
    : '';

if ($clave === '' || mb_strlen($clave) > 150) {
    redirigirDespuesDeEliminar('item_invalido');
}

try {
    eliminarItemCarritoSesion($clave);

    redirigirDespuesDeEliminar('eliminado');
} catch (Throwable $exception) {
    error_log(
        'Error al eliminar una línea del carrito: '
        . $exception->getMessage()
    );

    redirigirDespuesDeEliminar('error_temporal');
}