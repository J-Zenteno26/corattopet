<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-clientes.php';
require_once __DIR__ . '/includes/validaciones-clientes.php';
require_once __DIR__ . '/includes/consultas-clientes.php';

requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

[$values, $errors] = validarCliente($_POST);
$createUrl = appUrl('admin/clientes/crear.php');
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoNuevoCliente($values, [], 'La solicitud no es válida. Recarga la página.');
    header('Location: ' . $createUrl, true, 303);
    exit;
}
if ($errors !== []) {
    guardarEstadoNuevoCliente($values, $errors);
    header('Location: ' . $createUrl, true, 303);
    exit;
}

try {
    $pdo = database();
    if ($values['email'] !== '' && existeEmailCliente($pdo, $values['email'])) {
        guardarEstadoNuevoCliente($values, ['email' => 'Este email ya pertenece a otro cliente.']);
        header('Location: ' . $createUrl, true, 303);
        exit;
    }
    $id = insertarCliente($pdo, $values);
    guardarModalAdmin('success', 'Cliente creado', 'El cliente fue registrado correctamente.');
    header('Location: ' . appUrl('admin/clientes/ver.php?id_cliente=' . $id), true, 303);
    exit;
} catch (Throwable $e) {
    $ref = registrarExcepcionAdmin('Customer create error', $e);
    guardarEstadoNuevoCliente($values, [], 'No se pudo completar la acción.', $ref);
    header('Location: ' . $createUrl, true, 303);
    exit;
}
