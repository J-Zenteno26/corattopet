<?php

declare(strict_types=1);

function estadoVencimientoLote(string $fecha, ?DateTimeImmutable $hoy = null): int
{
    $hoy = ($hoy ?? new DateTimeImmutable('today'))->setTime(0, 0);
    $vencimiento = (new DateTimeImmutable($fecha))->setTime(0, 0);
    if ($vencimiento < $hoy) return 1;
    if ($vencimiento <= $hoy->modify('+3 months')) return 2;
    if ($vencimiento <= $hoy->modify('+6 months')) return 3;
    return 4;
}

function badgeVencimientoLote(string $fecha, ?DateTimeImmutable $hoy = null): string
{
    return match (estadoVencimientoLote($fecha, $hoy)) {
        1 => 'Vencido', 2 => 'Vence pronto', 3 => 'Próximo a vencer', default => 'Vigente',
    };
}

function stockVendibleLotes(PDO $pdo, int $productoId): int
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(cantidad_disponible_g),0)
        FROM stock_lotes
        WHERE id_producto=:producto AND activo=TRUE AND fecha_vencimiento>=CURRENT_DATE');
    $st->execute(['producto' => $productoId]);
    return (int) round((float) $st->fetchColumn());
}

function actualizarStockVendibleLotes(PDO $pdo, int $productoId): int
{
    $vendible = stockVendibleLotes($pdo, $productoId);
    $st = $pdo->prepare('UPDATE stock SET cantidad_actual=:cantidad,actualizado_en=CURRENT_TIMESTAMP WHERE id_producto=:producto');
    $st->execute(['cantidad' => $vendible, 'producto' => $productoId]);
    return $vendible;
}

