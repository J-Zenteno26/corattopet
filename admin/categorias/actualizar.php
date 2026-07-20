<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-categoria.php';
require_once __DIR__ . '/includes/validaciones-categoria.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$id = idPositivoCategoria($_POST['id_categoria'] ?? null);
[$values, $errors] = validarCategoria($_POST);
$formUrl = appUrl('admin/categorias/editar.php?id=' . ($id ?? 0));

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMantenedor('categoria_editar_' . ($id ?? 0), $values, [], 'La solicitud no es válida.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
if ($id === null) {
    http_response_code(404);
    exit;
}
if ($errors !== []) {
    guardarEstadoMantenedor('categoria_editar_' . $id, $values, $errors);
    header('Location: ' . $formUrl, true, 303);
    exit;
}
try {
    $connection = database();
    if (obtenerCategoria($connection, $id) === null) {
        http_response_code(404);
        exit;
    }
    if (existeNombreCategoria($connection, $values['nombre'], $id)) {
        guardarEstadoMantenedor('categoria_editar_' . $id, $values, ['nombre' => 'Ya existe una categoría con este nombre.']);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }
    $slug = generarSlugUnicoMantenedor($connection, 'categorias', 'id_categoria', $values['nombre'], $id);
    $statement = $connection->prepare('UPDATE categorias SET nombre = :nombre, slug = :slug, descripcion = :descripcion, orden = :orden, maneja_fraccionamiento = :maneja_fraccionamiento, activo = :activo, actualizado_en = CURRENT_TIMESTAMP WHERE id_categoria = :id');
    $statement->execute(['nombre' => $values['nombre'], 'slug' => $slug, 'descripcion' => $values['descripcion'] === '' ? null : $values['descripcion'], 'orden' => (int) $values['orden'], 'maneja_fraccionamiento' => $values['maneja_fraccionamiento'], 'activo' => $values['activo'], 'id' => $id]);
    header('Location: ' . appUrl('admin/categorias/index.php?mensaje=actualizada'), true, 303);
    exit;
} catch (Throwable $exception) {
    $duplicate = $exception->getCode() === '23505';
    error_log('Category update error: ' . $exception->getMessage());
    guardarEstadoMantenedor('categoria_editar_' . $id, $values, $duplicate ? ['nombre' => 'Ya existe una categoría con este nombre.'] : [], $duplicate ? null : 'No fue posible actualizar la categoría.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
