<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-marca.php';

requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
$indexUrl = appUrl('admin/marcas/index.php');
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarModalAdmin('error', 'No fue posible cambiar el estado de la marca', 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $indexUrl, true, 303); exit;
}
$id = idPositivoMarca($_POST['id_marca'] ?? null);
if ($id === null) {
    guardarModalAdmin('error', 'No fue posible cambiar el estado de la marca', 'La marca indicada no es válida.');
    header('Location: ' . $indexUrl, true, 303); exit;
}
try {
    $statement = database()->prepare('UPDATE marcas SET activo = NOT activo, actualizado_en = CURRENT_TIMESTAMP WHERE id_marca = :id RETURNING id_marca, activo');
    $statement->bindValue(':id', $id, PDO::PARAM_INT);
    $statement->execute();
    $result = $statement->fetch();
    if (!is_array($result)) {
        guardarModalAdmin('error', 'No fue posible cambiar el estado de la marca', 'La marca indicada no existe.');
        header('Location: ' . $indexUrl, true, 303); exit;
    }
    $active = booleanoPostgresMantenedor($result['activo']);
    guardarModalAdmin('success', $active ? 'Marca activada' : 'Marca desactivada', $active ? 'La marca fue activada correctamente.' : 'La marca fue desactivada correctamente.');
    header('Location: ' . $indexUrl, true, 303); exit;
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Brand status error', $exception);
    guardarModalAdmin('error', 'No fue posible cambiar el estado de la marca', 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', ['reference' => $reference]);
    header('Location: ' . $indexUrl, true, 303); exit;
}
