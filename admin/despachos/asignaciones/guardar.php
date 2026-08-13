<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 3);

require_once $projectRoot . '/config/app.php';
require_once $projectRoot . '/shared/seguridad.php';
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-asignaciones-despacho.php';

requireAuthentication();

function redirigirAsignacionesDespacho(string $returnQuery = ''): never
{
    $url = appUrl('admin/despachos/asignaciones/index.php');
    $returnQuery = ltrim(trim($returnQuery), '?');

    if ($returnQuery !== '') {
        parse_str($returnQuery, $parameters);

        if (is_array($parameters)) {
            unset($parameters['resultado']);
            $safeQuery = http_build_query($parameters);
            $url .= $safeQuery === '' ? '' : '?' . $safeQuery;
        }
    }

    header('Location: ' . $url, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}

$returnQuery = is_scalar($_POST['return_query'] ?? null)
    ? (string) $_POST['return_query']
    : '';

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarModalAdmin(
        'error',
        'Solicitud expirada',
        'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.'
    );
    redirigirAsignacionesDespacho($returnQuery);
}

$shippingCategoryId = normalizarIdAsignacionDespacho(
    $_POST['id_categoria_despacho'] ?? null
);
$productIds = $_POST['productos'] ?? [];

if (!is_array($productIds)) {
    $productIds = [];
}

$productIds = array_values(array_unique(array_filter(
    array_map(
        static fn(mixed $value): int => (int) $value,
        $productIds
    ),
    static fn(int $id): bool => $id > 0
)));

if ($shippingCategoryId === null || $productIds === []) {
    guardarModalAdmin(
        'warning',
        'Selección incompleta',
        'Selecciona al menos un producto y una categoría de despacho.'
    );
    redirigirAsignacionesDespacho($returnQuery);
}

try {
    $connection = database();
    $connection->beginTransaction();

    $categoryStatement = $connection->prepare(
        'SELECT id_categoria_despacho
         FROM categorias_despacho
         WHERE id_categoria_despacho = :id
           AND activo = TRUE'
    );
    $categoryStatement->execute(['id' => $shippingCategoryId]);

    if ($categoryStatement->fetchColumn() === false) {
        $connection->rollBack();
        guardarModalAdmin(
            'error',
            'Categoría no disponible',
            'La categoría seleccionada ya no se encuentra activa.'
        );
        redirigirAsignacionesDespacho($returnQuery);
    }

    $placeholders = [];
    foreach ($productIds as $index => $productId) {
        $placeholders[] = ':product_' . $index;
    }

    $eligibleStatement = $connection->prepare(
        "SELECT p.id_producto
         FROM productos p
         WHERE p.estado = 'activo'
           AND p.id_producto IN (" . implode(', ', $placeholders) . ")
           AND NOT EXISTS (
                SELECT 1
                FROM producto_presentaciones pp
                WHERE pp.id_producto = p.id_producto
                  AND pp.activo = TRUE
                  AND pp.cantidad_gramos IS NOT NULL
                  AND pp.cantidad_gramos > 0
           )"
    );

    foreach ($productIds as $index => $productId) {
        $eligibleStatement->bindValue(':product_' . $index, $productId, PDO::PARAM_INT);
    }
    $eligibleStatement->execute();

    $eligibleIds = array_map(
        'intval',
        $eligibleStatement->fetchAll(PDO::FETCH_COLUMN)
    );

    if ($eligibleIds === []) {
        $connection->rollBack();
        guardarModalAdmin(
            'warning',
            'Sin productos asignables',
            'Los productos seleccionados calculan su peso mediante presentaciones o ya no están activos.'
        );
        redirigirAsignacionesDespacho($returnQuery);
    }

    $upsertStatement = $connection->prepare(
        'INSERT INTO productos_categorias_despacho (
            id_producto,
            id_categoria_despacho,
            asignado_en
         ) VALUES (
            :id_producto,
            :id_categoria_despacho,
            NOW()
         )
         ON CONFLICT (id_producto)
         DO UPDATE SET
            id_categoria_despacho = EXCLUDED.id_categoria_despacho,
            asignado_en = NOW()'
    );

    foreach ($eligibleIds as $productId) {
        $upsertStatement->execute([
            'id_producto' => $productId,
            'id_categoria_despacho' => $shippingCategoryId,
        ]);
    }

    $connection->commit();

    $assignedCount = count($eligibleIds);
    $skippedCount = count($productIds) - $assignedCount;
    $message = $assignedCount === 1
        ? 'Se asignó correctamente 1 producto.'
        : 'Se asignaron correctamente ' . $assignedCount . ' productos.';

    if ($skippedCount > 0) {
        $message .= ' Se omitieron ' . $skippedCount . ' porque usan peso por presentación o no están activos.';
    }

    guardarModalAdmin('success', 'Asignación completada', $message);
    redirigirAsignacionesDespacho($returnQuery);
} catch (Throwable $exception) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('Shipping product assignment error: ' . $exception->getMessage());
    guardarModalAdmin(
        'error',
        'No pudimos guardar la asignación',
        'Inténtalo nuevamente. Si el problema continúa, revisa el registro del sistema.'
    );
    redirigirAsignacionesDespacho($returnQuery);
}
