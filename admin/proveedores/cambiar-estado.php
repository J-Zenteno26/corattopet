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
$back = appUrl('admin/proveedores/index.php');
if ($id === null || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarModalAdmin('error', 'Solicitud inválida', 'Recarga la página e intenta nuevamente.');
    header('Location: ' . $back, true, 303);
    exit;
}
try {
    $st = database()->prepare('UPDATE proveedores SET activo=NOT activo,actualizado_en=CURRENT_TIMESTAMP WHERE id_proveedor=:id RETURNING activo');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    if (!is_array($row))
        throw new RuntimeException('Supplier not found');
    $active = booleanoPostgresMantenedor($row['activo']);
    guardarModalAdmin('success', $active ? 'Proveedor activado' : 'Proveedor desactivado', $active ? 'El proveedor vuelve a estar disponible.' : 'El proveedor ya no estará disponible para nuevas operaciones.');
} catch (Throwable $e) {
    $reference = registrarExcepcionAdmin('Supplier status error', $e);
    guardarModalAdmin('error', 'No fue posible cambiar el estado', 'Intenta nuevamente.', ['reference' => $reference]);
}
header('Location: ' . appUrl('admin/proveedores/ver.php?id_proveedor=' . $id), true, 303);
exit;
