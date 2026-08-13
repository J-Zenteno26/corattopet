<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-carrito.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-stock-lotes.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-checkout.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-webpay.php';
require_once dirname(__DIR__, 2) . '/shared/notificaciones-pedido.php';

$pdo = database();

$token = '';
foreach ([$_GET['token_ws'] ?? null, $_POST['token_ws'] ?? null] as $candidate) {
    if (is_scalar($candidate) && trim((string) $candidate) !== '') {
        $token = trim((string) $candidate);
        break;
    }
}

$tbkToken = is_scalar($_GET['TBK_TOKEN'] ?? $_POST['TBK_TOKEN'] ?? null)
    ? trim((string) ($_GET['TBK_TOKEN'] ?? $_POST['TBK_TOKEN']))
    : '';

$tbkBuyOrder = is_scalar(
    $_GET['TBK_ORDEN_COMPRA']
        ?? $_POST['TBK_ORDEN_COMPRA']
        ?? null
) ? trim(
    (string) (
        $_GET['TBK_ORDEN_COMPRA']
        ?? $_POST['TBK_ORDEN_COMPRA']
    )
) : '';

$tbkSessionId = is_scalar(
    $_GET['TBK_ID_SESION']
        ?? $_POST['TBK_ID_SESION']
        ?? null
) ? trim(
    (string) (
        $_GET['TBK_ID_SESION']
        ?? $_POST['TBK_ID_SESION']
    )
) : '';

if ($token === '' && ($tbkToken !== '' || $tbkBuyOrder !== '')) {
    $cancelStatement = $pdo->prepare(
        "SELECT
            pw.id_pago_webpay,
            pw.id_pedido,
            p.codigo_pedido,
            p.estado_pago
         FROM pagos_webpay pw
         INNER JOIN pedidos p
            ON p.id_pedido = pw.id_pedido
         WHERE
            (:buy_order <> '' AND pw.buy_order = :buy_order)
            OR (
                :session_id <> ''
                AND pw.session_id = :session_id
            )
         ORDER BY pw.id_pago_webpay DESC
         LIMIT 1"
    );

    $cancelStatement->execute([
        'buy_order' => $tbkBuyOrder,
        'session_id' => $tbkSessionId,
    ]);

    $cancelled = $cancelStatement->fetch(PDO::FETCH_ASSOC);

    if ($cancelled) {
        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                "UPDATE pagos_webpay
                 SET
                    estado = 'abandonado',
                    token_ws = COALESCE(NULLIF(:tbk_token, ''), token_ws),
                    actualizado_en = NOW()
                 WHERE id_pago_webpay = :id_pago_webpay"
            )->execute([
                'tbk_token' => $tbkToken,
                'id_pago_webpay' => (int) $cancelled['id_pago_webpay'],
            ]);

            if ((string) $cancelled['estado_pago'] !== 'pagado') {
                liberarStockReservadoPedidoCheckout(
                    $pdo,
                    (int) $cancelled['id_pedido']
                );

                $pdo->prepare(
                    "INSERT INTO pedido_historial_estados (
                        id_pedido,
                        estado_nuevo,
                        estado_pago_nuevo,
                        observacion
                     ) VALUES (
                        :id_pedido,
                        'recibido',
                        'pendiente',
                        :observacion
                     )"
                )->execute([
                    'id_pedido' => (int) $cancelled['id_pedido'],
                    'observacion' => 'El cliente regresó desde Webpay sin completar el pago. La reserva de stock fue liberada.',
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Error al registrar abandono Webpay: '
                . $exception->getMessage()
            );
        }

        $_SESSION['webpay_resultado'] = [
            'tipo' => 'pendiente',
            'titulo' => 'Pago no completado',
            'mensaje' => 'Tu pedido sigue guardado y puedes volver a intentar el pago.',
            'codigo_pedido' => (string) $cancelled['codigo_pedido'],
        ];
    } else {
        $_SESSION['webpay_resultado'] = [
            'tipo' => 'error',
            'titulo' => 'No pudimos identificar el pago',
            'mensaje' => 'Regresa al checkout para intentarlo nuevamente.',
        ];
    }

    header('Location: ' . appUrl('public/webpay/resultado.php'));
    exit;
}

if ($token === '') {
    $_SESSION['webpay_resultado'] = [
        'tipo' => 'error',
        'titulo' => 'Respuesta de pago incompleta',
        'mensaje' => 'Webpay no entregó un token válido.',
    ];

    header('Location: ' . appUrl('public/webpay/resultado.php'));
    exit;
}

