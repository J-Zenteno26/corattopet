<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-carrito.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-checkout.php';
require_once dirname(__DIR__) . '/includes/consultas-publicas.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Método no permitido.',
    ]);
    exit;
}

try {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new InvalidArgumentException(
            'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.'
        );
    }

    $idComuna = filter_var(
        $_POST['id_comuna'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idComuna === false) {
        throw new InvalidArgumentException('Selecciona una comuna válida.');
    }

    $pdo = database();
    $config = obtenerConfiguracionPublica($pdo);
    $resumen = obtenerResumenCheckout($pdo);

    if (!$resumen['valido']) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'mensaje' => implode(' ', $resumen['errores']),
        ]);
        exit;
    }


    validarMontoMinimoCheckout(
        (int) $resumen['subtotal'],
        $config,
        'despacho'
    );


    $tarifa = obtenerTarifaDespachoCheckout(
        $pdo,
        (int) $idComuna,
        (int) $resumen['peso_tarifable_gramos']
    );

    if ($tarifa === null) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'mensaje' => 'No encontramos una tarifa activa para esa comuna y peso.',
        ]);
        exit;
    }

    $calculoDespacho = calcularCostoDespachoCheckout(
        $tarifa,
        (int) $resumen['subtotal']
    );
    $costoDespacho = (int) $calculoDespacho['costo_despacho'];
    $total = (int) $resumen['subtotal'] + $costoDespacho;

    $_SESSION['checkout_despacho'] = [
        'id_tarifa_despacho' => (int) $tarifa['id_tarifa_despacho'],
        'id_comuna' => (int) $tarifa['id_comuna'],
        'id_region' => (int) $tarifa['id_region'],
        'comuna' => (string) $tarifa['comuna'],
        'region' => (string) $tarifa['region'],
        'peso_total_gramos' => (int) $resumen['peso_total_gramos'],
        'peso_tarifable_gramos' => (int) $resumen['peso_tarifable_gramos'],
        'peso_maximo_tarifa_gramos' => (int) $tarifa['peso_maximo_gramos'],
        'subtotal' => (int) $resumen['subtotal'],
        'costo_despacho' => $costoDespacho,
        'monto_envio_gratis' => $calculoDespacho['monto_envio_gratis'],
        'aplica_envio_gratis' => (bool) $calculoDespacho['aplica_envio_gratis'],
        'faltante_envio_gratis' => $calculoDespacho['faltante_envio_gratis'],
        'total' => $total,
        'calculado_en' => date(DATE_ATOM),
    ];

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Despacho calculado correctamente.',
        'despacho' => [
            'comuna' => (string) $tarifa['comuna'],
            'region' => (string) $tarifa['region'],
            'peso_tarifable_gramos' => (int) $resumen['peso_tarifable_gramos'],
            'peso_maximo_tarifa_gramos' => (int) $tarifa['peso_maximo_gramos'],
            'subtotal' => (int) $resumen['subtotal'],
            'costo_despacho' => $costoDespacho,
            'monto_envio_gratis' => $calculoDespacho['monto_envio_gratis'],
            'monto_envio_gratis_formateado' => $calculoDespacho['monto_envio_gratis'] !== null
                ? formatearDineroCheckout((int) $calculoDespacho['monto_envio_gratis'])
                : null,
            'aplica_envio_gratis' => (bool) $calculoDespacho['aplica_envio_gratis'],
            'faltante_envio_gratis' => $calculoDespacho['faltante_envio_gratis'],
            'faltante_envio_gratis_formateado' => $calculoDespacho['faltante_envio_gratis'] !== null
                ? formatearDineroCheckout((int) $calculoDespacho['faltante_envio_gratis'])
                : null,
            'total' => $total,
            'subtotal_formateado' => formatearDineroCheckout(
                (int) $resumen['subtotal']
            ),
            'costo_despacho_formateado' => formatearDineroCheckout(
                $costoDespacho
            ),
            'total_formateado' => formatearDineroCheckout($total),
        ],
    ]);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'mensaje' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log(
        'Error al calcular despacho del checkout: '
        . $exception->getMessage()
    );

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'No pudimos calcular el despacho en este momento.',
    ]);
}
