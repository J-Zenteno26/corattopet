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
$presentationId = idPositivoPresentacion($_POST['id_presentacion'] ?? null);
[$values, $errors] = validarPresentacion($_POST);
$key = 'presentacion_editar_' . ($presentationId ?? 0);
$formUrl = appUrl('admin/inventario/presentaciones/editar.php?id=' . ($presentationId ?? 0));
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoPresentacion($key, $values, [], 'La solicitud no es válida.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
if ($presentationId === null) {
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=presentaciones_no_disponibles'), true, 303);
    exit;
}
try {
    $connection = database();
    $presentation = buscarPresentacion($connection, $presentationId);
    $product = $presentation === null ? null : buscarProductoFraccionable($connection, (int) $presentation['id_producto']);
    if ($presentation === null || $product === null) {
        header('Location: ' . appUrl('admin/inventario/index.php?mensaje=presentaciones_no_disponibles'), true, 303);
        exit;
    }
    if ($values['sku'] !== '' && existeSkuPresentacion($connection, $values['sku'], $presentationId))
        $errors['sku'] = 'Ya existe una presentación con este SKU.';
    if ($errors !== []) {
        guardarEstadoPresentacion($key, $values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }
    $statement = $connection->prepare('UPDATE producto_presentaciones SET nombre=:nombre, cantidad_gramos=:cantidad_gramos, precio_venta=:precio_venta, sku=:sku, activo=:activo, orden=:orden, actualizado_en=CURRENT_TIMESTAMP WHERE id_presentacion=:id_presentacion');
    $statement->execute(['nombre' => $values['nombre'], 'cantidad_gramos' => (int) $values['cantidad_gramos'], 'precio_venta' => (int) $values['precio_venta'], 'sku' => $values['sku'] === '' ? null : $values['sku'], 'activo' => $values['activo'], 'orden' => (int) $values['orden'], 'id_presentacion' => $presentationId]);
    header('Location: ' . appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $presentation['id_producto'] . '&mensaje=actualizada'), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Presentation update error: ' . $exception->getMessage());
    guardarEstadoPresentacion($key, $values, $errors, 'No fue posible actualizar la presentación.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
