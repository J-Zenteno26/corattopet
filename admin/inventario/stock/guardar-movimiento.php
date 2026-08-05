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
    guardarModalAdmin('error', 'No fue posible registrar el movimiento', 'El producto indicado no es válido.');
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
    exit;
}

$formUrl = appUrl('admin/inventario/stock/index.php?id=' . $productId);
$values = array_merge(valoresInicialesMovimientoStock(), array_map(
    static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
    array_intersect_key($_POST, valoresInicialesMovimientoStock())
));
$errors = [];

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMovimientoStock($productId, $values, [], 'La solicitud no es válida. Recarga el formulario e intenta nuevamente.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}

$connection = null;

try {
    $connection = database();
    $connection->beginTransaction();

    $stockStatement = $connection->prepare(
        'SELECT s.cantidad_actual, s.cantidad_reservada, c.slug AS categoria_slug, p.detalles_opcionales
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
        guardarModalAdmin('error', 'No fue posible registrar el movimiento', 'El producto indicado no existe o no tiene un registro de stock.');
        header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
        exit;
    }
    $fractionable = esProductoFraccionable($stock);
    if ($fractionable) {
            $presentaciones = presentacionesActivasProducto($connection, $productId);
        if ((string)($_POST['tipo_movimiento'] ?? '') === 'entrada') {
            $supplierId = null;
            if (trim((string)($_POST['id_proveedor'] ?? '')) !== '') {
                $supplierId = filter_var($_POST['id_proveedor'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
                if ($supplierId === false) {
                    $connection->rollBack(); guardarEstadoMovimientoStock($productId,array_merge($values,['id_proveedor'=>(string)($_POST['id_proveedor']??'')]),['id_proveedor'=>'Selecciona un proveedor válido.']);
                    header('Location: '.$formUrl,true,303); exit;
                }
                $supplierStatement=$connection->prepare('SELECT 1 FROM proveedores WHERE id_proveedor=:proveedor AND activo=TRUE');
                $supplierStatement->execute(['proveedor'=>$supplierId]);
                if ($supplierStatement->fetchColumn()===false) {
                    $connection->rollBack(); guardarEstadoMovimientoStock($productId,array_merge($values,['id_proveedor'=>(string)$supplierId]),['id_proveedor'=>'El proveedor seleccionado no está activo.']);
                    header('Location: '.$formUrl,true,303); exit;
                }
                $supplierId=(int)$supplierId;
                $connection->prepare('INSERT INTO proveedor_productos (id_proveedor,id_producto,activo) VALUES (:proveedor,:producto,TRUE) ON CONFLICT (id_proveedor,id_producto) DO UPDATE SET activo=TRUE')->execute(['proveedor'=>$supplierId,'producto'=>$productId]);
            }
            $cuentaLotes = $connection->prepare('SELECT COUNT(*) FROM stock_lotes WHERE id_producto=:id AND activo=TRUE');
            $cuentaLotes->execute(['id'=>$productId]);
            if ((int)$stock['cantidad_actual'] > 0 && (int)$cuentaLotes->fetchColumn() === 0 && ($_POST['confirmar_regularizacion'] ?? '') !== '1') {
                $connection->rollBack();
                guardarEstadoMovimientoStock($productId,$values,['confirmar_regularizacion'=>'Debes confirmar explícitamente la regularización del stock histórico.']);
                header('Location: '.$formUrl,true,303); exit;
            }
            $lotes = normalizarLotesFormulario($_POST['lotes'] ?? []);
            $lotErrors = validarLotesStock($lotes);
            if ($lotErrors !== []) {
                $connection->rollBack();
                guardarEstadoMovimientoStock($productId, array_merge($values, ['tipo_movimiento'=>'entrada','lotes'=>$lotes,'id_proveedor'=>$supplierId===null?'':(string)$supplierId]), $lotErrors);
                header('Location: ' . $formUrl, true, 303); exit;
            }
            $anterior = stockVendibleLotes($connection,$productId);
            guardarLotesStock($connection,$productId,$lotes,$supplierId);
            $final = actualizarStockVendibleLotes($connection,$productId);
            $connection->prepare("INSERT INTO movimientos_stock (id_producto,id_usuario,tipo_movimiento,cantidad,stock_anterior,stock_final,origen,motivo,referencia) VALUES (:producto,:usuario,'entrada',:cantidad,:anterior,:final,'manual','Ingreso de lotes',:referencia)")
                ->execute(['producto'=>$productId,'usuario'=>(int)$_SESSION['id_usuario'],'cantidad'=>$final-$anterior,'anterior'=>$anterior,'final'=>$final,'referencia'=>trim((string)($_POST['observacion']??''))?:null]);
            $connection->commit();
            guardarModalAdmin('success','Lotes registrados','El stock vendible fue actualizado según el peso disponible de los lotes.');
            header('Location: '.$formUrl,true,303); exit;
        }
        if ((string)($_POST['tipo_movimiento'] ?? '') === 'salida') {
            $presentacionId=filter_var($_POST['id_presentacion']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $unidades=filter_var($_POST['unidades_presentacion']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if($presentacionId===false || $unidades===false){
                $connection->rollBack(); guardarEstadoMovimientoStock($productId,$values,['presentacion'=>'Selecciona una presentación y una cantidad de unidades válida.']);
                header('Location: '.$formUrl,true,303); exit;
            }
            $validos=array_column($presentaciones,'id_presentacion');
            if(!in_array((int)$presentacionId,array_map('intval',$validos),true)) throw new InvalidArgumentException('La presentación no pertenece al producto.');
            descontarPresentacionFefo($connection,$productId,(int)$presentacionId,(int)$unidades,(int)$_SESSION['id_usuario'],'Salida por presentación',trim((string)($_POST['observacion']??''))?:null);
            $connection->commit(); guardarModalAdmin('success','Salida registrada','Las unidades se descontaron por FEFO.');
            header('Location: '.$formUrl,true,303); exit;
        }
        $connection->rollBack();
        guardarEstadoMovimientoStock($productId,$values,['tipo_movimiento'=>'Los ajustes directos no están habilitados para alimento seco; usa entrada por lote o salida por presentación.']);
        header('Location: '.$formUrl,true,303); exit;
    }
    [$values, $errors] = validarDatosMovimientoStock($_POST, false);
    if ($errors !== []) {
        $connection->rollBack();
        guardarEstadoMovimientoStock($productId, $values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $currentStock = (int) $stock['cantidad_actual'];
    $reservedStock = (int) $stock['cantidad_reservada'];
    [$movementQuantity, $resultingStock] = calcularMovimientoStock(
        $values['tipo_movimiento'],
        (int) $values['_cantidad_entera'],
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
        'motivo' => $values['_motivo_label'],
        'referencia' => $values['observacion'] === '' ? null : $values['observacion'],
    ]);

    $connection->commit();
    $successModal = match ($values['tipo_movimiento']) {
        'entrada' => ['Entrada registrada', 'El stock fue actualizado correctamente.'],
        'salida' => ['Salida registrada', 'El stock fue descontado correctamente.'],
        default => ['Ajuste registrado', 'El ajuste de stock fue guardado correctamente.'],
    };
    guardarModalAdmin('success', $successModal[0], $successModal[1]);
    header('Location: ' . $formUrl, true, 303);
    exit;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    $reference = registrarExcepcionAdmin('Stock movement error', $exception);
    guardarEstadoMovimientoStock($productId, $values, [], 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', $reference);
    header('Location: ' . $formUrl, true, 303);
    exit;
}
