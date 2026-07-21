<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-categoria.php';
require_once __DIR__ . '/includes/validaciones-categoria.php';

requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
[$values, $errors] = validarCategoria($_POST);
$formUrl = appUrl('admin/categorias/crear.php');
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMantenedor('categoria_crear', $values, [], 'La solicitud no es válida. Intenta nuevamente.');
    header('Location: ' . $formUrl, true, 303); exit;
}
if ($errors !== []) { guardarEstadoMantenedor('categoria_crear', $values, $errors); header('Location: ' . $formUrl, true, 303); exit; }

try {
    $connection = database();
    if (existeNombreCategoria($connection, $values['nombre'])) {
        guardarEstadoMantenedor('categoria_crear', $values, ['nombre' => 'Ya existe una categoría con este nombre.']);
        header('Location: ' . $formUrl, true, 303); exit;
    }
    $slug = generarSlugUnicoMantenedor($connection, 'categorias', 'id_categoria', $values['nombre']);
    $statement = $connection->prepare('INSERT INTO categorias (nombre, slug, descripcion, orden, maneja_fraccionamiento, activo) VALUES (:nombre, :slug, :descripcion, :orden, :maneja_fraccionamiento, :activo)');
    $statement->bindValue(':nombre', $values['nombre']);
    $statement->bindValue(':slug', $slug);
    $statement->bindValue(':descripcion', $values['descripcion'] === '' ? null : $values['descripcion']);
    $statement->bindValue(':orden', (int) $values['orden'], PDO::PARAM_INT);
    $statement->bindValue(':maneja_fraccionamiento', $values['maneja_fraccionamiento'], PDO::PARAM_BOOL);
    $statement->bindValue(':activo', $values['activo'], PDO::PARAM_BOOL);
    $statement->execute();
    guardarModalAdmin('success', 'Categoría creada', 'La categoría fue registrada correctamente.');
    header('Location: ' . appUrl('admin/categorias/index.php'), true, 303); exit;
} catch (Throwable $exception) {
    $duplicate = $exception->getCode() === '23505';
    $reference = registrarExcepcionAdmin('Category creation error', $exception);
    guardarEstadoMantenedor('categoria_crear', $values, $duplicate ? ['nombre' => 'Ya existe una categoría con este nombre.'] : [], $duplicate ? null : 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', $duplicate ? null : $reference);
    header('Location: ' . $formUrl, true, 303); exit;
}
