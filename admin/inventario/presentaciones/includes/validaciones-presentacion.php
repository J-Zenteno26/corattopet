<?php

declare(strict_types=1);

function idPositivoPresentacion(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

function valoresInicialesPresentacion(): array
{
    return ['nombre' => '', 'cantidad_gramos' => '', 'precio_venta' => '', 'sku' => '', 'orden' => '0', 'activo' => true];
}

function validarPresentacion(array $input): array
{
    $values = [];
    foreach (['nombre', 'cantidad_gramos', 'precio_venta', 'sku', 'orden'] as $field) {
        $values[$field] = is_scalar($input[$field] ?? null) ? trim((string) $input[$field]) : '';
    }
    $values['activo'] = ($input['activo'] ?? null) === '1';
    $errors = [];
    $length = mb_strlen($values['nombre']);
    if ($length < 3 || $length > 120) $errors['nombre'] = 'El nombre debe tener entre 3 y 120 caracteres.';
    if (!ctype_digit($values['cantidad_gramos']) || (int) $values['cantidad_gramos'] <= 0) $errors['cantidad_gramos'] = 'La cantidad debe ser un entero mayor que 0.';
    if (!ctype_digit($values['precio_venta'])) $errors['precio_venta'] = 'El precio debe ser un entero igual o mayor que 0.';
    if (mb_strlen($values['sku']) > 100) $errors['sku'] = 'El SKU no puede superar los 100 caracteres.';
    if (!ctype_digit($values['orden'])) $errors['orden'] = 'El orden debe ser un entero igual o mayor que 0.';
    return [$values, $errors];
}

function guardarEstadoPresentacion(string $key, array $values, array $errors, ?string $generalError = null): void
{
    $_SESSION[$key] = ['valores' => $values, 'errores' => $errors, 'error_general' => $generalError];
}

function consumirEstadoPresentacion(string $key): array
{
    $state = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);
    return is_array($state) ? $state : [];
}
