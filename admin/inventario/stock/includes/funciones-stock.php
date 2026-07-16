<?php

declare(strict_types=1);

function idPositivoStock(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function valoresInicialesMovimientoStock(): array
{
    return ['tipo_movimiento' => '', 'cantidad' => '', 'motivo' => '', 'observacion' => ''];
}

function guardarEstadoMovimientoStock(int $productId, array $values, array $errors, ?string $generalError = null): void
{
    $_SESSION['movimiento_stock_' . $productId] = [
        'valores' => $values,
        'errores' => $errors,
        'error_general' => $generalError,
    ];
}

function consumirEstadoMovimientoStock(int $productId): array
{
    $key = 'movimiento_stock_' . $productId;
    $state = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);

    return is_array($state) ? $state : [];
}

function estadoStockProducto(int $currentStock, int $minimumStock): string
{
    if ($currentStock === 0) {
        return 'Sin stock';
    }

    return $currentStock <= $minimumStock ? 'Stock bajo' : 'Disponible';
}

function claseEstadoStockProducto(int $currentStock, int $minimumStock): string
{
    if ($currentStock === 0) {
        return 'is-inactive';
    }

    return $currentStock <= $minimumStock ? '' : 'is-active';
}

function calcularMovimientoStock(string $type, int $quantity, int $currentStock): array
{
    if ($type === 'entrada') {
        return [$quantity, $currentStock + $quantity];
    }

    if ($type === 'salida') {
        return [-$quantity, $currentStock - $quantity];
    }

    return [$quantity - $currentStock, $quantity];
}

function tipoPersistidoMovimientoStock(string $type, string $reason, int $movementQuantity): string
{
    if ($type === 'entrada') {
        return 'entrada';
    }

    if ($type === 'salida' && $reason === 'Venta manual') {
        return 'venta';
    }

    return $movementQuantity > 0 ? 'ajuste_positivo' : 'ajuste_negativo';
}

function formatearFechaMovimientoStock(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return 'Sin fecha';
    }

    try {
        return (new DateTimeImmutable($value))->format('d-m-Y H:i');
    } catch (Throwable) {
        return 'Sin fecha';
    }
}

function textoTipoMovimientoStock(string $type): string
{
    return match ($type) {
        'entrada' => 'Entrada',
        'salida' => 'Salida',
        'ajuste' => 'Ajuste',
        'ajuste_positivo', 'ajuste_negativo' => 'Ajuste',
        'venta' => 'Salida',
        'carga_inicial' => 'Carga inicial',
        default => ucfirst(str_replace('_', ' ', $type)),
    };
}
