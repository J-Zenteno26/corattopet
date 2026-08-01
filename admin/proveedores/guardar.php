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
[$values, $errors] = validarProveedor($_POST);
$back = appUrl('admin/proveedores/crear.php');
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $errors['csrf'] = 'La solicitud no es válida. Recarga la página.';
}
if ($errors !== []) {
    guardarEstadoMantenedor('proveedor_crear', $values, $errors);
    header('Location: ' . $back, true, 303);
    exit;
}
try {
    $pdo = database();
    $fields = array_keys(valoresProveedorIniciales());
    $sql = 'INSERT INTO proveedores (' . implode(',', $fields) . ') VALUES (:' . implode(',:', $fields) . ') RETURNING id_proveedor';
    $st = $pdo->prepare($sql);
    foreach ($fields as $field) {
        $value = $values[$field];
        if ($field === 'activo')
            $st->bindValue(':' . $field, $value, PDO::PARAM_BOOL);
        else
            $st->bindValue(':' . $field, $value === '' ? null : $value);
    }
    $st->execute();
    $id = (int) $st->fetchColumn();
    guardarModalAdmin('success', 'Proveedor creado', 'El proveedor fue registrado correctamente.');
    header('Location: ' . appUrl('admin/proveedores/ver.php?id_proveedor=' . $id), true, 303);
    exit;
} catch (Throwable $e) {
    $reference = registrarExcepcionAdmin('Supplier creation error', $e);
    guardarEstadoMantenedor('proveedor_crear', $values, [], 'No fue posible guardar el proveedor.', $reference);
    header('Location: ' . $back, true, 303);
    exit;
}
