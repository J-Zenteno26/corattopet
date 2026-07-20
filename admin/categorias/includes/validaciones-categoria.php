<?php

declare(strict_types=1);

function validarCategoria(array $input): array
{
    $values = [
        'nombre' => is_scalar($input['nombre'] ?? null) ? trim((string) $input['nombre']) : '',
        'descripcion' => is_scalar($input['descripcion'] ?? null) ? trim((string) $input['descripcion']) : '',
        'orden' => is_scalar($input['orden'] ?? null) ? trim((string) $input['orden']) : '',
        'maneja_fraccionamiento' => ($input['maneja_fraccionamiento'] ?? null) === '1',
        'activo' => ($input['activo'] ?? null) === '1',
    ];
    $errors = [];
    $nameLength = mb_strlen($values['nombre']);

    if ($nameLength < 2 || $nameLength > 120) {
        $errors['nombre'] = 'El nombre debe tener entre 2 y 120 caracteres.';
    }
    if (mb_strlen($values['descripcion']) > 1000) {
        $errors['descripcion'] = 'La descripción no puede superar los 1000 caracteres.';
    }
    if ($values['orden'] === '' || !ctype_digit($values['orden'])) {
        $errors['orden'] = 'El orden debe ser un entero igual o mayor que 0.';
    }

    return [$values, $errors];
}
