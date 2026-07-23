<?php

declare(strict_types=1);

function estadosPedido(): array
{
    return ['recibido' => 'Recibido', 'en_preparacion' => 'En preparación', 'listo_para_retiro' => 'Listo para retiro', 'enviado' => 'Enviado', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado'];
}

function estadosPagoPedido(): array
{
    return ['pendiente' => 'Pendiente', 'pagado' => 'Pagado', 'rechazado' => 'Rechazado', 'reembolsado' => 'Reembolsado'];
}

function etiquetaEstadoPedido(string $state): string
{
    return estadosPedido()[$state] ?? ucfirst(str_replace('_', ' ', $state));
}

function etiquetaEstadoPagoPedido(string $state): string
{
    return estadosPagoPedido()[$state] ?? ucfirst(str_replace('_', ' ', $state));
}

function claseEstadoPedido(string $state, bool $payment = false): string
{
    $allowed = $payment ? array_keys(estadosPagoPedido()) : array_keys(estadosPedido());
    return in_array($state, $allowed, true) ? 'admin-order-badge--' . str_replace('_', '-', $state) : 'admin-order-badge--neutral';
}

function formatearDineroPedido(mixed $amount): string
{
    return '$' . number_format(max(0, (int) $amount), 0, ',', '.');
}

function formatearFechaPedido(mixed $date, string $format = 'd-m-Y H:i'): string
{
    try { return is_string($date) ? (new DateTimeImmutable($date))->format($format) : 'Sin fecha'; }
    catch (Throwable) { return 'Sin fecha'; }
}

function generarCodigoPedido(int $orderId): string
{
    if ($orderId < 1) { throw new InvalidArgumentException('El identificador del pedido debe ser positivo.'); }
    return 'COR-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
}

function descripcionEntregaPedido(?string $method): string
{
    $method = trim((string) $method);
    return $method === '' ? 'Por definir' : ucfirst(str_replace('_', ' ', $method));
}
