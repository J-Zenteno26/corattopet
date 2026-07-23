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
$destination = $productId === null
    ? appUrl('admin/inventario/index.php')
    : appUrl('admin/inventario/productos/editar.php?id=' . $productId);
if (!validateCsrfToken($_POST['csrf_token'] ?? null) || $productId === null) {
    guardarModalAdmin('error', 'No fue posible subir la imagen', 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $destination, true, 303);
    exit;
}

try {
    guardarImagenProducto(
        database(),
        $productId,
        is_array($_FILES['imagen'] ?? null) ? $_FILES['imagen'] : [],
        $_POST['alt_text'] ?? null
    );
    guardarModalAdmin('success', 'Imagen subida', 'La imagen del producto fue registrada correctamente.');
} catch (ImagenProductoException $exception) {
    guardarModalAdmin('error', 'No fue posible subir la imagen', 'Revisa el archivo seleccionado e intenta nuevamente.', ['detail' => $exception->getMessage()]);
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Product image upload error', $exception);
    guardarModalAdmin('error', 'No fue posible subir la imagen', 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', ['reference' => $reference]);
}
header('Location: ' . $destination, true, 303);
exit;
