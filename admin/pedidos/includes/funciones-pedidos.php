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


function flujoOperativoPedido(string $metodoEntrega): array
{
    return match ($metodoEntrega) {
        'retiro_en_tienda' => [
            'recibido',
            'en_preparacion',
            'listo_para_retiro',
            'entregado',
        ],
        'despacho' => [
            'recibido',
            'en_preparacion',
            'enviado',
            'entregado',
        ],
        default => [],
    };
}

/**
 * Estados que el administrador puede seleccionar para un pedido concreto.
 *
 * - Un pago no aprobado no puede entrar al flujo operativo.
 * - Se puede avanzar saltando etapas, pero nunca retroceder.
 * - "Cancelado" se permite mientras el pedido no esté entregado/cancelado.
 * - Retiro y despacho usan flujos distintos.
 *
 * @return array<string,string>
 */
function estadosGestionablesPedido(array $pedido): array
{
    $estadoActual = trim((string) ($pedido['estado'] ?? ''));
    $estadoPago = trim((string) ($pedido['estado_pago'] ?? ''));
    $metodoEntrega = trim((string) ($pedido['metodo_entrega'] ?? ''));

    if (!array_key_exists($estadoActual, estadosPedido())) {
        return [];
    }

    if (in_array($estadoActual, ['entregado', 'cancelado'], true)) {
        return [$estadoActual => etiquetaEstadoPedido($estadoActual)];
    }

    $disponibles = [
        $estadoActual => etiquetaEstadoPedido($estadoActual),
    ];

    if ($estadoPago !== 'pagado') {
        $disponibles['cancelado'] = etiquetaEstadoPedido('cancelado');
        return $disponibles;
    }

    $flujo = flujoOperativoPedido($metodoEntrega);
    $indiceActual = array_search($estadoActual, $flujo, true);

    if ($indiceActual === false) {
        $disponibles['cancelado'] = etiquetaEstadoPedido('cancelado');
        return $disponibles;
    }

    foreach (array_slice($flujo, $indiceActual) as $estado) {
        $disponibles[$estado] = etiquetaEstadoPedido($estado);
    }

    $disponibles['cancelado'] = etiquetaEstadoPedido('cancelado');

    return $disponibles;
}

function errorTransicionEstadoPedido(array $pedido, string $estadoNuevo): ?string
{
    if (!array_key_exists($estadoNuevo, estadosPedido())) {
        return 'Selecciona un estado de pedido válido.';
    }

    $estadoActual = trim((string) ($pedido['estado'] ?? ''));
    if ($estadoNuevo === $estadoActual) {
        return null;
    }

    $permitidos = estadosGestionablesPedido($pedido);
    if (array_key_exists($estadoNuevo, $permitidos)) {
        return null;
    }

    $estadoPago = trim((string) ($pedido['estado_pago'] ?? ''));
    $metodoEntrega = trim((string) ($pedido['metodo_entrega'] ?? ''));

    if ($estadoPago !== 'pagado') {
        return 'El pedido no puede avanzar mientras el pago no esté aprobado. Solo puedes mantener su estado actual o cancelarlo.';
    }

    if ($metodoEntrega === 'retiro_en_tienda' && $estadoNuevo === 'enviado') {
        return 'Un pedido con retiro en tienda no puede marcarse como enviado.';
    }

    if ($metodoEntrega === 'despacho' && $estadoNuevo === 'listo_para_retiro') {
        return 'Un pedido con despacho a domicilio no puede marcarse como listo para retiro.';
    }

    if (in_array($estadoActual, ['entregado', 'cancelado'], true)) {
        return 'Este pedido ya está cerrado y su estado operativo no puede modificarse.';
    }

    return 'El cambio de estado solicitado no corresponde al flujo operativo de este pedido.';
}

function resumenEstadoOperativoPedido(string $estado, ?string $metodoEntrega = null): string
{
    return match ($estado) {
        'recibido' => 'Pedido confirmado. Está a la espera de comenzar su preparación.',
        'en_preparacion' => 'El equipo está preparando los productos de este pedido.',
        'listo_para_retiro' => 'El pedido está preparado y el cliente ya puede retirarlo.',
        'enviado' => 'El pedido salió a despacho y está en proceso de entrega.',
        'entregado' => 'Pedido completado y entregado al cliente.',
        'cancelado' => 'Pedido cancelado. No debe continuar el flujo operativo.',
        default => 'Revisa el estado actual antes de continuar.',
    };
}

function textoFlujoOperativoPedido(array $pedido): string
{
    $flujo = flujoOperativoPedido(trim((string) ($pedido['metodo_entrega'] ?? '')));

    if ($flujo === []) {
        return 'No hay un flujo de entrega definido para este pedido.';
    }

    return implode(' → ', array_map(
        static fn (string $estado): string => etiquetaEstadoPedido($estado),
        $flujo
    ));
}
