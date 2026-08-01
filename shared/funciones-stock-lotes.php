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
    $st = $pdo->prepare('SELECT COALESCE(SUM(slp.unidades_disponibles * slp.gramos_por_unidad),0)
        FROM stock_lote_presentaciones slp
        INNER JOIN stock_lotes sl ON sl.id_lote=slp.id_lote
        WHERE sl.id_producto=:producto AND sl.activo=TRUE AND slp.activo=TRUE
          AND sl.fecha_vencimiento >= CURRENT_DATE');
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

/** Asigna unidades de una presentación a un lote. Debe ejecutarse dentro de una transacción. */
function gramosAsignacionPresentacion(int $unidades, int $gramosPorUnidad): int
{
    if ($unidades < 0) throw new DomainException('Las unidades deben ser un entero igual o mayor que 0.');
    if ($gramosPorUnidad <= 0) throw new DomainException('Los gramos por unidad deben ser mayores que 0.');
    return $unidades * $gramosPorUnidad;
}

function asignarStockPresentacionDesdeLote(PDO $pdo, int $productoId, int $presentacionId, int $loteId, int $unidades, int $gramosPorUnidad): int
{
    $used = gramosAsignacionPresentacion($unidades, $gramosPorUnidad);
    if ($unidades === 0) return actualizarStockVendibleLotes($pdo, $productoId);

    $lotStatement = $pdo->prepare(
        'SELECT saldo_no_asignado_g FROM stock_lotes
         WHERE id_lote=:lote AND id_producto=:producto AND activo=TRUE
           AND fecha_vencimiento>=CURRENT_DATE FOR UPDATE'
    );
    $lotStatement->execute(['lote' => $loteId, 'producto' => $productoId]);
    $balance = $lotStatement->fetchColumn();
    if ($balance === false) throw new DomainException('El lote seleccionado no está disponible para este producto.');

    if ($used > (float) $balance) {
        throw new DomainException('Las unidades exceden el saldo no asignado del lote. Reduce la cantidad o selecciona otro lote.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO stock_lote_presentaciones
         (id_lote,id_presentacion,unidades_iniciales,unidades_disponibles,gramos_por_unidad,activo)
         VALUES (:lote,:presentacion,:unidades,:unidades,:gramos,TRUE)'
    );
    $insert->execute(['lote'=>$loteId,'presentacion'=>$presentacionId,'unidades'=>$unidades,'gramos'=>$gramosPorUnidad]);
    $update = $pdo->prepare(
        'UPDATE stock_lotes SET saldo_no_asignado_g=saldo_no_asignado_g-:usados,actualizado_en=CURRENT_TIMESTAMP
         WHERE id_lote=:lote'
    );
    $update->execute(['usados'=>$used,'lote'=>$loteId]);

    return actualizarStockVendibleLotes($pdo, $productoId);
}

/** Descuenta unidades físicas usando FEFO. Debe ejecutarse dentro de una transacción. */
function descontarPresentacionFefo(PDO $pdo, int $productoId, int $presentacionId, int $unidades, int $usuarioId, string $motivo, ?string $referencia = null): int
{
    if ($unidades <= 0) throw new InvalidArgumentException('Las unidades a descontar deben ser mayores que cero.');
    $stockAnterior = stockVendibleLotes($pdo, $productoId);
    $st = $pdo->prepare('SELECT slp.id_lote_presentacion,slp.id_lote,slp.unidades_disponibles,slp.gramos_por_unidad
        FROM stock_lote_presentaciones slp INNER JOIN stock_lotes sl ON sl.id_lote=slp.id_lote
        WHERE sl.id_producto=:producto AND slp.id_presentacion=:presentacion AND sl.activo=TRUE AND slp.activo=TRUE
          AND sl.fecha_vencimiento >= CURRENT_DATE AND slp.unidades_disponibles>0
        ORDER BY sl.fecha_vencimiento ASC,sl.id_lote ASC FOR UPDATE OF slp,sl');
    $st->execute(['producto'=>$productoId,'presentacion'=>$presentacionId]);
    $filas=$st->fetchAll();
    $disponibles=array_sum(array_map(static fn(array $f): int => (int)$f['unidades_disponibles'],$filas));
    if ($disponibles < $unidades) throw new RuntimeException('No existen unidades suficientes de esta presentación.');
    $restantes=$unidades;
    $updateP=$pdo->prepare('UPDATE stock_lote_presentaciones SET unidades_disponibles=unidades_disponibles-:unidades,actualizado_en=CURRENT_TIMESTAMP WHERE id_lote_presentacion=:id');
    $updateL=$pdo->prepare('UPDATE stock_lotes SET cantidad_disponible_g=cantidad_disponible_g-:gramos,actualizado_en=CURRENT_TIMESTAMP WHERE id_lote=:id');
    $mov=$pdo->prepare("INSERT INTO movimientos_stock (id_producto,id_usuario,tipo_movimiento,cantidad,stock_anterior,stock_final,origen,motivo,referencia,id_lote,id_lote_presentacion) VALUES (:producto,:usuario,'salida',:cantidad,:anterior,:final,'manual',:motivo,:referencia,:lote,:lote_presentacion)");
    $acumulado=$stockAnterior;
    foreach($filas as $fila){
        if($restantes===0) break;
        $tomar=min($restantes,(int)$fila['unidades_disponibles']);
        $gramos=(int)round($tomar*(float)$fila['gramos_por_unidad']);
        $updateP->execute(['unidades'=>$tomar,'id'=>$fila['id_lote_presentacion']]);
        $updateL->execute(['gramos'=>$gramos,'id'=>$fila['id_lote']]);
        $mov->execute(['producto'=>$productoId,'usuario'=>$usuarioId,'cantidad'=>-$gramos,'anterior'=>$acumulado,'final'=>$acumulado-$gramos,'motivo'=>$motivo,'referencia'=>$referencia,'lote'=>$fila['id_lote'],'lote_presentacion'=>$fila['id_lote_presentacion']]);
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
        $presentaciones = [];
        foreach (($lote['presentaciones'] ?? []) as $id => $unidades) {
            if (ctype_digit((string) $id)) $presentaciones[(int) $id] = trim((string) $unidades);
        }
        $lotes[] = [
            'codigo_lote' => trim((string) ($lote['codigo_lote'] ?? '')),
            'fecha_elaboracion' => trim((string) ($lote['fecha_elaboracion'] ?? '')),
            'fecha_vencimiento' => trim((string) ($lote['fecha_vencimiento'] ?? '')),
            'cantidad_total_g' => trim(str_replace(',', '.', (string) ($lote['cantidad_total_g'] ?? ''))),
            'presentaciones' => $presentaciones,
        ];
    }
    return $lotes;
}

function validarLotesStock(array &$lotes, array $presentaciones): array
{
    $errores = [];
    if ($lotes === []) return ['lotes' => 'Debes registrar al menos un lote con fecha de vencimiento.'];
    $porId = [];
    foreach ($presentaciones as $p) $porId[(int) $p['id_presentacion']] = (float) $p['cantidad_gramos'];
    foreach ($lotes as $i => &$lote) {
        $prefijo = 'Lote ' . ($i + 1) . ': ';
        if ($lote['codigo_lote'] === '' || mb_strlen($lote['codigo_lote']) > 80) $errores['lotes_' . $i . '_codigo'] = $prefijo . 'el código es obligatorio (máximo 80 caracteres).';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lote['fecha_vencimiento'])) $errores['lotes_' . $i . '_vencimiento'] = $prefijo . 'la fecha de vencimiento es obligatoria.';
        if ($lote['fecha_elaboracion'] !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lote['fecha_elaboracion']) || ($lote['fecha_vencimiento'] !== '' && $lote['fecha_elaboracion'] > $lote['fecha_vencimiento']))) $errores['lotes_' . $i . '_elaboracion'] = $prefijo . 'la elaboración debe ser anterior o igual al vencimiento.';
        if (!preg_match('/^\d+(?:\.\d{1,3})?$/', $lote['cantidad_total_g']) || (float) $lote['cantidad_total_g'] <= 0) {
            $errores['lotes_' . $i . '_cantidad'] = $prefijo . 'la cantidad debe ser mayor que 0 y tener hasta 3 decimales.';
            continue;
        }
        $asignados = 0.0;
        foreach ($lote['presentaciones'] as $id => $unidades) {
            if (!array_key_exists($id, $porId) || !ctype_digit($unidades)) { $errores['lotes_' . $i . '_presentaciones'] = $prefijo . 'las unidades deben ser enteras y no negativas.'; continue; }
            $asignados += (int) $unidades * $porId[$id];
        }
        if ($asignados > (float) $lote['cantidad_total_g'] + 0.0001) $errores['lotes_' . $i . '_asignacion'] = $prefijo . 'los gramos asignados exceden la cantidad total.';
        $lote['_gramos_asignados'] = $asignados;
        $lote['_saldo_no_asignado'] = max(0, (float) $lote['cantidad_total_g'] - $asignados);
    }
    unset($lote);
    return $errores;
}

