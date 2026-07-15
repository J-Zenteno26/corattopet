<?php

declare(strict_types=1);

function validarMarca(array $input): array
{
    $values = [
        'nombre' => is_scalar($input['nombre'] ?? null) ? trim((string) $input['nombre']) : '',
        'activo' => ($input['activo'] ?? null) === '1',
    ];
    $errors = [];
    $length = mb_strlen($values['nombre']);
    if ($length < 2 || $length > 120) {
        $errors['nombre'] = 'El nombre debe tener entre 2 y 120 caracteres.';
    }

    return [$values, $errors];
}
