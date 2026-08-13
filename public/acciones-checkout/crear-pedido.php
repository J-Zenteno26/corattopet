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

    $checkoutToken = is_scalar($_POST['checkout_token'] ?? null)
        ? trim((string) $_POST['checkout_token'])
        : '';

    $sessionToken = $_SESSION['checkout_token'] ?? null;

    if (
        $checkoutToken === ''
        || !is_string($sessionToken)
        || !hash_equals($sessionToken, $checkoutToken)
    ) {
        throw new InvalidArgumentException(
            'La sesión del checkout expiró. Recarga la página e inténtalo nuevamente.'
        );
    }
    $firmaCarritoActual = obtenerFirmaCarritoSesion();
    $pdo = database();

    if (
        isset($_SESSION['checkout_pedido'])
        && is_array($_SESSION['checkout_pedido'])
        && ($_SESSION['checkout_pedido']['checkout_token'] ?? null) === $checkoutToken
    ) {
        $pedidoPreparado = $_SESSION['checkout_pedido'];
        $firmaPedidoPreparado = $pedidoPreparado['firma_carrito'] ?? null;

        /*
         * Si la firma coincide, el pedido sigue representando exactamente
         * el carrito que el cliente está viendo.
         */
        if (
            is_string($firmaPedidoPreparado)
            && hash_equals($firmaPedidoPreparado, $firmaCarritoActual)
        ) {
            echo json_encode([
                'ok' => true,
                'ya_existia' => true,
                'mensaje' => 'El pedido ya fue preparado.',
                'pedido' => $pedidoPreparado,
                'iniciar_webpay_url' => appUrl(
                    'public/acciones-checkout/iniciar-webpay.php'
                ),
            ]);
            exit;
        }

        /*
         * El carrito cambió.
         *
         * Antes de olvidar el pedido anterior comprobamos su estado,
         * porque podría mantener stock reservado.
         */
        $idPedidoAnterior = (int) ($pedidoPreparado['id_pedido'] ?? 0);

        if ($idPedidoAnterior > 0) {
            $pedidoAnteriorStatement = $pdo->prepare(
                "SELECT
                estado_pago,
                estado_stock
             FROM pedidos
             WHERE id_pedido = :id_pedido
             LIMIT 1"
            );

            $pedidoAnteriorStatement->execute([
                'id_pedido' => $idPedidoAnterior,
            ]);

            $pedidoAnterior = $pedidoAnteriorStatement->fetch(PDO::FETCH_ASSOC);

            if (is_array($pedidoAnterior)) {
                $estadoPagoAnterior = (string) $pedidoAnterior['estado_pago'];
                $estadoStockAnterior = (string) $pedidoAnterior['estado_stock'];

                if (
                    $estadoPagoAnterior === 'pagado'
                    || $estadoStockAnterior === 'consumido'
                ) {
                    throw new RuntimeException(
                        'El pedido anterior ya fue confirmado y no puede reutilizarse con un carrito diferente.'
                    );
                }

                if ($estadoStockAnterior === 'reservado') {
                    /*
                     * Si Webpay nunca alcanzó a iniciarse, la reserva puede
                     * liberarse de forma segura.
                     *
                     * Si ya existe cualquier intento Webpay y la reserva sigue
                     * activa, el resultado podría ser incierto. No liberamos ni
                     * permitimos crear otro pedido hasta resolverlo.
                     */
                    $intentosStatement = $pdo->prepare(
                        "SELECT COUNT(*)
                     FROM pagos_webpay
                     WHERE id_pedido = :id_pedido"
                    );

                    $intentosStatement->execute([
                        'id_pedido' => $idPedidoAnterior,
                    ]);

                    $cantidadIntentos = (int) $intentosStatement->fetchColumn();

                    if ($cantidadIntentos > 0) {
                        http_response_code(409);

                        echo json_encode([
                            'ok' => false,
                            'mensaje' => 'Tu carrito cambió, pero existe un intento de pago anterior que todavía mantiene stock reservado. No iniciaremos un pago distinto hasta resolver esa operación.',
                        ]);

                        exit;
                    }

                    $pdo->beginTransaction();

                    try {
                        liberarStockReservadoPedidoCheckout(
                            $pdo,
                            $idPedidoAnterior
                        );

                        $pdo->commit();
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        throw $exception;
                    }
                }
            }
        }

        /*
         * El pedido anterior ya no representa el carrito actual
         * y no mantiene una operación Webpay incierta.
         */
        unset(
            $_SESSION['checkout_pedido'],
            $_SESSION['webpay_pago_actual']
        );
    }
    $config = obtenerConfiguracionPublica($pdo);
    $metodoEntrega = validarModalidadEntregaCheckout(
        $_POST['metodo_entrega'] ?? null,
        $config
    );
    $datosCliente = validarDatosClienteCheckout($_POST, $metodoEntrega);
    $resumen = obtenerResumenCheckout(
        $pdo,
        $metodoEntrega === 'despacho'
    );

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
        $metodoEntrega
    );


    $tarifa = null;

    if ($metodoEntrega === 'despacho') {
        $tarifa = obtenerTarifaDespachoCheckout(
            $pdo,
            (int) $datosCliente['id_comuna'],
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
    }

    $pdo->beginTransaction();

    try {
        $idClienteSesion = filter_var(
            $_SESSION['id_cliente'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $pedido = crearPedidoCheckout(
            $pdo,
            $datosCliente,
            $resumen,
            $tarifa,
            $metodoEntrega,
            $idClienteSesion === false ? null : (int) $idClienteSesion
        );

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    $_SESSION['checkout_pedido'] = [
        'checkout_token' => $checkoutToken,
        'firma_carrito' => $firmaCarritoActual,
        'id_pedido' => (int) $pedido['id_pedido'],
        'codigo_pedido' => (string) $pedido['codigo_pedido'],
        'subtotal' => (int) $pedido['subtotal'],
        'costo_despacho' => (int) $pedido['costo_despacho'],
        'total' => (int) $pedido['total'],
        'metodo_entrega' => (string) $pedido['metodo_entrega'],
        'creado_en' => date(DATE_ATOM),
    ];

    echo json_encode([
        'ok' => true,
        'ya_existia' => false,
        'mensaje' => 'Pedido preparado correctamente.',
        'pedido' => $_SESSION['checkout_pedido'],
        'iniciar_webpay_url' => appUrl(
            'public/acciones-checkout/iniciar-webpay.php'
        ),
    ]);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'mensaje' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log(
        'Error al crear pedido desde checkout: '
        . $exception->getMessage()
    );

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'No pudimos preparar el pedido en este momento.',
    ]);
}
