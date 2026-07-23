<?php

declare(strict_types=1);

/** @return array{0: array<string, mixed>, 1: array<string, string>} */
function validarConfiguracion(array $input): array
{
    $textLimits = [
        'nombre_tienda' => 120, 'razon_social' => 160, 'rut_empresa' => 20,
        'descripcion_breve' => 1000, 'mensaje_principal' => 1500, 'mensaje_secundario' => 1500,
        'email_contacto' => 160, 'whatsapp_principal' => 40, 'telefono_secundario' => 40,
        'direccion' => 500, 'comuna' => 100, 'region' => 100, 'horario_atencion' => 1000,
        'texto_retiro' => 1500, 'texto_despacho' => 1500, 'tiempo_preparacion' => 500,
        'instagram' => 255, 'facebook' => 255, 'tiktok' => 255, 'sitio_externo' => 255,
        'mensaje_banner' => 1000, 'mensaje_home' => 2000, 'mensaje_checkout' => 1500,
        'mensaje_post_compra' => 2000, 'mensaje_fraccionados' => 2000, 'moneda' => 10,
    ];
    $values = [];
    $errors = [];
    foreach ($textLimits as $field => $limit) {
        $values[$field] = is_scalar($input[$field] ?? null) ? trim((string) $input[$field]) : '';
        if (mb_strlen($values[$field]) > $limit) {
            $errors[$field] = "Este campo admite un máximo de {$limit} caracteres.";
        }
    }
    if ($values['nombre_tienda'] === '') {
        $errors['nombre_tienda'] = 'El nombre de la tienda es obligatorio.';
    }
    if ($values['email_contacto'] !== '' && filter_var($values['email_contacto'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email_contacto'] = 'Ingresa un email válido.';
    }
    foreach (['monto_minimo_compra', 'costo_despacho_base', 'despacho_gratis_desde'] as $field) {
        $raw = is_scalar($input[$field] ?? null) ? trim((string) $input[$field]) : '';
        if ($raw === '') {
            $values[$field] = 0;
        } elseif (filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $values[$field] = $raw;
            $errors[$field] = 'Ingresa un monto entero igual o mayor que 0.';
        } else {
            $values[$field] = (int) $raw;
        }
    }
    foreach (['permite_retiro', 'permite_despacho', 'iva_incluido', 'mostrar_stock', 'mostrar_sin_stock', 'permitir_venta_sin_stock'] as $field) {
        $values[$field] = ($input[$field] ?? null) === '1';
    }
    $values['moneda'] = $values['moneda'] === '' ? 'CLP' : strtoupper($values['moneda']);
    $mode = is_scalar($input['modo_tienda'] ?? null) ? trim((string) $input['modo_tienda']) : '';
    $values['modo_tienda'] = $mode;
    if (!in_array($mode, ['activa', 'mantenimiento'], true)) {
        $errors['modo_tienda'] = 'Selecciona un modo de tienda válido.';
    }
    return [$values, $errors];
}
