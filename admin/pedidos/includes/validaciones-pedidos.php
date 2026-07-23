<?php

declare(strict_types=1);

function idPedidoValido(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : (int) $id;
}

function normalizarFiltrosPedidos(array $input): array
{
    $text = static fn (string $key): string => is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';
    $state = $text('estado');
    $payment = $text('estado_pago');
    $page = filter_var($input['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    return [
        'buscar' => mb_substr($text('buscar'), 0, 160),
        'estado' => array_key_exists($state, estadosPedido()) ? $state : '',
        'estado_pago' => array_key_exists($payment, estadosPagoPedido()) ? $payment : '',
        'fecha_desde' => fechaFiltroPedido($text('fecha_desde')),
        'fecha_hasta' => fechaFiltroPedido($text('fecha_hasta')),
        'pagina' => (int) $page, 'por_pagina' => 20,
    ];
}

function fechaFiltroPedido(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
}

function validarActualizacionPedido(array $input): array
{
    $values = [
        'id_pedido' => idPedidoValido($input['id_pedido'] ?? null),
        'estado' => is_scalar($input['estado'] ?? null) ? trim((string) $input['estado']) : '',
        'estado_pago' => is_scalar($input['estado_pago'] ?? null) ? trim((string) $input['estado_pago']) : '',
        'observaciones_internas' => is_scalar($input['observaciones_internas'] ?? null) ? trim((string) $input['observaciones_internas']) : '',
    ];
    $errors = [];
    if ($values['id_pedido'] === null) { $errors['id_pedido'] = 'El pedido indicado no es válido.'; }
    if (!array_key_exists($values['estado'], estadosPedido())) { $errors['estado'] = 'Selecciona un estado de pedido válido.'; }
    if (!array_key_exists($values['estado_pago'], estadosPagoPedido())) { $errors['estado_pago'] = 'Selecciona un estado de pago válido.'; }
    if (mb_strlen($values['observaciones_internas']) > 1000) { $errors['observaciones_internas'] = 'Las observaciones admiten un máximo de 1000 caracteres.'; }
    return [$values, $errors];
}
