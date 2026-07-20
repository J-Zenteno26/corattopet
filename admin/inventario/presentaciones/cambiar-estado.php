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
if (!validateCsrfToken($_POST['csrf_token'] ?? null) || $presentationId === null) {
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=error'), true, 303);
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
    $statement = $connection->prepare('UPDATE producto_presentaciones SET activo=NOT activo, actualizado_en=CURRENT_TIMESTAMP WHERE id_presentacion=:id_presentacion');
    $statement->execute(['id_presentacion' => $presentationId]);
    header('Location: ' . appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $presentation['id_producto'] . '&mensaje=estado'), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Presentation state error: ' . $exception->getMessage());
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=error'), true, 303);
    exit;
}
