<?php

declare(strict_types=1);

function validarProveedor(array $input): array
{
    $values = valoresProveedorIniciales();
    foreach (array_keys($values) as $field) {
        if ($field !== 'activo')
            $values[$field] = trim((string) ($input[$field] ?? ''));
    }
    $values['email'] = strtolower($values['email']);
    $values['activo'] = isset($input['activo']) && in_array($input['activo'], ['1', 1, true, 'true'], true);
    $errors = [];
    $lengths = [
        'nombre' => 160,
        'razon_social' => 180,
        'rut' => 20,
        'giro' => 180,
        'contacto_principal' => 120,
        'telefono' => 40,
        'email' => 160,
        'comuna' => 100,
        'region' => 100,
        'sitio_web' => 220,
        'instagram' => 120,
        'condicion_pago' => 120,
        'metodo_pago' => 120,
        'dias_despacho' => 160,
        'contacto_ventas' => 160,
        'contacto_cobranza' => 160,
    ];
    if ($values['nombre'] === '')
        $errors['nombre'] = 'El nombre es obligatorio.';
    foreach ($lengths as $field => $max) {
        if (mb_strlen($values[$field]) > $max)
            $errors[$field] = "El campo no puede superar {$max} caracteres.";
    }
    if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false)
        $errors['email'] = 'Ingresa un email válido.';
    if ($values['sitio_web'] !== '' && filter_var($values['sitio_web'], FILTER_VALIDATE_URL) === false)
        $errors['sitio_web'] = 'Ingresa una URL válida, incluyendo https://.';
    if ($values['plazo_pago_dias'] !== '' && filter_var($values['plazo_pago_dias'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false)
        $errors['plazo_pago_dias'] = 'Ingresa un plazo igual o mayor que cero.';
    if ($values['monto_minimo_compra'] !== '' && (!is_numeric(str_replace(',', '.', $values['monto_minimo_compra'])) || (float) str_replace(',', '.', $values['monto_minimo_compra']) < 0))
        $errors['monto_minimo_compra'] = 'Ingresa un monto válido igual o mayor que cero.';
    $values['monto_minimo_compra'] = str_replace(',', '.', $values['monto_minimo_compra']);
    return [$values, $errors];
}

function validarAsociacionProveedor(array $input): array
{
    $values = ['id_producto' => idProveedorValido($input['id_producto'] ?? null), 'sku_proveedor' => trim((string) ($input['sku_proveedor'] ?? '')), 'precio_compra' => str_replace(',', '.', trim((string) ($input['precio_compra'] ?? ''))), 'activo' => isset($input['activo'])];
    $errors = [];
    if ($values['id_producto'] === null)
        $errors['id_producto'] = 'Selecciona un producto válido.';
    if (mb_strlen($values['sku_proveedor']) > 80)
        $errors['sku_proveedor'] = 'El SKU no puede superar 80 caracteres.';
    if ($values['precio_compra'] !== '' && (!is_numeric($values['precio_compra']) || (float) $values['precio_compra'] < 0))
        $errors['precio_compra'] = 'Ingresa un precio válido.';
    return [$values, $errors];
}
