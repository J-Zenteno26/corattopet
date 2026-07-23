<?php

declare(strict_types=1);

function idClienteValido(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : (int) $id;
}

function formatearDineroCliente(mixed $amount): string
{
    return '$' . number_format(max(0, (int) $amount), 0, ',', '.');
}

function formatearFechaCliente(mixed $date, string $format = 'd-m-Y H:i'): string
{
    try { return is_string($date) ? (new DateTimeImmutable($date))->format($format) : 'Sin registros'; }
    catch (Throwable) { return 'Sin registros'; }
}

function inicialCliente(): array
{
    return ['nombre' => '', 'email' => '', 'telefono' => '', 'rut' => '', 'direccion' => '', 'comuna' => '', 'region' => ''];
}

function guardarEstadoCliente(int $id, array $values, array $errors = [], ?string $general = null, ?string $reference = null): void
{
    $_SESSION['cliente_form_' . $id] = compact('values', 'errors', 'general', 'reference');
}

function consumirEstadoCliente(int $id): array
{
    $key = 'cliente_form_' . $id; $state = $_SESSION[$key] ?? []; unset($_SESSION[$key]);
    return is_array($state) ? $state : [];
}