$paymentStatement = $pdo->prepare(
    "SELECT
        pw.*,
        p.codigo_pedido,
        p.total AS total_pedido,
        p.estado AS estado_pedido,
        p.estado_pago,
        p.metodo_entrega
     FROM pagos_webpay pw
     INNER JOIN pedidos p
        ON p.id_pedido = pw.id_pedido
     WHERE pw.token_ws = :token_ws
     ORDER BY pw.id_pago_webpay DESC
     LIMIT 1"
);
$paymentStatement->execute(['token_ws' => $token]);
$payment = $paymentStatement->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    $_SESSION['webpay_resultado'] = [
        'tipo' => 'error',
        'titulo' => 'Pago no reconocido',
        'mensaje' => 'No encontramos una transacción asociada a este retorno.',
    ];

    header('Location: ' . appUrl('public/webpay/resultado.php'));
    exit;
}

try {
    $transaction = webpayTransaction();
    $response = $transaction->commit($token);
    $responseData = respuestaCommitWebpayAArray($response);

    $coincideOrden = hash_equals(
        (string) $payment['buy_order'],
        (string) ($responseData['buy_order'] ?? '')
    );

    $coincideSesion = hash_equals(
        (string) $payment['session_id'],
        (string) ($responseData['session_id'] ?? '')
    );

    $coincideMonto = (int) $payment['monto']
        === (int) round((float) ($responseData['amount'] ?? 0));

    $approved = (bool) ($responseData['approved'] ?? false)
        && $coincideOrden
        && $coincideSesion
        && $coincideMonto;

    $debeNotificarPedidoRecibido = false;

    $pdo->beginTransaction();

    try {
        $updatePayment = $pdo->prepare(
            "UPDATE pagos_webpay
             SET
                estado = :estado,
                status_transbank = :status_transbank,
                response_code = :response_code,
                authorization_code = :authorization_code,
                payment_type_code = :payment_type_code,
                installments_number = :installments_number,
                installments_amount = :installments_amount,
                card_last_four = :card_last_four,
                accounting_date = :accounting_date,
                transaction_date = :transaction_date,
                respuesta_commit = CAST(:respuesta_commit AS JSONB),
                actualizado_en = NOW(),
                confirmado_en = NOW()
             WHERE id_pago_webpay = :id_pago_webpay"
        );

        $updatePayment->execute([
            'estado' => $approved ? 'autorizado' : 'rechazado',
            'status_transbank' => $responseData['status'],
            'response_code' => $responseData['response_code'],
            'authorization_code' => $responseData['authorization_code'],
            'payment_type_code' => $responseData['payment_type_code'],
            'installments_number' => $responseData['installments_number'],
            'installments_amount' => $responseData['installments_amount'],
            'card_last_four' => $responseData['card_number'],
            'accounting_date' => $responseData['accounting_date'],
            'transaction_date' => $responseData['transaction_date'],
            'respuesta_commit' => json_encode(
                $responseData,
                JSON_UNESCAPED_SLASHES
            ),
            'id_pago_webpay' => (int) $payment['id_pago_webpay'],
        ]);

        $pedidoYaPagado = (string) $payment['estado_pago'] === 'pagado';
        $debeNotificarPedidoRecibido = $approved && !$pedidoYaPagado;

        if ($approved) {
            consumirStockReservadoPedidoCheckout(
                $pdo,
                (int) $payment['id_pedido'],
                (string) $payment['codigo_pedido']
            );
        } elseif (!$pedidoYaPagado) {
            liberarStockReservadoPedidoCheckout(
                $pdo,
                (int) $payment['id_pedido']
            );
        }

        $estadoPagoFinal = $approved
            ? 'pagado'
            : ($pedidoYaPagado ? 'pagado' : 'rechazado');

        if ($approved) {
            $updateOrder = $pdo->prepare(
                "UPDATE pedidos
                 SET
                    estado_pago = :estado_pago,
                    referencia_pago = :referencia_pago,
                    metodo_pago = 'webpay',
                    actualizado_en = NOW()
                 WHERE id_pedido = :id_pedido"
            );

            $updateOrder->execute([
                'estado_pago' => $estadoPagoFinal,
                'referencia_pago' => $responseData['authorization_code'],
                'id_pedido' => (int) $payment['id_pedido'],
            ]);
        } else {
            $updateOrder = $pdo->prepare(
                "UPDATE pedidos
                 SET
                    estado_pago = :estado_pago,
                    metodo_pago = 'webpay',
                    actualizado_en = NOW()
                 WHERE id_pedido = :id_pedido"
            );

            $updateOrder->execute([
                'estado_pago' => $estadoPagoFinal,
                'id_pedido' => (int) $payment['id_pedido'],
            ]);
        }

        $history = $pdo->prepare(
            "INSERT INTO pedido_historial_estados (
                id_pedido,
                estado_nuevo,
                estado_pago_nuevo,
                observacion
             ) VALUES (
                :id_pedido,
                :estado_nuevo,
                :estado_pago_nuevo,
                :observacion
             )"
        );

        $history->execute([
            'id_pedido' => (int) $payment['id_pedido'],
            'estado_nuevo' => (string) $payment['estado_pedido'],
            'estado_pago_nuevo' => $estadoPagoFinal,
            'observacion' => $approved
                ? 'Pago autorizado por Webpay.'
                : (
                    $pedidoYaPagado
                        ? 'Intento de pago rechazado recibido después de que el pedido ya había sido pagado. No se modificó el stock.'
                        : 'Pago rechazado o datos de retorno no coincidentes. La reserva de stock fue liberada.'
                ),
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    if ($debeNotificarPedidoRecibido) {
        try {
            notificarPedidoRecibido(
                $pdo,
                (int) $payment['id_pedido']
            );
        } catch (Throwable $notificationException) {
            registrarErrorNotificacionPedido(
                'pedido_recibido',
                (int) $payment['id_pedido'],
                $notificationException
            );
        }
    }

    if ($approved) {
        unset($_SESSION['carrito']);
        unset($_SESSION['checkout_despacho']);
        unset($_SESSION['checkout_token']);
        unset($_SESSION['checkout_pedido']);
        unset($_SESSION['webpay_pago_actual']);
    }

    $_SESSION['webpay_resultado'] = [
        'tipo' => $approved ? 'exito' : 'error',
        'titulo' => $approved
            ? 'Pago aprobado'
            : 'Pago rechazado',
        'mensaje' => $approved
            ? (
                (string) ($payment['metodo_entrega'] ?? '') === 'retiro_en_tienda'
                    ? 'Tu pedido fue recepcionado. Prepararemos tu compra y te avisaremos cuando esté lista para retiro. Por favor, espera nuestra confirmación antes de acercarte a la tienda.'
                    : 'Recibimos tu pago y el pedido quedó confirmado.'
            )
            : (
                $pedidoYaPagado
                    ? 'El pedido ya tenía un pago confirmado previamente.'
                    : 'El pedido sigue registrado. La reserva fue liberada y puedes intentar pagar nuevamente.'
            ),
        'codigo_pedido' => (string) $payment['codigo_pedido'],
        'monto' => (int) $payment['monto'],
        'authorization_code' => $responseData['authorization_code'],
        'transaction_date' => $responseData['transaction_date'],
        'payment_type' => etiquetaTipoPagoWebpay(
            $responseData['payment_type_code']
        ),
        'installments_number' => $responseData['installments_number'],
        'installments_amount' => $responseData['installments_amount'],
        'card_last_four' => $responseData['card_number'],
    ];
} catch (Throwable $exception) {
    error_log(
        'Error al confirmar Webpay: ' . $exception->getMessage()
    );

    try {
        $pdo->prepare(
            "UPDATE pagos_webpay
             SET
                estado = 'error',
                mensaje_error = :mensaje_error,
                actualizado_en = NOW()
             WHERE id_pago_webpay = :id_pago_webpay"
        )->execute([
            'mensaje_error' => mb_substr(
                $exception->getMessage(),
                0,
                1000
            ),
            'id_pago_webpay' => (int) $payment['id_pago_webpay'],
        ]);
    } catch (Throwable $databaseException) {
        error_log(
            'Error adicional al registrar fallo Webpay: '
            . $databaseException->getMessage()
        );
    }

    $_SESSION['webpay_resultado'] = [
        'tipo' => 'error',
        'titulo' => 'No pudimos confirmar el pago',
        'mensaje' => 'El pedido sigue guardado. No intentes pagar nuevamente hasta revisar su estado.',
        'codigo_pedido' => (string) $payment['codigo_pedido'],
    ];
}

header('Location: ' . appUrl('public/webpay/resultado.php'));
exit;