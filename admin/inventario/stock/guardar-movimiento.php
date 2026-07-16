<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/funciones-stock.php';
require_once __DIR__ . '/includes/validaciones-stock.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$productId = idPositivoStock($_POST['id_producto'] ?? null);
if ($productId === null) {
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=no_encontrado'), true, 303);
    exit;
}

$formUrl = appUrl('admin/inventario/stock/index.php?id=' . $productId);
[$values, $errors] = validarDatosMovimientoStock($_POST);

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMovimientoStock($productId, $values, [], 'La solicitud no es válida. Recarga el formulario e intenta nuevamente.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}

if ($errors !== []) {
    guardarEstadoMovimientoStock($productId, $values, $errors);
    header('Location: ' . $formUrl, true, 303);
    exit;
}

$connection = null;

try {
    $connection = database();
    $connection->beginTransaction();

    $stockStatement = $connection->prepare(
        'SELECT cantidad_actual, cantidad_reservada
        FROM stock
        WHERE id_producto = :id_producto
        FOR UPDATE'
    );
    $stockStatement->execute(['id_producto' => $productId]);
    $stock = $stockStatement->fetch();

    if (!is_array($stock)) {
        $connection->rollBack();
        header('Location: ' . appUrl('admin/inventario/index.php?mensaje=no_encontrado'), true, 303);
        exit;
    }

    $currentStock = (int) $stock['cantidad_actual'];
    $reservedStock = (int) $stock['cantidad_reservada'];
    [$movementQuantity, $resultingStock] = calcularMovimientoStock(
        $values['tipo_movimiento'],
        (int) $values['cantidad'],
        $currentStock
    );

    if ($resultingStock < 0 || $resultingStock < $reservedStock) {
        $connection->rollBack();
        $errors['cantidad'] = 'No existe stock suficiente para realizar esta salida.';
        guardarEstadoMovimientoStock($productId, $values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    if ($movementQuantity === 0) {
        $connection->rollBack();
        $errors['cantidad'] = 'El ajuste debe ser diferente del stock actual.';
        guardarEstadoMovimientoStock($productId, $values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $persistedType = tipoPersistidoMovimientoStock(
        $values['tipo_movimiento'],
        $values['motivo'],
        $movementQuantity
    );

    $updateStatement = $connection->prepare(
        'UPDATE stock
        SET cantidad_actual = :cantidad_actual,
            actualizado_en = CURRENT_TIMESTAMP
        WHERE id_producto = :id_producto'
    );
    $updateStatement->execute([
        'cantidad_actual' => $resultingStock,
        'id_producto' => $productId,
    ]);

    $movementStatement = $connection->prepare(
        "INSERT INTO movimientos_stock (
            id_producto, id_usuario, tipo_movimiento, cantidad,
            stock_anterior, stock_final, origen, motivo, referencia
        ) VALUES (
            :id_producto, :id_usuario, :tipo_movimiento, :cantidad,
            :stock_anterior, :stock_final, 'manual', :motivo, :referencia
        )"
    );
    $movementStatement->execute([
        'id_producto' => $productId,
        'id_usuario' => (int) $_SESSION['id_usuario'],
        'tipo_movimiento' => $persistedType,
        'cantidad' => $movementQuantity,
        'stock_anterior' => $currentStock,
        'stock_final' => $resultingStock,
        'motivo' => $values['motivo'],
        'referencia' => $values['observacion'] === '' ? null : $values['observacion'],
    ]);

    $connection->commit();
    header('Location: ' . $formUrl . '&mensaje=registrado', true, 303);
    exit;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('Stock movement error: ' . $exception->getMessage());
    guardarEstadoMovimientoStock($productId, $values, [], 'No fue posible registrar el movimiento de stock.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}
