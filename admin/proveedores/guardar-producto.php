<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-proveedores.php';
require_once __DIR__ . '/includes/validaciones-proveedores.php';
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
$back = appUrl('admin/proveedores/ver.php?id_proveedor=' . $id);
[$v, $errors] = validarAsociacionProveedor($_POST);
if ($errors !== []) {
    guardarModalAdmin('error', 'No fue posible asociar el producto', implode(' ', array_values($errors)));
    header('Location: ' . $back, true, 303);
    exit;
}
try {
    $st = database()->prepare('INSERT INTO proveedor_productos (id_proveedor,id_producto,sku_proveedor,precio_compra,activo) VALUES (:proveedor,:producto,:sku,:precio,:activo)');
    $st->execute(['proveedor' => $id, 'producto' => $v['id_producto'], 'sku' => $v['sku_proveedor'] ?: null, 'precio' => $v['precio_compra'] === '' ? null : $v['precio_compra'], 'activo' => $v['activo']]);
    guardarModalAdmin('success', 'Producto asociado', 'La asociación fue creada correctamente.');
} catch (Throwable $e) {
    $duplicate = (string) $e->getCode() === '23505';
    $reference = $duplicate ? null : registrarExcepcionAdmin('Supplier product creation error', $e);
    guardarModalAdmin('error', 'No fue posible asociar el producto', $duplicate ? 'El producto ya está asociado a este proveedor.' : 'Intenta nuevamente.', $reference ? ['reference' => $reference] : []);
}
header('Location: ' . $back, true, 303);
exit;
