<?php

declare(strict_types=1);

/** @return array<string, mixed> */
function valoresInicialesConfiguracion(): array
{
    return [
        'nombre_tienda' => 'Coratto Pet', 'razon_social' => '', 'rut_empresa' => '',
        'descripcion_breve' => '', 'mensaje_principal' => '', 'mensaje_secundario' => '',
        'email_contacto' => '', 'whatsapp_principal' => '', 'telefono_secundario' => '',
        'direccion' => '', 'comuna' => '', 'region' => '', 'horario_atencion' => '',
        'permite_retiro' => true, 'texto_retiro' => '', 'permite_despacho' => false,
        'texto_despacho' => '', 'monto_minimo_compra' => 0, 'costo_despacho_base' => 0,
        'despacho_gratis_desde' => 0, 'tiempo_preparacion' => '', 'instagram' => '',
        'facebook' => '', 'tiktok' => '', 'sitio_externo' => '', 'mensaje_banner' => '',
        'mensaje_home' => '', 'mensaje_checkout' => '', 'mensaje_post_compra' => '',
        'mensaje_fraccionados' => '', 'moneda' => 'CLP', 'iva_incluido' => true,
        'mostrar_stock' => true, 'mostrar_sin_stock' => true,
        'permitir_venta_sin_stock' => false, 'modo_tienda' => 'activa',
    ];
}

/** @return array<string, mixed> */
function obtenerConfiguracionTienda(PDO $connection): array
{
    $connection->exec("INSERT INTO configuracion_tienda (id_configuracion, nombre_tienda) VALUES (1, 'Coratto Pet') ON CONFLICT (id_configuracion) DO NOTHING");
    $statement = $connection->query('SELECT * FROM configuracion_tienda WHERE id_configuracion = 1');
    $row = $statement->fetch();
    if (!is_array($row)) {
        return valoresInicialesConfiguracion();
    }

    $values = array_merge(valoresInicialesConfiguracion(), $row);
    foreach (['permite_retiro', 'permite_despacho', 'iva_incluido', 'mostrar_stock', 'mostrar_sin_stock', 'permitir_venta_sin_stock'] as $field) {
        $values[$field] = filter_var($values[$field], FILTER_VALIDATE_BOOL);
    }
    return $values;
}

/** @param array<string, mixed> $values @param array<string, string> $errors */
function guardarEstadoConfiguracion(array $values, array $errors = [], ?string $generalError = null, ?string $reference = null): void
{
    $_SESSION['configuracion_tienda_form'] = compact('values', 'errors', 'generalError', 'reference');
}

/** @return array<string, mixed> */
function consumirEstadoConfiguracion(): array
{
    $state = $_SESSION['configuracion_tienda_form'] ?? [];
    unset($_SESSION['configuracion_tienda_form']);
    return is_array($state) ? $state : [];
}
