<?php

declare(strict_types=1);

function idProveedorValido(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : (int) $id;
}

function valoresProveedorIniciales(): array
{
    return [
        'nombre' => '', 'razon_social' => '', 'rut' => '', 'giro' => '',
        'contacto_principal' => '', 'telefono' => '', 'email' => '', 'direccion' => '',
        'comuna' => '', 'region' => '', 'sitio_web' => '', 'instagram' => '',
        'condicion_pago' => '', 'plazo_pago_dias' => '', 'metodo_pago' => '',
        'dias_despacho' => '', 'monto_minimo_compra' => '', 'contacto_ventas' => '',
        'contacto_cobranza' => '', 'observaciones' => '', 'activo' => true,
    ];
}

function monedaProveedor(mixed $value): string
{
    return $value === null || $value === '' ? 'No informado' : '$' . number_format((float) $value, 0, ',', '.');
}
