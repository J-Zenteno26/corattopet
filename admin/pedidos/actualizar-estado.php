<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-pedidos.php';
require_once __DIR__ . '/includes/validaciones-pedidos.php';
require_once __DIR__ . '/includes/consultas-pedidos.php';

requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }

[$values, $errors] = validarActualizacionPedido($_POST);
$orderId = $values['id_pedido'];
$fallbackUrl = appUrl('admin/pedidos/index.php');
$detailUrl = $orderId === null ? $fallbackUrl : appUrl('admin/pedidos/ver.php?id_pedido=' . $orderId);

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    if ($orderId !== null) { guardarEstadoMantenedor('pedido_estado_' . $orderId, $values, [], 'La solicitud no es válida. Recarga la página e intenta nuevamente.'); }
    else { guardarModalAdmin('error', 'No fue posible actualizar el pedido', 'La solicitud no es válida.'); }
    header('Location: ' . $detailUrl, true, 303); exit;
}
if ($errors !== []) {
    if ($orderId !== null) { guardarEstadoMantenedor('pedido_estado_' . $orderId, $values, $errors); }
    else { guardarModalAdmin('error', 'No fue posible actualizar el pedido', 'El pedido indicado no es válido.'); }
    header('Location: ' . $detailUrl, true, 303); exit;
}

$connection = null;
try {
    $connection = database();
    $connection->beginTransaction();
    $current = obtenerPedido($connection, $orderId, true);
    if ($current === null) {
        $connection->rollBack();
        guardarModalAdmin('error', 'No fue posible actualizar el pedido', 'El pedido indicado no existe.');
        header('Location: ' . $fallbackUrl, true, 303); exit;
    }
    $update = $connection->prepare('UPDATE pedidos SET estado = :estado, estado_pago = :estado_pago,
        observaciones_internas = :observaciones_internas, actualizado_en = CURRENT_TIMESTAMP WHERE id_pedido = :id_pedido');
    $update->bindValue(':estado', $values['estado'], PDO::PARAM_STR);
    $update->bindValue(':estado_pago', $values['estado_pago'], PDO::PARAM_STR);
    $update->bindValue(':observaciones_internas', $values['observaciones_internas'], PDO::PARAM_STR);
    $update->bindValue(':id_pedido', $orderId, PDO::PARAM_INT);
    $update->execute();

    $stateChanged = (string) $current['estado'] !== $values['estado'];
    $paymentChanged = (string) $current['estado_pago'] !== $values['estado_pago'];
    if ($stateChanged || $paymentChanged) {
        $history = $connection->prepare('INSERT INTO pedido_historial_estados
            (id_pedido, estado_anterior, estado_nuevo, estado_pago_anterior, estado_pago_nuevo, observacion, id_usuario)
            VALUES (:id_pedido, :estado_anterior, :estado_nuevo, :pago_anterior, :pago_nuevo, :observacion, :id_usuario)');
        $history->bindValue(':id_pedido', $orderId, PDO::PARAM_INT);
        $history->bindValue(':estado_anterior', $stateChanged ? (string) $current['estado'] : null, $stateChanged ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $history->bindValue(':estado_nuevo', $stateChanged ? $values['estado'] : null, $stateChanged ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $history->bindValue(':pago_anterior', $paymentChanged ? (string) $current['estado_pago'] : null, $paymentChanged ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $history->bindValue(':pago_nuevo', $paymentChanged ? $values['estado_pago'] : null, $paymentChanged ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $history->bindValue(':observacion', $values['observaciones_internas'] === '' ? null : $values['observaciones_internas'], $values['observaciones_internas'] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $history->bindValue(':id_usuario', (int) $_SESSION['id_usuario'], PDO::PARAM_INT);
        $history->execute();
    }
    $connection->commit();
    guardarModalAdmin('success', 'Pedido actualizado', 'El estado del pedido fue actualizado correctamente.');
    header('Location: ' . $detailUrl, true, 303); exit;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) { $connection->rollBack(); }
    $reference = registrarExcepcionAdmin('Order status update error', $exception);
    guardarEstadoMantenedor('pedido_estado_' . $orderId, $values, [], 'No se pudo completar la acción.', $reference);
    header('Location: ' . $detailUrl, true, 303); exit;
}
