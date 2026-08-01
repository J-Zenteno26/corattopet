<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-proveedores.php';
requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$id = idProveedorValido($_POST['id_proveedor'] ?? null);
$association = idProveedorValido($_POST['id_proveedor_producto'] ?? null);
$back = $id ? appUrl('admin/proveedores/ver.php?id_proveedor=' . $id) : appUrl('admin/proveedores/index.php');
if ($id === null || $association === null || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarModalAdmin('error', 'Solicitud inválida', 'Recarga la página e intenta nuevamente.');
    header('Location: ' . $back, true, 303);
    exit;
}
try {
    $st = database()->prepare('UPDATE proveedor_productos SET activo=NOT activo WHERE id_proveedor_producto=:association AND id_proveedor=:provider RETURNING activo');
    $st->execute(['association' => $association, 'provider' => $id]);
    $row = $st->fetch();
    if (!is_array($row))
        throw new RuntimeException('Association not found');
    $active = booleanoPostgresMantenedor($row['activo']);
    guardarModalAdmin('success', $active ? 'Asociación activada' : 'Asociación desactivada', 'El estado fue actualizado correctamente.');
} catch (Throwable $e) {
    $reference = registrarExcepcionAdmin('Supplier product status error', $e);
    guardarModalAdmin('error', 'No fue posible cambiar el estado', 'Intenta nuevamente.', ['reference' => $reference]);
}
header('Location: ' . $back, true, 303);
exit;
