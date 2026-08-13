<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-lotes.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
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
    guardarModalAdmin(
        'error',
        'No fue posible registrar el movimiento',
        'El producto indicado no es válido.'
    );
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
    exit;
}

$formUrl = appUrl('admin/inventario/stock/index.php?id=' . $productId);
$values = array_merge(
    valoresInicialesMovimientoStock(),
    array_map(
        static fn (mixed $value): mixed => is_scalar($value) ? trim((string) $value) : $value,
        array_intersect_key($_POST, valoresInicialesMovimientoStock())
    )
);
$values['lotes'] = is_array($_POST['lotes'] ?? null) ? $_POST['lotes'] : [];
$values['lotes_existentes'] = is_array($_POST['lotes_existentes'] ?? null) ? $_POST['lotes_existentes'] : [];
$errors = [];

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMovimientoStock(
        $productId,
        $values,
        [],
        'La solicitud no es válida. Recarga el formulario e intenta nuevamente.'
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}

$connection = null;

try {
    $connection = database();
    $connection->beginTransaction();

    $stockStatement = $connection->prepare(
        'SELECT
            s.cantidad_actual,
            s.cantidad_reservada,
            c.slug AS categoria_slug,
            p.detalles_opcionales
        FROM stock s
        INNER JOIN productos p ON p.id_producto = s.id_producto
        INNER JOIN categorias c ON c.id_categoria = p.id_categoria
        WHERE s.id_producto = :id_producto
        FOR UPDATE OF s'
    );
    $stockStatement->execute(['id_producto' => $productId]);
    $stock = $stockStatement->fetch();

    if (!is_array($stock)) {
        $connection->rollBack();
        guardarModalAdmin(
            'error',
            'No fue posible registrar el movimiento',
            'El producto indicado no existe o no tiene un registro de stock.'
        );
        header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
        exit;
    }

    $fractionable = esProductoFraccionable($stock);
    [$values, $errors] = validarDatosMovimientoStock($_POST, $fractionable);

    if ($errors !== []) {
        $connection->rollBack();
        guardarEstadoMovimientoStock($productId, $values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $type = (string) $values['tipo_movimiento'];
    $reasonLabel = (string) ($values['_motivo_label'] ?? 'Movimiento manual');
    $observation = trim((string) $values['observacion']);
    $reference = $observation !== '' ? $observation : null;
    $userId = (int) $_SESSION['id_usuario'];
    $reservedStock = max(0, (int) $stock['cantidad_reservada']);

    if ($fractionable) {
        $presentations = presentacionesActivasProducto($connection, $productId);

        $lotCountStatement = $connection->prepare(
            'SELECT COUNT(*) FROM stock_lotes WHERE id_producto = :id AND activo = TRUE'
        );
        $lotCountStatement->execute(['id' => $productId]);
        $activeLotCount = (int) $lotCountStatement->fetchColumn();
        $legacyStockWithoutLots = (int) $stock['cantidad_actual'] > 0 && $activeLotCount === 0;

        if ($type === 'entrada' && $legacyStockWithoutLots) {
            $connection->rollBack();
            guardarEstadoMovimientoStock(
                $productId,
                $values,
                ['tipo_movimiento' => 'Este producto tiene stock histórico sin lotes. Usa Regularización para identificar el stock físico antes de registrar nuevas entradas.']
            );
            header('Location: ' . $formUrl, true, 303);
            exit;
        }

        if ($type === 'entrada') {
            $supplierId = validarProveedorMovimientoStock($connection, $_POST['id_proveedor'] ?? null);
            $lotes = normalizarLotesFormulario($_POST['lotes'] ?? []);
            $lotErrors = validarLotesStock($lotes);

            if ($lotErrors !== []) {
                $connection->rollBack();
                $values['lotes'] = $lotes;
                $values['id_proveedor'] = $supplierId === null ? '' : (string) $supplierId;
                guardarEstadoMovimientoStock($productId, $values, $lotErrors);
                header('Location: ' . $formUrl, true, 303);
                exit;
            }

            if ($supplierId !== null) {
                vincularProveedorProductoStock($connection, $supplierId, $productId);
            }

            $previousStock = stockVendibleLotes($connection, $productId);
            guardarLotesStock($connection, $productId, $lotes, $supplierId);
            $finalStock = actualizarStockVendibleLotes($connection, $productId);

            $movementStatement = $connection->prepare(
                "INSERT INTO movimientos_stock (
                    id_producto,
                    id_usuario,
                    tipo_movimiento,
                    cantidad,
                    stock_anterior,
                    stock_final,
                    origen,
                    motivo,
                    referencia
                ) VALUES (
                    :producto,
                    :usuario,
                    'entrada',
                    :cantidad,
                    :anterior,
                    :final,
                    'manual',
                    :motivo,
                    :referencia
                )"
            );
            $movementStatement->execute([
                'producto' => $productId,
                'usuario' => $userId,
                'cantidad' => $finalStock - $previousStock,
                'anterior' => $previousStock,
                'final' => $finalStock,
                'motivo' => $reasonLabel,
                'referencia' => $reference,
            ]);

            $connection->commit();
            guardarModalAdmin(
                'success',
                'Entrada registrada',
                'Los lotes fueron creados y el stock físico quedó actualizado.'
            );
            header('Location: ' . $formUrl, true, 303);
            exit;
        }

        if ($type === 'salida') {
            if ($legacyStockWithoutLots) {
                $connection->rollBack();
                guardarEstadoMovimientoStock(
                    $productId,
                    $values,
                    ['tipo_movimiento' => 'No puedes retirar alimento fraccionado mientras exista stock histórico sin lotes. Regulariza primero el inventario físico.']
                );
                header('Location: ' . $formUrl, true, 303);
                exit;
            }

            $movementReason = 'Salida manual · ' . $reasonLabel;

            if ($values['salida_modo'] === 'presentacion') {
                $presentationId = (int) $values['id_presentacion'];
                $units = (int) $values['unidades_presentacion'];
                $validPresentationIds = array_map(
                    'intval',
                    array_column($presentations, 'id_presentacion')
                );

                if (!in_array($presentationId, $validPresentationIds, true)) {
                    $connection->rollBack();
                    guardarEstadoMovimientoStock(
                        $productId,
                        $values,
                        ['id_presentacion' => 'La presentación seleccionada no pertenece a este producto.']
                    );
                    header('Location: ' . $formUrl, true, 303);
                    exit;
                }

                descontarPresentacionFefo(
                    $connection,
                    $productId,
                    $presentationId,
                    $units,
                    $userId,
                    $movementReason,
                    $reference
                );
            } else {
                $grams = (int) $values['cantidad_gramos_salida'];
                descontarGramosLibresFefo(
                    $connection,
                    $productId,
                    $grams,
                    $userId,
                    'ajuste_negativo',
                    'manual',
                    $movementReason,
                    $reference
                );
            }

            $connection->commit();
            guardarModalAdmin(
                'success',
                'Salida manual registrada',
                'El stock físico fue descontado por FEFO sin afectar las reservas de pedidos.'
            );
            header('Location: ' . $formUrl, true, 303);
            exit;
        }

        if ($type === 'ajuste') {
            $targetPhysicalStock = (int) $values['_stock_fisico_contado'];

            if ($targetPhysicalStock < $reservedStock) {
                $connection->rollBack();
                guardarEstadoMovimientoStock(
                    $productId,
                    $values,
                    ['stock_fisico_contado' => 'El stock físico contado no puede ser menor que el stock reservado por pedidos.']
                );
                header('Location: ' . $formUrl, true, 303);
                exit;
            }

            if ($legacyStockWithoutLots) {
                if ($targetPhysicalStock === 0) {
                    $previousStock = (int) $stock['cantidad_actual'];
                    $connection->prepare(
                        'UPDATE stock SET cantidad_actual = 0, actualizado_en = CURRENT_TIMESTAMP WHERE id_producto = :producto'
                    )->execute(['producto' => $productId]);

                    registrarMovimientoRegularizacionStock(
                        $connection,
                        $productId,
                        $userId,
                        -$previousStock,
                        $previousStock,
                        0,
                        'Regularización · ' . $reasonLabel,
                        $reference
                    );

                    $connection->commit();
                    guardarModalAdmin(
                        'success',
                        'Stock regularizado',
                        'El conteo físico confirmó que no existe stock disponible y el registro histórico quedó conciliado.'
                    );
                    header('Location: ' . $formUrl, true, 303);
                    exit;
                }

                $supplierId = validarProveedorMovimientoStock($connection, $_POST['id_proveedor'] ?? null);
                $lotes = normalizarLotesFormulario($_POST['lotes'] ?? []);
                $lotErrors = validarLotesStock($lotes);

                if ($lotErrors !== []) {
                    $connection->rollBack();
                    $values['lotes'] = $lotes;
                    guardarEstadoMovimientoStock($productId, $values, $lotErrors);
                    header('Location: ' . $formUrl, true, 303);
                    exit;
                }

                $gramsInLots = sumarGramosLotesStock($lotes);
                if ($gramsInLots !== $targetPhysicalStock) {
                    $connection->rollBack();
                    $values['lotes'] = $lotes;
                    guardarEstadoMovimientoStock(
                        $productId,
                        $values,
                        ['lotes' => 'La suma de los lotes debe coincidir exactamente con el stock físico contado.']
                    );
                    header('Location: ' . $formUrl, true, 303);
                    exit;
                }

                if ($supplierId !== null) {
                    vincularProveedorProductoStock($connection, $supplierId, $productId);
                }

                $previousStock = (int) $stock['cantidad_actual'];
                guardarLotesStock($connection, $productId, $lotes, $supplierId);
                $finalStock = actualizarStockVendibleLotes($connection, $productId);
                $difference = $finalStock - $previousStock;

                if ($difference !== 0) {
                    registrarMovimientoRegularizacionStock(
                        $connection,
                        $productId,
                        $userId,
                        $difference,
                        $previousStock,
                        $finalStock,
                        'Regularización · ' . $reasonLabel,
                        $reference
                    );
                }

                $connection->commit();
                guardarModalAdmin(
                    'success',
                    'Stock regularizado',
                    'El stock histórico quedó identificado mediante lotes y sincronizado con el inventario físico.'
                );
                header('Location: ' . $formUrl, true, 303);
                exit;
            }

            $currentPhysicalStock = stockVendibleLotes($connection, $productId);
            $difference = $targetPhysicalStock - $currentPhysicalStock;

            if ($difference === 0) {
                $connection->rollBack();
                guardarEstadoMovimientoStock(
                    $productId,
                    $values,
                    ['stock_fisico_contado' => 'El conteo informado coincide con el stock físico registrado. No hay nada que regularizar.']
                );
                header('Location: ' . $formUrl, true, 303);
                exit;
            }

            if ($difference < 0) {
                descontarGramosLibresFefo(
                    $connection,
                    $productId,
                    abs($difference),
                    $userId,
                    'ajuste_negativo',
                    'manual',
                    'Regularización · ' . $reasonLabel,
                    $reference
                );
            } else {
                $existingLotAssignments = normalizarAsignacionesLotesExistentesStock($_POST['lotes_existentes'] ?? []);
                $existingLotErrors = validarAsignacionesLotesExistentesStock($connection, $productId, $existingLotAssignments);

                $lotes = normalizarLotesFormulario($_POST['lotes'] ?? []);
                $lotErrors = $lotes === [] ? [] : validarLotesStock($lotes);
                $allErrors = array_merge($existingLotErrors, $lotErrors);

                if ($allErrors !== []) {
                    $connection->rollBack();
                    $values['lotes_existentes'] = $existingLotAssignments;
                    $values['lotes'] = $lotes;
                    guardarEstadoMovimientoStock($productId, $values, $allErrors);
                    header('Location: ' . $formUrl, true, 303);
                    exit;
                }

                $gramsExistingLots = sumarGramosAsignacionesLotesExistentesStock($existingLotAssignments);
                $gramsNewLots = sumarGramosLotesStock($lotes);
                $identifiedTotal = $gramsExistingLots + $gramsNewLots;

                if ($identifiedTotal !== $difference) {
                    $connection->rollBack();
                    $values['lotes_existentes'] = $existingLotAssignments;
                    $values['lotes'] = $lotes;
                    guardarEstadoMovimientoStock(
                        $productId,
                        $values,
                        ['lotes' => 'Debes distribuir exactamente ' . formatearCantidadStock($difference, true) . ' entre los lotes existentes y/o nuevos.']
                    );
                    header('Location: ' . $formUrl, true, 303);
                    exit;
                }

                aplicarAumentoLotesExistentesStock($connection, $productId, $existingLotAssignments);

                if ($lotes !== []) {
                    $supplierId = validarProveedorMovimientoStock($connection, $_POST['id_proveedor'] ?? null);
                    if ($supplierId !== null) {
                        vincularProveedorProductoStock($connection, $supplierId, $productId);
                    }
                    guardarLotesStock($connection, $productId, $lotes, $supplierId);
                }

                $finalStock = actualizarStockVendibleLotes($connection, $productId);

                registrarMovimientoRegularizacionStock(
                    $connection,
                    $productId,
                    $userId,
                    $difference,
                    $currentPhysicalStock,
                    $finalStock,
                    'Regularización · ' . $reasonLabel,
                    $reference
                );
            }

            $connection->commit();
            guardarModalAdmin(
                'success',
                'Stock regularizado',
                'El inventario físico fue conciliado sin modificar las reservas de pedidos.'
            );
            header('Location: ' . $formUrl, true, 303);
            exit;
        }

        throw new InvalidArgumentException('La operación indicada no está disponible para este producto.');
    }

    // Productos no fraccionables: unidades físicas.
    $currentStock = (int) $stock['cantidad_actual'];
    $quantity = (int) $values['_cantidad_entera'];
    [$movementQuantity, $resultingStock] = calcularMovimientoStock(
        $type,
        $quantity,
        $currentStock
    );

    if ($resultingStock < 0 || $resultingStock < $reservedStock) {
        $connection->rollBack();
        $errors['cantidad'] = $type === 'ajuste'
            ? 'El stock físico final no puede ser menor que las unidades reservadas por pedidos.'
            : 'No existe stock libre suficiente para realizar esta salida.';
        guardarEstadoMovimientoStock($productId, $values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    if ($movementQuantity === 0) {
        $connection->rollBack();
        $errors['cantidad'] = 'El valor informado coincide con el stock actual. No hay nada que registrar.';
        guardarEstadoMovimientoStock($productId, $values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $persistedType = tipoPersistidoMovimientoStock(
        $type,
        (string) $values['motivo'],
        $movementQuantity
    );

    $movementReason = $type === 'ajuste'
        ? 'Regularización · ' . $reasonLabel
        : ($type === 'salida' ? 'Salida manual · ' . $reasonLabel : $reasonLabel);

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
            id_producto,
            id_usuario,
            tipo_movimiento,
            cantidad,
            stock_anterior,
            stock_final,
            origen,
            motivo,
            referencia
        ) VALUES (
            :id_producto,
            :id_usuario,
            :tipo_movimiento,
            :cantidad,
            :stock_anterior,
            :stock_final,
            'manual',
            :motivo,
            :referencia
        )"
    );
    $movementStatement->execute([
        'id_producto' => $productId,
        'id_usuario' => $userId,
        'tipo_movimiento' => $persistedType,
        'cantidad' => $movementQuantity,
        'stock_anterior' => $currentStock,
        'stock_final' => $resultingStock,
        'motivo' => $movementReason,
        'referencia' => $reference,
    ]);

    $connection->commit();

    $successModal = match ($type) {
        'entrada' => ['Entrada registrada', 'El stock físico fue actualizado correctamente.'],
        'salida' => ['Salida manual registrada', 'El stock físico libre fue descontado correctamente.'],
        default => ['Stock regularizado', 'El inventario físico fue conciliado correctamente.'],
    };

    guardarModalAdmin('success', $successModal[0], $successModal[1]);
    header('Location: ' . $formUrl, true, 303);
    exit;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    $reference = registrarExcepcionAdmin('Stock movement error', $exception);
    guardarEstadoMovimientoStock(
        $productId,
        $values,
        [],
        'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.',
        $reference
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}

function validarProveedorMovimientoStock(PDO $connection, mixed $value): ?int
{
    $raw = trim(is_scalar($value) ? (string) $value : '');
    if ($raw === '') {
        return null;
    }

    $supplierId = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($supplierId === false) {
        throw new InvalidArgumentException('Selecciona un proveedor válido.');
    }

    $statement = $connection->prepare(
        'SELECT 1 FROM proveedores WHERE id_proveedor = :proveedor AND activo = TRUE'
    );
    $statement->execute(['proveedor' => $supplierId]);

    if ($statement->fetchColumn() === false) {
        throw new InvalidArgumentException('El proveedor seleccionado no está activo.');
    }

    return (int) $supplierId;
}

function vincularProveedorProductoStock(PDO $connection, int $supplierId, int $productId): void
{
    $connection->prepare(
        'INSERT INTO proveedor_productos (id_proveedor, id_producto, activo)
        VALUES (:proveedor, :producto, TRUE)
        ON CONFLICT (id_proveedor, id_producto)
        DO UPDATE SET activo = TRUE'
    )->execute([
        'proveedor' => $supplierId,
        'producto' => $productId,
    ]);
}

function sumarGramosLotesStock(array $lotes): int
{
    $total = 0;
    foreach ($lotes as $lote) {
        $total += (int) round((float) ($lote['cantidad_total_g'] ?? 0));
    }

    return $total;
}

function normalizarAsignacionesLotesExistentesStock(mixed $input): array
{
    if (!is_array($input)) {
        return [];
    }

    $result = [];
    foreach ($input as $row) {
        if (!is_array($row)) {
            continue;
        }

        $rawId = trim(is_scalar($row['id_lote'] ?? null) ? (string) $row['id_lote'] : '');
        $rawQuantity = trim(is_scalar($row['cantidad_g'] ?? null) ? (string) $row['cantidad_g'] : '');

        if ($rawId === '' && $rawQuantity === '') {
            continue;
        }

        $result[] = [
            'id_lote' => $rawId,
            'cantidad_g' => $rawQuantity,
        ];
    }

    return $result;
}

function validarAsignacionesLotesExistentesStock(PDO $connection, int $productId, array $assignments): array
{
    $errors = [];
    $seen = [];
    $statement = $connection->prepare(
        "SELECT id_lote
         FROM stock_lotes
         WHERE id_lote = :lote
           AND id_producto = :producto
           AND activo = TRUE
           AND fecha_vencimiento >= CURRENT_DATE
         FOR UPDATE"
    );

    foreach ($assignments as $index => $assignment) {
        $id = filter_var($assignment['id_lote'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($assignment['cantidad_g'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            $errors['lotes_existentes_' . $index . '_id'] = 'Selecciona un lote existente válido.';
            continue;
        }
        if ($quantity === false) {
            $errors['lotes_existentes_' . $index . '_cantidad'] = 'La cantidad asignada al lote debe ser mayor que 0 g.';
            continue;
        }
        if (isset($seen[(int) $id])) {
            $errors['lotes_existentes_' . $index . '_duplicado'] = 'Cada lote existente debe seleccionarse una sola vez.';
            continue;
        }

        $statement->execute(['lote' => (int) $id, 'producto' => $productId]);
        if ($statement->fetchColumn() === false) {
            $errors['lotes_existentes_' . $index . '_id'] = 'El lote seleccionado no pertenece al producto, está inactivo o está vencido.';
            continue;
        }

        $seen[(int) $id] = true;
    }

    return $errors;
}

function sumarGramosAsignacionesLotesExistentesStock(array $assignments): int
{
    $total = 0;
    foreach ($assignments as $assignment) {
        $total += (int) ($assignment['cantidad_g'] ?? 0);
    }
    return $total;
}

function aplicarAumentoLotesExistentesStock(PDO $connection, int $productId, array $assignments): void
{
    if ($assignments === []) {
        return;
    }

    $statement = $connection->prepare(
        "UPDATE stock_lotes
         SET
            cantidad_inicial_g = cantidad_inicial_g + :cantidad,
            cantidad_disponible_g = cantidad_disponible_g + :cantidad,
            saldo_no_asignado_g = saldo_no_asignado_g + :cantidad,
            actualizado_en = CURRENT_TIMESTAMP
         WHERE id_lote = :lote
           AND id_producto = :producto
           AND activo = TRUE
           AND fecha_vencimiento >= CURRENT_DATE"
    );

    foreach ($assignments as $assignment) {
        $statement->execute([
            'cantidad' => (int) $assignment['cantidad_g'],
            'lote' => (int) $assignment['id_lote'],
            'producto' => $productId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('No fue posible actualizar uno de los lotes seleccionados para la regularización.');
        }
    }
}

function registrarMovimientoRegularizacionStock(
    PDO $connection,
    int $productId,
    int $userId,
    int $difference,
    int $previousStock,
    int $finalStock,
    string $reason,
    ?string $reference
): void {
    if ($difference === 0) {
        return;
    }

    $statement = $connection->prepare(
        "INSERT INTO movimientos_stock (
            id_producto,
            id_usuario,
            tipo_movimiento,
            cantidad,
            stock_anterior,
            stock_final,
            origen,
            motivo,
            referencia
        ) VALUES (
            :producto,
            :usuario,
            :tipo,
            :cantidad,
            :anterior,
            :final,
            'manual',
            :motivo,
            :referencia
        )"
    );
    $statement->execute([
        'producto' => $productId,
        'usuario' => $userId,
        'tipo' => $difference > 0 ? 'ajuste_positivo' : 'ajuste_negativo',
        'cantidad' => $difference,
        'anterior' => $previousStock,
        'final' => $finalStock,
        'motivo' => $reason,
        'referencia' => $reference,
    ]);
}
