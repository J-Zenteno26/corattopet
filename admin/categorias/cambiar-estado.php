<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-categoria.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}
$id = idPositivoCategoria($_POST['id_categoria'] ?? null);
if ($id === null) {
    http_response_code(404);
    exit;
}
try {
    $statement = database()->prepare('UPDATE categorias SET activo = NOT activo, actualizado_en = CURRENT_TIMESTAMP WHERE id_categoria = :id RETURNING id_categoria, activo');
    $statement->execute(['id' => $id]);
    $result = $statement->fetch();
    if (!is_array($result)) {
        http_response_code(404);
        exit;
    }
    header('Location: ' . appUrl('admin/categorias/index.php?mensaje=' . (booleanoPostgresMantenedor($result['activo']) ? 'activada' : 'desactivada')), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Category status error: ' . $exception->getMessage());
    header('Location: ' . appUrl('admin/categorias/index.php?mensaje=error'), true, 303);
    exit;
}
