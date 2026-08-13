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

$id = idPositivoCategoriaDespacho($_POST['id_categoria_despacho'] ?? null);
[$values, $errors] = validarCategoriaDespacho($_POST);
$formUrl = appUrl('admin/despachos/categorias/editar.php?id=' . ($id ?? 0));

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMantenedor(
        'categoria_despacho_editar_' . ($id ?? 0),
        $values,
        [],
        'La solicitud no es válida.'
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}

if ($id === null) {
    http_response_code(404);
    exit;
}

if ($errors !== []) {
    guardarEstadoMantenedor('categoria_despacho_editar_' . $id, $values, $errors);
    header('Location: ' . $formUrl, true, 303);
    exit;
}

try {
    $connection = database();

    if (obtenerCategoriaDespacho($connection, $id) === null) {
        http_response_code(404);
        exit;
    }

    if (existeNombreCategoriaDespacho($connection, $values['nombre'], $id)) {
        guardarEstadoMantenedor(
            'categoria_despacho_editar_' . $id,
            $values,
            ['nombre' => 'Ya existe una categoría de despacho con este nombre.']
        );
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $statement = $connection->prepare(
        'UPDATE categorias_despacho
        SET
            nombre = :nombre,
            peso_estimado_gramos = :peso_estimado_gramos,
            tamano = :tamano,
            activo = :activo,
            actualizado_en = CURRENT_TIMESTAMP
        WHERE id_categoria_despacho = :id'
    );
    $statement->bindValue(':nombre', $values['nombre']);
    $statement->bindValue(':peso_estimado_gramos', (int) $values['peso_estimado_gramos'], PDO::PARAM_INT);
    $statement->bindValue(':tamano', $values['tamano']);
    $statement->bindValue(':activo', $values['activo'], PDO::PARAM_BOOL);
    $statement->bindValue(':id', $id, PDO::PARAM_INT);
    $statement->execute();

    guardarModalAdmin(
        'success',
        'Categoría de despacho actualizada',
        'Los cambios fueron guardados correctamente.'
    );
    header('Location: ' . appUrl('admin/despachos/categorias/index.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    $duplicate = $exception->getCode() === '23505';
    $reference = registrarExcepcionAdmin('Shipping category update error', $exception);

    guardarEstadoMantenedor(
        'categoria_despacho_editar_' . $id,
        $values,
        $duplicate ? ['nombre' => 'Ya existe una categoría de despacho con este nombre.'] : [],
        $duplicate ? null : 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.',
        $duplicate ? null : $reference
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}