function guardarLotesStock(PDO $pdo, int $productoId, array $lotes, array $presentaciones, ?int $proveedorId = null): int
{
    $porId = [];
    foreach ($presentaciones as $p) $porId[(int) $p['id_presentacion']] = (float) $p['cantidad_gramos'];
    $insertLote = $pdo->prepare('INSERT INTO stock_lotes (id_producto,id_proveedor,codigo_lote,fecha_elaboracion,fecha_vencimiento,cantidad_inicial_g,cantidad_disponible_g,saldo_no_asignado_g) VALUES (:producto,:proveedor,:codigo,:elaboracion,:vencimiento,:inicial,:disponible,:saldo) RETURNING id_lote');
    $insertPresentacion = $pdo->prepare('INSERT INTO stock_lote_presentaciones (id_lote,id_presentacion,unidades_iniciales,unidades_disponibles,gramos_por_unidad) VALUES (:lote,:presentacion,:unidades,:unidades,:gramos)');
    $vendible = 0;
    foreach ($lotes as $lote) {
        $asignados = (float) ($lote['_gramos_asignados'] ?? 0);
        $insertLote->execute(['producto'=>$productoId,'proveedor'=>$proveedorId,'codigo'=>$lote['codigo_lote'],'elaboracion'=>$lote['fecha_elaboracion'] ?: null,'vencimiento'=>$lote['fecha_vencimiento'],'inicial'=>$lote['cantidad_total_g'],'disponible'=>$lote['cantidad_total_g'],'saldo'=>$lote['_saldo_no_asignado']]);
        $idLote = (int) $insertLote->fetchColumn();
        foreach ($lote['presentaciones'] as $id => $unidades) if ((int) $unidades > 0) {
            $insertPresentacion->execute(['lote'=>$idLote,'presentacion'=>$id,'unidades'=>(int)$unidades,'gramos'=>$porId[$id]]);
        }
        $vendible += (int) round($asignados);
    }
    return $vendible;
}

function presentacionesActivasProducto(PDO $pdo, int $productoId): array
{
    $st = $pdo->prepare('SELECT id_presentacion,nombre,cantidad_gramos,sku FROM producto_presentaciones WHERE id_producto=:id AND activo=TRUE ORDER BY orden,cantidad_gramos');
    $st->execute(['id'=>$productoId]); return $st->fetchAll();
}
