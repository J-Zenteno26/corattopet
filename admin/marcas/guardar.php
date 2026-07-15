<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-marca.php';
require_once __DIR__ . '/includes/validaciones-marca.php';
requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
[$values, $errors] = validarMarca($_POST);
$formUrl = appUrl('admin/marcas/crear.php');
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMantenedor('marca_crear', $values, [], 'La solicitud no es válida.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
if ($errors !== []) {
    guardarEstadoMantenedor('marca_crear', $values, $errors);
    header('Location: ' . $formUrl, true, 303);
    exit;
}
try {
    $connection = database();
    if (existeNombreMarca($connection, $values['nombre'])) {
        guardarEstadoMantenedor('marca_crear', $values, ['nombre' => 'Ya existe una marca con este nombre.']);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }
    $slug = generarSlugUnicoMantenedor($connection, 'marcas', 'id_marca', $values['nombre']);
    $statement = $connection->prepare('INSERT INTO marcas (nombre, slug, activo) VALUES (:nombre, :slug, :activo)');
    $statement->execute(['nombre' => $values['nombre'], 'slug' => $slug, 'activo' => $values['activo']]);
    header('Location: ' . appUrl('admin/marcas/index.php?mensaje=creada'), true, 303);
    exit;
} catch (Throwable $exception) {
    $duplicate = $exception->getCode() === '23505';
    error_log('Brand creation error: ' . $exception->getMessage());
    guardarEstadoMantenedor('marca_crear', $values, $duplicate ? ['nombre' => 'Ya existe una marca con este nombre.'] : [], $duplicate ? null : 'No fue posible guardar la marca.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
