<?php

declare(strict_types=1);

function validarDatosMovimientoStock(array $input, bool $fractionable = false): array
{
    $values = [];
    foreach (['tipo_movimiento', 'cantidad', 'unidad_cantidad', 'motivo', 'observacion'] as $field) {
        $value = $input[$field] ?? '';
        $values[$field] = is_scalar($value) ? trim((string) $value) : '';
    }

    $errors = [];
    if (!in_array($values['tipo_movimiento'], ['entrada', 'salida', 'ajuste'], true)) {
        $errors['tipo_movimiento'] = 'Selecciona un tipo de movimiento válido.';
    }

    if (!ctype_digit($values['cantidad'])) {
        $errors['cantidad'] = $fractionable
            ? 'Ingresa una cantidad entera de gramos igual o mayor que 0.'
            : 'Ingresa una cantidad entera igual o mayor que 0.';
    } elseif ($values['tipo_movimiento'] !== 'ajuste' && (int) $values['cantidad'] === 0) {
        $errors['cantidad'] = 'La cantidad debe ser mayor que 0 para entradas y salidas.';
    } else {
        $values['_cantidad_entera'] = (int) $values['cantidad'];
    }

    $reasonsByType = motivosMovimientoStock();
    $allowedReasons = $reasonsByType[$values['tipo_movimiento']] ?? [];
    if (!array_key_exists($values['motivo'], $allowedReasons)) {
        $errors['motivo'] = 'Selecciona un motivo válido para el tipo de movimiento.';
    } else {
        $values['_motivo_label'] = $allowedReasons[$values['motivo']];
    }

    if ($values['motivo'] === 'otro' && $values['observacion'] === '') {
        $errors['observacion'] = 'La observación es obligatoria cuando el motivo es Otro.';
    }

    if (mb_strlen($values['observacion']) > 150) {
        $errors['observacion'] = 'La observación no puede superar los 150 caracteres.';
    }

    return [$values, $errors];
}
