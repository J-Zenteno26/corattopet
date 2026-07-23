<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/shared/seguridad.php';
require_once dirname(__DIR__, 4) . '/config/database.php';
require_once dirname(__DIR__, 4) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 4) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-imagenes-producto.php';

requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$productId = idPositivoImagenProducto($_POST['id_producto'] ?? null);
$imageId = idPositivoImagenProducto($_POST['id_imagen'] ?? null);
$destination = $productId === null
    ? appUrl('admin/inventario/index.php')
    : appUrl('admin/inventario/productos/editar.php?id=' . $productId);
if (!validateCsrfToken($_POST['csrf_token'] ?? null) || $productId === null || $imageId === null) {
    guardarModalAdmin('error', 'No fue posible actualizar la imagen principal', 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $destination, true, 303);
    exit;
}

try {
    if (!marcarImagenPrincipalProducto(database(), $productId, $imageId)) {
        throw new ImagenProductoException('La imagen indicada no pertenece al producto o ya no está disponible.');
    }
    guardarModalAdmin('success', 'Imagen principal actualizada', 'La imagen principal del producto fue actualizada correctamente.');
} catch (ImagenProductoException $exception) {
    guardarModalAdmin('error', 'No fue posible actualizar la imagen principal', $exception->getMessage());
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Product primary image error', $exception);
    guardarModalAdmin('error', 'No fue posible actualizar la imagen principal', 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', ['reference' => $reference]);
}
header('Location: ' . $destination, true, 303);
exit;
