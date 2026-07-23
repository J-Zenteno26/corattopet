<?php

declare(strict_types=1);

function normalizarFiltrosClientes(array $input): array
{
    $text = static fn (string $key, int $max): string => mb_substr(is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '', 0, $max);
    $page = filter_var($input['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    return ['nombre' => $text('nombre', 140), 'email' => $text('email', 160), 'telefono' => $text('telefono', 40), 'comuna' => $text('comuna', 100), 'region' => $text('region', 100), 'pagina' => (int) $page, 'por_pagina' => 20];
}

function validarCliente(array $input): array
{
    $limits = ['nombre' => 140, 'email' => 160, 'telefono' => 40, 'rut' => 20, 'direccion' => 500, 'comuna' => 100, 'region' => 100];
    $values = []; $errors = [];
    foreach ($limits as $field => $limit) {
        $values[$field] = is_scalar($input[$field] ?? null) ? trim((string) $input[$field]) : '';
        if (mb_strlen($values[$field]) > $limit) { $errors[$field] = "Este campo admite un máximo de {$limit} caracteres."; }
    }
    if ($values['nombre'] === '') { $errors['nombre'] = 'El nombre del cliente es obligatorio.'; }
    if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) { $errors['email'] = 'Ingresa un email válido.'; }
    return [$values, $errors];
}
