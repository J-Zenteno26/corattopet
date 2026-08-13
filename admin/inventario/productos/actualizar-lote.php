<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/consultas-lotes-producto.php';

requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
$lotId = filter_var($_POST['id_lote'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($lotId === false) {
    guardarModalAdmin('error', 'Lote inválido', 'El lote indicado no es válido.');
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
    exit;
}
$formUrl = appUrl('admin/inventario/productos/editar-lote.php?id_lote=' . $lotId);
$stateKey = 'lote_editar_' . $lotId;
$values = [
    'codigo_lote' => trim((string) ($_POST['codigo_lote'] ?? '')),
    'fecha_elaboracion' => trim((string) ($_POST['fecha_elaboracion'] ?? '')),
    'fecha_vencimiento' => trim((string) ($_POST['fecha_vencimiento'] ?? '')),
    'id_proveedor' => trim((string) ($_POST['id_proveedor'] ?? '')),
];
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION[$stateKey] = ['values' => $values, 'general_error' => 'La solicitud no es válida. Recarga la página e intenta nuevamente.'];
    header('Location: ' . $formUrl, true, 303);
    exit;
}
$errors = [];
if ($values['codigo_lote'] === '')
    $errors['codigo_lote'] = 'El código de lote es obligatorio.';
elseif (mb_strlen($values['codigo_lote']) > 80)
    $errors['codigo_lote'] = 'El código no puede superar 80 caracteres.';
$validDate = static function (string $date): bool {
    if ($date === '')
        return false;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $dateErrors = DateTimeImmutable::getLastErrors();
    return $parsed !== false
        && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
        && $parsed->format('Y-m-d') === $date;
};
if (!$validDate($values['fecha_vencimiento']))
    $errors['fecha_vencimiento'] = 'Ingresa una fecha de vencimiento válida.';
if ($values['fecha_elaboracion'] !== '' && !$validDate($values['fecha_elaboracion']))
    $errors['fecha_elaboracion'] = 'Ingresa una fecha de elaboración válida.';
if (!isset($errors['fecha_elaboracion'], $errors['fecha_vencimiento']) && $values['fecha_elaboracion'] !== '' && $values['fecha_elaboracion'] > $values['fecha_vencimiento'])
    $errors['fecha_elaboracion'] = 'La elaboración no puede ser posterior al vencimiento.';

try {
    $connection = database();
    $lot = obtenerLoteEditable($connection, $lotId);
    if ($lot === null) {
        guardarModalAdmin('error', 'Lote no encontrado', 'El lote indicado no existe.');
        header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
        exit;
    }
    $supplierId = null;
    if ($values['id_proveedor'] !== '') {
        $supplierId = filter_var($values['id_proveedor'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($supplierId === false)
            $errors['id_proveedor'] = 'Selecciona un proveedor válido.';
        else {
            $supplierCheck = $connection->prepare('SELECT 1 FROM proveedores WHERE id_proveedor=:id');
            $supplierCheck->execute(['id' => $supplierId]);
            if (!$supplierCheck->fetchColumn())
                $errors['id_proveedor'] = 'El proveedor seleccionado no existe.';
        }
    }
    if ($errors !== []) {
        $_SESSION[$stateKey] = ['values' => $values, 'errors' => $errors];
        header('Location: ' . $formUrl, true, 303);
        exit;
    }
    $duplicate = existeCodigoLoteProducto($connection, (int) $lot['id_producto'], $values['codigo_lote'], $lotId);
    $statement = $connection->prepare('UPDATE stock_lotes SET codigo_lote=:codigo, fecha_elaboracion=:elaboracion,
        fecha_vencimiento=:vencimiento, id_proveedor=:proveedor, actualizado_en=CURRENT_TIMESTAMP WHERE id_lote=:id');
    $statement->execute([
        'codigo' => $values['codigo_lote'],
        'elaboracion' => $values['fecha_elaboracion'] ?: null,
        'vencimiento' => $values['fecha_vencimiento'],
        'proveedor' => $supplierId ?: null,
        'id' => $lotId
    ]);
    guardarModalAdmin($duplicate ? 'warning' : 'success', 'Lote actualizado', $duplicate
        ? 'Los metadatos fueron guardados. El código también existe en otro lote del mismo producto.'
        : 'Los metadatos del lote fueron guardados correctamente.');
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Lot metadata update error: ' . $exception->getMessage());
    $_SESSION[$stateKey] = ['values' => $values, 'errors' => $errors, 'general_error' => 'No fue posible actualizar el lote.'];
    header('Location: ' . $formUrl, true, 303);
    exit;
}
