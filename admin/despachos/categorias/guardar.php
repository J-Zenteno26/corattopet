<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-categoria-despacho.php';
require_once __DIR__ . '/includes/validaciones-categoria-despacho.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

[$values, $errors] = validarCategoriaDespacho($_POST);
$formUrl = appUrl('admin/despachos/categorias/crear.php');

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMantenedor(
        'categoria_despacho_crear',
        $values,
        [],
        'La solicitud no es válida. Intenta nuevamente.'
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}

if ($errors !== []) {
    guardarEstadoMantenedor('categoria_despacho_crear', $values, $errors);
    header('Location: ' . $formUrl, true, 303);
    exit;
}

try {
    $connection = database();

    if (existeNombreCategoriaDespacho($connection, $values['nombre'])) {
        guardarEstadoMantenedor(
            'categoria_despacho_crear',
            $values,
            ['nombre' => 'Ya existe una categoría de despacho con este nombre.']
        );
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $statement = $connection->prepare(
        'INSERT INTO categorias_despacho (
            nombre,
            peso_estimado_gramos,
            tamano,
            activo
        ) VALUES (
            :nombre,
            :peso_estimado_gramos,
            :tamano,
            :activo
        )'
    );
    $statement->bindValue(':nombre', $values['nombre']);
    $statement->bindValue(':peso_estimado_gramos', (int) $values['peso_estimado_gramos'], PDO::PARAM_INT);
    $statement->bindValue(':tamano', $values['tamano']);
    $statement->bindValue(':activo', $values['activo'], PDO::PARAM_BOOL);
    $statement->execute();

    guardarModalAdmin(
        'success',
        'Categoría de despacho creada',
        'La categoría fue registrada correctamente.'
    );
    header('Location: ' . appUrl('admin/despachos/categorias/index.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    $duplicate = $exception->getCode() === '23505';
    $reference = registrarExcepcionAdmin('Shipping category creation error', $exception);

    guardarEstadoMantenedor(
        'categoria_despacho_crear',
        $values,
        $duplicate ? ['nombre' => 'Ya existe una categoría de despacho con este nombre.'] : [],
        $duplicate ? null : 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.',
        $duplicate ? null : $reference
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}
