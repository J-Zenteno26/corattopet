<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/consultas-presentaciones.php';
require_once __DIR__ . '/includes/validaciones-presentacion.php';
requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
$productId = idPositivoPresentacion($_POST['id_producto'] ?? null);
[$values, $errors] = validarPresentacion($_POST);
$key = 'presentacion_crear_' . ($productId ?? 0);
$formUrl = appUrl('admin/inventario/presentaciones/crear.php?id_producto=' . ($productId ?? 0));
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoPresentacion($key, $values, [], 'La solicitud no es válida.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
if ($productId === null) {
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=presentaciones_no_disponibles'), true, 303);
    exit;
}
try {
    $connection = database();
    if (buscarProductoFraccionable($connection, $productId) === null) {
        header('Location: ' . appUrl('admin/inventario/index.php?mensaje=presentaciones_no_disponibles'), true, 303);
        exit;
    }
    if ($values['sku'] !== '' && existeSkuPresentacion($connection, $values['sku']))
        $errors['sku'] = 'Ya existe una presentación con este SKU.';
    if ($errors !== []) {
        guardarEstadoPresentacion($key, $values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }
    $statement = $connection->prepare('INSERT INTO producto_presentaciones (id_producto, nombre, cantidad_gramos, precio_venta, sku, activo, orden) VALUES (:id_producto, :nombre, :cantidad_gramos, :precio_venta, :sku, :activo, :orden)');
    $statement->execute(['id_producto' => $productId, 'nombre' => $values['nombre'], 'cantidad_gramos' => (int) $values['cantidad_gramos'], 'precio_venta' => (int) $values['precio_venta'], 'sku' => $values['sku'] === '' ? null : $values['sku'], 'activo' => $values['activo'], 'orden' => (int) $values['orden']]);
    header('Location: ' . appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $productId . '&mensaje=creada'), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Presentation creation error: ' . $exception->getMessage());
    guardarEstadoPresentacion($key, $values, $errors, 'No fue posible guardar la presentación.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
