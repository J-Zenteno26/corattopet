<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-categoria.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarModalAdmin('error', 'No fue posible cambiar el estado de la categoría', 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . appUrl('admin/categorias/index.php'), true, 303);
    exit;
}
$id = idPositivoCategoria($_POST['id_categoria'] ?? null);
if ($id === null) {
    guardarModalAdmin('error', 'No fue posible cambiar el estado de la categoría', 'La categoría indicada no es válida.');
    header('Location: ' . appUrl('admin/categorias/index.php'), true, 303);
    exit;
}
try {
    $statement = database()->prepare('UPDATE categorias SET activo = NOT activo, actualizado_en = CURRENT_TIMESTAMP WHERE id_categoria = :id RETURNING id_categoria, activo');
    $statement->execute(['id' => $id]);
    $result = $statement->fetch();
    if (!is_array($result)) {
        guardarModalAdmin('error', 'No fue posible cambiar el estado de la categoría', 'La categoría indicada no existe.');
        header('Location: ' . appUrl('admin/categorias/index.php'), true, 303);
        exit;
    }
    $active = booleanoPostgresMantenedor($result['activo']);
    guardarModalAdmin(
        'success',
        $active ? 'Categoría activada' : 'Categoría desactivada',
        $active ? 'La categoría fue activada correctamente.' : 'La categoría fue desactivada correctamente.'
    );
    header('Location: ' . appUrl('admin/categorias/index.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Category status error: ' . $exception->getMessage());
    guardarModalAdmin('error', 'No fue posible cambiar el estado de la categoría', 'Intenta nuevamente. Si el problema continúa, contacta al administrador.');
    header('Location: ' . appUrl('admin/categorias/index.php'), true, 303);
    exit;
}
