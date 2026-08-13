<?php

declare(strict_types=1);

function idPositivoStock(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function valoresInicialesMovimientoStock(): array
{
    return [
        'tipo_movimiento' => '',
        'cantidad' => '',
        'unidad_cantidad' => 'unidad',
        'motivo' => '',
        'observacion' => '',
        'salida_modo' => 'presentacion',
        'cantidad_gramos_salida' => '',
        'stock_fisico_contado' => '',
        'id_proveedor' => '',
        'id_presentacion' => '',
        'unidades_presentacion' => '1',
        'lotes' => [],
    ];
}

function motivosMovimientoStock(): array
{
    return [
        'entrada' => [
            'compra_reposicion' => 'Compra o reposición',
            'recepcion_saco' => 'Recepción de producto',
            'devolucion_cliente' => 'Devolución de cliente',
            'correccion_administrativa' => 'Corrección administrativa',
            'otro' => 'Otro',
        ],
        'salida' => [
            'merma_fraccionamiento' => 'Merma por fraccionamiento',
            'producto_danado' => 'Producto dañado',
            'vencimiento' => 'Vencimiento',
            'uso_interno' => 'Uso interno',
            'perdida' => 'Pérdida o faltante',
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

function guardarEstadoMovimientoStock(
    int $productId,
    array $values,
    array $errors,
    ?string $generalError = null,
    ?string $reference = null
): void {
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

function estadoStockProducto(int $availableStock, int $minimumStock, bool $fractionable = false): string
{
    if ($availableStock <= 0) {
        return 'Sin stock disponible';
    }

    $lowStock = $fractionable
        ? $availableStock < $minimumStock
        : $availableStock <= $minimumStock;

    return $lowStock ? 'Stock bajo' : 'Disponible';
}

function claseEstadoStockProducto(int $availableStock, int $minimumStock, bool $fractionable = false): string
{
    if ($availableStock <= 0) {
        return 'is-inactive';
    }

    $lowStock = $fractionable
        ? $availableStock < $minimumStock
        : $availableStock <= $minimumStock;

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

/**
 * Los movimientos manuales no se persisten como "venta" ni "salida".
 * La venta pertenece al checkout/Webpay. Una salida manual de inventario
 * es un ajuste negativo trazable y su motivo explica la causa física.
 */
function tipoPersistidoMovimientoStock(string $type, string $reason, int $movementQuantity): string
{
    if ($type === 'entrada') {
        return 'entrada';
    }

    if ($type === 'salida') {
        return 'ajuste_negativo';
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

function textoTipoMovimientoStock(string $type, ?string $origin = null, ?string $reason = null): string
{
    $origin = trim((string) $origin);
    $reason = trim((string) $reason);

    if ($type === 'venta') {
        return $origin === 'webpay' ? 'Venta Webpay' : 'Venta';
    }

    if ($type === 'ajuste_negativo') {
        if (str_starts_with(mb_strtolower($reason), 'regularización')) {
            return 'Regularización';
        }

        return 'Salida manual';
    }

    if ($type === 'ajuste_positivo') {
        return 'Regularización';
    }

    return match ($type) {
        'entrada' => 'Entrada',
        'carga_inicial' => 'Carga inicial',
        'devolucion' => 'Devolución',
        'reserva' => 'Reserva',
        'liberacion_reserva' => 'Liberación reserva',
        'confirmacion_reserva' => 'Confirmación reserva',
        default => ucfirst(str_replace('_', ' ', $type)),
    };
}

function claseTipoMovimientoStock(string $type, ?string $origin = null): string
{
    if ($type === 'venta') {
        return 'is-sale';
    }

    return match ($type) {
        'entrada', 'carga_inicial', 'devolucion', 'ajuste_positivo' => 'is-entry',
        'ajuste_negativo' => 'is-exit',
        'reserva', 'confirmacion_reserva', 'liberacion_reserva' => 'is-system',
        default => 'is-neutral',
    };
}

function motivoCompletoMovimientoStock(?string $reason, ?string $reference): string
{
    $reason = trim((string) $reason);
    $reference = trim((string) $reference);

    if ($reason === '' && $reference === '') {
        return 'Sin detalle';
    }

    if ($reference === '') {
        return $reason;
    }

    if ($reason === '') {
        return $reference;
    }

    return $reason . ' · ' . $reference;
}
