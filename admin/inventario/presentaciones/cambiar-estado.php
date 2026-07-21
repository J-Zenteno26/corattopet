<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
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
    guardarModalAdmin('error', 'No fue posible cambiar el estado de la presentación', 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
    exit;
}
try {
    $connection = database();
    $presentation = buscarPresentacion($connection, $presentationId);
    $product = $presentation === null ? null : buscarProductoFraccionable($connection, (int) $presentation['id_producto']);
    if ($presentation === null || $product === null) {
        guardarModalAdmin('error', 'No fue posible cambiar el estado de la presentación', 'La presentación indicada no está disponible.');
        header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
        exit;
    }
    $statement = $connection->prepare('UPDATE producto_presentaciones SET activo=NOT activo, actualizado_en=CURRENT_TIMESTAMP WHERE id_presentacion=:id_presentacion RETURNING activo');
    $statement->bindValue(':id_presentacion', $presentationId, PDO::PARAM_INT);
    $statement->execute();
    $active = in_array($statement->fetchColumn(), [true, 1, '1', 't', 'true'], true);
    guardarModalAdmin('success', $active ? 'Presentación activada' : 'Presentación desactivada', $active ? 'La presentación fue activada correctamente.' : 'La presentación fue desactivada correctamente.');
    header('Location: ' . appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $presentation['id_producto']), true, 303);
    exit;
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Presentation state error', $exception);
    guardarModalAdmin('error', 'No fue posible cambiar el estado de la presentación', 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', ['reference' => $reference]);
    $destination = isset($presentation['id_producto']) ? appUrl('admin/inventario/presentaciones/index.php?id_producto=' . (int) $presentation['id_producto']) : appUrl('admin/inventario/index.php');
    header('Location: ' . $destination, true, 303);
    exit;
}
