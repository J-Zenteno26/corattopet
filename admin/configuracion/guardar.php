<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-configuracion.php';
require_once __DIR__ . '/includes/validaciones-configuracion.php';

requireAuthentication();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); header('Allow: POST'); exit;
}

[$values, $errors] = validarConfiguracion($_POST);
$indexUrl = appUrl('admin/configuracion/index.php');
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoConfiguracion($values, [], 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $indexUrl, true, 303); exit;
}
if ($errors !== []) {
    guardarEstadoConfiguracion($values, $errors);
    header('Location: ' . $indexUrl, true, 303); exit;
}

try {
    $connection = database();
    $statement = $connection->prepare('UPDATE configuracion_tienda SET
        nombre_tienda=:nombre_tienda, razon_social=:razon_social, rut_empresa=:rut_empresa,
        descripcion_breve=:descripcion_breve,
        email_contacto=:email_contacto, whatsapp_principal=:whatsapp_principal, telefono_secundario=:telefono_secundario,
        direccion=:direccion, comuna=:comuna, region=:region, horario_atencion=:horario_atencion,
        permite_retiro=:permite_retiro, permite_despacho=:permite_despacho,
        monto_minimo_despacho=:monto_minimo_despacho,
        monto_minimo_retiro=:monto_minimo_retiro,
        instagram=:instagram, facebook=:facebook, tiktok=:tiktok,
        sitio_externo=:sitio_externo,
        moneda=:moneda, iva_incluido=:iva_incluido,
        mostrar_stock=:mostrar_stock, mostrar_sin_stock=:mostrar_sin_stock,
        permitir_venta_sin_stock=:permitir_venta_sin_stock, modo_tienda=:modo_tienda,
        actualizado_en=CURRENT_TIMESTAMP WHERE id_configuracion=1');
            $booleanFields = ['permite_retiro', 'permite_despacho', 'iva_incluido', 'mostrar_stock', 'mostrar_sin_stock', 'permitir_venta_sin_stock'];
            $integerFields = [
            'monto_minimo_despacho',
            'monto_minimo_retiro',
        ];
    foreach ($values as $field => $value) {
        $type = in_array($field, $booleanFields, true) ? PDO::PARAM_BOOL : (in_array($field, $integerFields, true) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $statement->bindValue(':' . $field, $value, $type);
    }
    $statement->execute();
    guardarModalAdmin('success', 'Configuración guardada', 'Los datos de la tienda fueron actualizados correctamente.');
    header('Location: ' . $indexUrl, true, 303); exit;
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Store settings update error', $exception);
    guardarEstadoConfiguracion($values, [], 'No se pudo completar la acción.', $reference);
    header('Location: ' . $indexUrl, true, 303); exit;
}
