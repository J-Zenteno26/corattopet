<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-marca.php';
require_once __DIR__ . '/includes/validaciones-marca.php';

requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }

[$values, $errors] = validarMarca($_POST);
$values['_modo'] = 'create';
$indexUrl = appUrl('admin/marcas/index.php');
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMantenedor('marca_form', $values, [], 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $indexUrl, true, 303); exit;
}
if ($errors !== []) {
    guardarEstadoMantenedor('marca_form', $values, $errors);
    header('Location: ' . $indexUrl, true, 303); exit;
}

try {
    $connection = database();
    if (existeNombreMarca($connection, $values['nombre'])) {
        guardarEstadoMantenedor('marca_form', $values, ['nombre' => 'Ya existe una marca con este nombre.']);
        header('Location: ' . $indexUrl, true, 303); exit;
    }
    $slug = generarSlugUnicoMantenedor($connection, 'marcas', 'id_marca', $values['nombre']);
    $statement = $connection->prepare('INSERT INTO marcas (nombre, slug, activo) VALUES (:nombre, :slug, :activo)');
    $statement->bindValue(':nombre', $values['nombre']);
    $statement->bindValue(':slug', $slug);
    $statement->bindValue(':activo', $values['activo'], PDO::PARAM_BOOL);
    $statement->execute();
    guardarModalAdmin('success', 'Marca creada', 'La marca fue registrada correctamente.');
    header('Location: ' . $indexUrl, true, 303); exit;
} catch (Throwable $exception) {
    $duplicate = (string) $exception->getCode() === '23505';
    $reference = registrarExcepcionAdmin('Brand creation error', $exception);
    guardarEstadoMantenedor('marca_form', $values, $duplicate ? ['nombre' => 'Ya existe una marca con este nombre.'] : [], $duplicate ? null : 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', $duplicate ? null : $reference);
    header('Location: ' . $indexUrl, true, 303); exit;
}