/** Descuenta unidades físicas usando FEFO. Debe ejecutarse dentro de una transacción. */
function descontarPresentacionFefo(PDO $pdo, int $productoId, int $presentacionId, int $unidades, int $usuarioId, string $motivo, ?string $referencia = null): int
{
    if ($unidades <= 0) throw new InvalidArgumentException('Las unidades a descontar deben ser mayores que cero.');
    $presentationStatement=$pdo->prepare('SELECT cantidad_gramos FROM producto_presentaciones WHERE id_presentacion=:presentacion AND id_producto=:producto AND activo=TRUE');
    $presentationStatement->execute(['presentacion'=>$presentacionId,'producto'=>$productoId]);
    $gramosPorUnidad=$presentationStatement->fetchColumn();
    if($gramosPorUnidad===false || (int)$gramosPorUnidad<=0) throw new InvalidArgumentException('La presentación no está disponible para este producto.');
    $gramosPorUnidad=(int)$gramosPorUnidad;
    $stockAnterior = stockVendibleLotes($pdo, $productoId);
    $st = $pdo->prepare('SELECT id_lote,cantidad_disponible_g
        FROM stock_lotes
        WHERE id_producto=:producto AND activo=TRUE AND fecha_vencimiento>=CURRENT_DATE
          AND cantidad_disponible_g>=:gramos
        ORDER BY fecha_vencimiento ASC,id_lote ASC FOR UPDATE');
    $st->execute(['producto'=>$productoId,'gramos'=>$gramosPorUnidad]);
    $filas=$st->fetchAll();
    $disponibles=array_sum(array_map(static fn(array $f): int => (int)floor((float)$f['cantidad_disponible_g']/$gramosPorUnidad),$filas));
    if ($disponibles < $unidades) throw new RuntimeException('No existen unidades suficientes de esta presentación.');
    $restantes=$unidades;
    $updateL=$pdo->prepare('UPDATE stock_lotes SET cantidad_disponible_g=cantidad_disponible_g-:gramos_disponibles,saldo_no_asignado_g=LEAST(saldo_no_asignado_g,cantidad_disponible_g-:gramos_saldo),actualizado_en=CURRENT_TIMESTAMP WHERE id_lote=:id');
    $mov=$pdo->prepare("INSERT INTO movimientos_stock (id_producto,id_usuario,tipo_movimiento,cantidad,stock_anterior,stock_final,origen,motivo,referencia,id_lote,id_lote_presentacion) VALUES (:producto,:usuario,'salida',:cantidad,:anterior,:final,'manual',:motivo,:referencia,:lote,NULL)");
    $acumulado=$stockAnterior;
    foreach($filas as $fila){
        if($restantes===0) break;
        $tomar=min($restantes,(int)floor((float)$fila['cantidad_disponible_g']/$gramosPorUnidad));
        $gramos=$tomar*$gramosPorUnidad;
        $updateL->execute(['gramos_disponibles'=>$gramos,'gramos_saldo'=>$gramos,'id'=>$fila['id_lote']]);
        $mov->execute(['producto'=>$productoId,'usuario'=>$usuarioId,'cantidad'=>-$gramos,'anterior'=>$acumulado,'final'=>$acumulado-$gramos,'motivo'=>$motivo,'referencia'=>$referencia,'lote'=>$fila['id_lote']]);
        $acumulado-=$gramos; $restantes-=$tomar;
    }
    return actualizarStockVendibleLotes($pdo,$productoId);
}

function normalizarLotesFormulario(mixed $input): array
{
    if (!is_array($input)) return [];
    $lotes = [];
    foreach ($input as $lote) {
        if (!is_array($lote)) continue;
        $lotes[] = [
            'codigo_lote' => trim((string) ($lote['codigo_lote'] ?? '')),
            'fecha_elaboracion' => trim((string) ($lote['fecha_elaboracion'] ?? '')),
            'fecha_vencimiento' => trim((string) ($lote['fecha_vencimiento'] ?? '')),
            'cantidad_total_g' => trim(str_replace(',', '.', (string) ($lote['cantidad_total_g'] ?? ''))),
        ];
    }
    return $lotes;
}

function validarLotesStock(array &$lotes): array
{
    $errores = [];
    if ($lotes === []) return ['lotes' => 'Debes registrar al menos un lote con fecha de vencimiento.'];
    foreach ($lotes as $i => &$lote) {
        $prefijo = 'Lote ' . ($i + 1) . ': ';
        if ($lote['codigo_lote'] === '' || mb_strlen($lote['codigo_lote']) > 80) $errores['lotes_' . $i . '_codigo'] = $prefijo . 'el código es obligatorio (máximo 80 caracteres).';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lote['fecha_vencimiento'])) $errores['lotes_' . $i . '_vencimiento'] = $prefijo . 'la fecha de vencimiento es obligatoria.';
        if ($lote['fecha_elaboracion'] !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lote['fecha_elaboracion']) || ($lote['fecha_vencimiento'] !== '' && $lote['fecha_elaboracion'] > $lote['fecha_vencimiento']))) $errores['lotes_' . $i . '_elaboracion'] = $prefijo . 'la elaboración debe ser anterior o igual al vencimiento.';
        if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $lote['cantidad_total_g']) || (float) $lote['cantidad_total_g'] <= 0) {
            $errores['lotes_' . $i . '_cantidad'] = $prefijo . 'la cantidad debe ser mayor que 0 y tener hasta 3 decimales.';
            continue;
        }
    }
    unset($lote);
    return $errores;
}

function guardarLotesStock(PDO $pdo, int $productoId, array $lotes, ?int $proveedorId = null): int
{
    $insertLote = $pdo->prepare('INSERT INTO stock_lotes (id_producto,id_proveedor,codigo_lote,fecha_elaboracion,fecha_vencimiento,cantidad_inicial_g,cantidad_disponible_g,saldo_no_asignado_g) VALUES (:producto,:proveedor,:codigo,:elaboracion,:vencimiento,:inicial,:disponible,:saldo) RETURNING id_lote');
    $vendible = 0;
    foreach ($lotes as $lote) {
        $insertLote->execute(['producto'=>$productoId,'proveedor'=>$proveedorId,'codigo'=>$lote['codigo_lote'],'elaboracion'=>$lote['fecha_elaboracion'] ?: null,'vencimiento'=>$lote['fecha_vencimiento'],'inicial'=>$lote['cantidad_total_g'],'disponible'=>$lote['cantidad_total_g'],'saldo'=>$lote['cantidad_total_g']]);
        $insertLote->fetchColumn();
        $vendible += (int) round((float)$lote['cantidad_total_g']);
    }
    return $vendible;
}

function presentacionesActivasProducto(PDO $pdo, int $productoId): array
{
    $st = $pdo->prepare('SELECT id_presentacion,nombre,cantidad_gramos,sku FROM producto_presentaciones WHERE id_producto=:id AND activo=TRUE ORDER BY orden,cantidad_gramos');
    $st->execute(['id'=>$productoId]); return $st->fetchAll();
}
