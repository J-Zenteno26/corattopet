<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-categoria-despacho.php';

requireAuthentication();

$indexUrl = appUrl('admin/despachos/categorias/index.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarModalAdmin(
        'error',
        'No fue posible cambiar el estado',
        'La solicitud no es válida. Recarga la página e intenta nuevamente.'
    );
    header('Location: ' . $indexUrl, true, 303);
    exit;
}

$id = idPositivoCategoriaDespacho($_POST['id_categoria_despacho'] ?? null);

if ($id === null) {
    guardarModalAdmin(
        'error',
        'No fue posible cambiar el estado',
        'La categoría indicada no es válida.'
    );
    header('Location: ' . $indexUrl, true, 303);
    exit;
}

try {
    $statement = database()->prepare(
        'UPDATE categorias_despacho
        SET
            activo = NOT activo,
            actualizado_en = CURRENT_TIMESTAMP
        WHERE id_categoria_despacho = :id
        RETURNING id_categoria_despacho, activo'
    );
    $statement->execute(['id' => $id]);
    $result = $statement->fetch();

    if (!is_array($result)) {
        guardarModalAdmin(
            'error',
            'No fue posible cambiar el estado',
            'La categoría indicada no existe.'
        );
        header('Location: ' . $indexUrl, true, 303);
        exit;
    }

    $active = booleanoPostgresMantenedor($result['activo']);

    guardarModalAdmin(
        'success',
        $active ? 'Categoría activada' : 'Categoría desactivada',
        $active
            ? 'La categoría de despacho fue activada correctamente.'
            : 'La categoría de despacho fue desactivada correctamente.'
    );
    header('Location: ' . $indexUrl, true, 303);
    exit;
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Shipping category status error', $exception);

    guardarModalAdmin(
        'error',
        'No fue posible cambiar el estado',
        'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.',
        ['reference' => $reference]
    );
    header('Location: ' . $indexUrl, true, 303);
    exit;
}
