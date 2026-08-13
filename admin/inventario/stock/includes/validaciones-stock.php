<?php

declare(strict_types=1);

function validarDatosMovimientoStock(array $input, bool $fractionable = false): array
{
    $values = [];
    foreach ([
        'tipo_movimiento',
        'cantidad',
        'unidad_cantidad',
        'motivo',
        'observacion',
        'salida_modo',
        'cantidad_gramos_salida',
        'stock_fisico_contado',
        'id_proveedor',
        'id_presentacion',
        'unidades_presentacion',
    ] as $field) {
        $value = $input[$field] ?? '';
        $values[$field] = is_scalar($value) ? trim((string) $value) : '';
    }
    $values['lotes'] = is_array($input['lotes'] ?? null) ? $input['lotes'] : [];

    $errors = [];

    if (!in_array($values['tipo_movimiento'], ['entrada', 'salida', 'ajuste'], true)) {
        $errors['tipo_movimiento'] = 'Selecciona una operación válida.';
    }

    $reasonsByType = motivosMovimientoStock();
    $allowedReasons = $reasonsByType[$values['tipo_movimiento']] ?? [];

    if (!array_key_exists($values['motivo'], $allowedReasons)) {
        $errors['motivo'] = 'Selecciona un motivo válido para esta operación.';
    } else {
        $values['_motivo_label'] = $allowedReasons[$values['motivo']];
    }

    if ($values['motivo'] === 'otro' && $values['observacion'] === '') {
        $errors['observacion'] = 'La observación es obligatoria cuando seleccionas Otro.';
    }

    if (mb_strlen($values['observacion']) > 250) {
        $errors['observacion'] = 'La observación no puede superar los 250 caracteres.';
    }

    if (!$fractionable) {
        if (!ctype_digit($values['cantidad'])) {
            $errors['cantidad'] = 'Ingresa una cantidad entera igual o mayor que 0.';
        } elseif ($values['tipo_movimiento'] !== 'ajuste' && (int) $values['cantidad'] === 0) {
            $errors['cantidad'] = 'La cantidad debe ser mayor que 0 para entradas y salidas.';
        } else {
            $values['_cantidad_entera'] = (int) $values['cantidad'];
        }

        return [$values, $errors];
    }

    if ($values['tipo_movimiento'] === 'salida') {
        if (!in_array($values['salida_modo'], ['presentacion', 'gramos'], true)) {
            $errors['salida_modo'] = 'Selecciona cómo deseas registrar la salida.';
        }

        if ($values['salida_modo'] === 'presentacion') {
            if (!ctype_digit($values['id_presentacion']) || (int) $values['id_presentacion'] < 1) {
                $errors['id_presentacion'] = 'Selecciona una presentación válida.';
            }

            if (!ctype_digit($values['unidades_presentacion']) || (int) $values['unidades_presentacion'] < 1) {
                $errors['unidades_presentacion'] = 'Ingresa una cantidad de presentaciones mayor que 0.';
            }
        }

        if ($values['salida_modo'] === 'gramos') {
            if (!ctype_digit($values['cantidad_gramos_salida']) || (int) $values['cantidad_gramos_salida'] < 1) {
                $errors['cantidad_gramos_salida'] = 'Ingresa una cantidad exacta de gramos mayor que 0.';
            }
        }
    }

    if ($values['tipo_movimiento'] === 'ajuste') {
        if (!ctype_digit($values['stock_fisico_contado'])) {
            $errors['stock_fisico_contado'] = 'Ingresa el stock físico contado en gramos.';
        } else {
            $values['_stock_fisico_contado'] = (int) $values['stock_fisico_contado'];
        }
    }

    return [$values, $errors];
}
