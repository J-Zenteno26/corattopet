<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-proveedores.php';
require_once __DIR__ . '/includes/validaciones-proveedores.php';
requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$id = idProveedorValido($_POST['id_proveedor'] ?? null);
if ($id === null) {
    guardarModalAdmin('error', 'Proveedor inválido', 'El proveedor indicado no es válido.');
    header('Location: ' . appUrl('admin/proveedores/index.php'), true, 303);
    exit;
}
[$values, $errors] = validarProveedor($_POST);
$back = appUrl('admin/proveedores/editar.php?id_proveedor=' . $id);
if (!validateCsrfToken($_POST['csrf_token'] ?? null))
    $errors['csrf'] = 'La solicitud no es válida. Recarga la página.';
if ($errors !== []) {
    guardarEstadoMantenedor('proveedor_editar_' . $id, $values, $errors);
    header('Location: ' . $back, true, 303);
    exit;
}
try {
    $pdo = database();
    $fields = array_keys(valoresProveedorIniciales());
    $sets = array_map(fn($f) => $f . '=:' . $f, $fields);
    $st = $pdo->prepare('UPDATE proveedores SET ' . implode(',', $sets) . ',actualizado_en=CURRENT_TIMESTAMP WHERE id_proveedor=:id');
    foreach ($fields as $field) {
        $value = $values[$field];
        if ($field === 'activo')
            $st->bindValue(':' . $field, $value, PDO::PARAM_BOOL);
        else
            $st->bindValue(':' . $field, $value === '' ? null : $value);
    }
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    if ($st->rowCount() === 0)
        throw new RuntimeException('Supplier not found');
    guardarModalAdmin('success', 'Proveedor actualizado', 'Los datos fueron actualizados correctamente.');
    header('Location: ' . appUrl('admin/proveedores/ver.php?id_proveedor=' . $id), true, 303);
    exit;
} catch (Throwable $e) {
    $reference = registrarExcepcionAdmin('Supplier update error', $e);
    guardarEstadoMantenedor('proveedor_editar_' . $id, $values, [], 'No fue posible actualizar el proveedor.', $reference);
    header('Location: ' . $back, true, 303);
    exit;
}
