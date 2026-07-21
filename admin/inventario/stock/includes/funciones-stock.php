<?php

declare(strict_types=1);

function idPositivoStock(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function valoresInicialesMovimientoStock(): array
{
    return ['tipo_movimiento' => '', 'cantidad' => '', 'unidad_cantidad' => 'unidad', 'motivo' => '', 'observacion' => ''];
}

function motivosMovimientoStock(): array
{
    return [
        'entrada' => [
            'compra_reposicion' => 'Compra o reposición',
            'recepcion_saco' => 'Recepción de saco',
            'devolucion_cliente' => 'Devolución de cliente',
            'correccion_administrativa' => 'Corrección administrativa',
            'otro' => 'Otro',
        ],
        'salida' => [
            'venta_manual' => 'Venta manual',
            'venta_fraccionada' => 'Venta fraccionada',
            'merma_fraccionamiento' => 'Merma por fraccionamiento',
            'producto_danado' => 'Producto dañado',
            'vencimiento' => 'Vencimiento',
            'correccion_administrativa' => 'Corrección administrativa',
            'otro' => 'Otro',
        ],
        'ajuste' => [
            'conteo_fisico' => 'Conteo físico',
            'correccion_administrativa' => 'Corrección administrativa',
            'otro' => 'Otro',
        ],
    ];
}

function guardarEstadoMovimientoStock(int $productId, array $values, array $errors, ?string $generalError = null, ?string $reference = null): void
{
    $_SESSION['movimiento_stock_' . $productId] = [
        'valores' => $values,
        'errores' => $errors,
        'error_general' => $generalError,
        'referencia' => $reference,
    ];
}

function consumirEstadoMovimientoStock(int $productId): array
{
    $key = 'movimiento_stock_' . $productId;
    $state = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);

    return is_array($state) ? $state : [];
}

function estadoStockProducto(int $currentStock, int $minimumStock, bool $fractionable = false): string
{
    if ($currentStock === 0) {
        return 'Sin stock';
    }

    $lowStock = $fractionable ? $currentStock < $minimumStock : $currentStock <= $minimumStock;

    return $lowStock ? 'Stock bajo' : 'Disponible';
}

function claseEstadoStockProducto(int $currentStock, int $minimumStock, bool $fractionable = false): string
{
    if ($currentStock === 0) {
        return 'is-inactive';
    }

    $lowStock = $fractionable ? $currentStock < $minimumStock : $currentStock <= $minimumStock;

    return $lowStock ? '' : 'is-active';
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

    if ($type === 'salida' && $reason === 'venta_manual') {
        return 'venta';
    }

    if ($type === 'salida') {
        return 'salida';
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
