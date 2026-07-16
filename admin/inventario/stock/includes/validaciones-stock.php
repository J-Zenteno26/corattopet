<?php

declare(strict_types=1);

const MOTIVOS_MOVIMIENTO_STOCK = [
    'Compra o reposición',
    'Recepción de mercadería',
    'Venta manual',
    'Merma',
    'Producto dañado',
    'Vencimiento',
    'Devolución',
    'Conteo físico',
    'Corrección administrativa',
    'Otro',
];

function validarDatosMovimientoStock(array $input): array
{
    $values = [];
    foreach (['tipo_movimiento', 'cantidad', 'motivo', 'observacion'] as $field) {
        $value = $input[$field] ?? '';
        $values[$field] = is_scalar($value) ? trim((string) $value) : '';
    }

    $errors = [];
    if (!in_array($values['tipo_movimiento'], ['entrada', 'salida', 'ajuste'], true)) {
        $errors['tipo_movimiento'] = 'Selecciona un tipo de movimiento válido.';
    }

    if ($values['cantidad'] === '' || !ctype_digit($values['cantidad'])) {
        $errors['cantidad'] = 'Ingresa una cantidad entera igual o mayor que 0.';
    } elseif ($values['tipo_movimiento'] !== 'ajuste' && (int) $values['cantidad'] === 0) {
        $errors['cantidad'] = 'La cantidad debe ser mayor que 0 para entradas y salidas.';
    }

    if (!in_array($values['motivo'], MOTIVOS_MOVIMIENTO_STOCK, true)) {
        $errors['motivo'] = 'Selecciona un motivo válido.';
    }

    if (mb_strlen($values['observacion']) > 150) {
        $errors['observacion'] = 'La observación no puede superar los 150 caracteres.';
    }

    return [$values, $errors];
}
