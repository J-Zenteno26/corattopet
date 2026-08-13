<?php

declare(strict_types=1);

function validarCategoriaDespacho(array $input): array
{
    $values = [
        'nombre' => is_scalar($input['nombre'] ?? null)
            ? trim((string) $input['nombre'])
            : '',
        'peso_estimado_gramos' => is_scalar($input['peso_estimado_gramos'] ?? null)
            ? trim((string) $input['peso_estimado_gramos'])
            : '',
        'tamano' => is_scalar($input['tamano'] ?? null)
            ? trim((string) $input['tamano'])
            : '',
        'activo' => ($input['activo'] ?? null) === '1',
    ];

    $errors = [];
    $nameLength = mb_strlen($values['nombre']);

    if ($nameLength < 2 || $nameLength > 100) {
        $errors['nombre'] = 'El nombre debe tener entre 2 y 100 caracteres.';
    }

    if (
        $values['peso_estimado_gramos'] === ''
        || !ctype_digit($values['peso_estimado_gramos'])
        || (int) $values['peso_estimado_gramos'] < 1
        || (int) $values['peso_estimado_gramos'] > 50000
    ) {
        $errors['peso_estimado_gramos'] = 'Ingresa un peso entre 1 y 50.000 gramos.';
    }

    if (!in_array($values['tamano'], ['pequeno', 'mediano', 'grande'], true)) {
        $errors['tamano'] = 'Selecciona un tamaño válido.';
    }

    return [$values, $errors];
}
