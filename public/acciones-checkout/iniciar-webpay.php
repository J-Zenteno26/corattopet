<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-carrito.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-checkout.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-webpay.php';

$pedidoSesion = $_SESSION['checkout_pedido'] ?? null;

if (
    !is_array($pedidoSesion)
    || empty($pedidoSesion['id_pedido'])
) {
    header('Location: ' . appUrl('public/checkout.php'));
    exit;
}
$firmaPedido = $pedidoSesion['firma_carrito'] ?? null;
$firmaCarritoActual = obtenerFirmaCarritoSesion();

if (
    !is_string($firmaPedido)
    || !hash_equals($firmaPedido, $firmaCarritoActual)
) {
    $_SESSION['webpay_resultado'] = [
        'tipo' => 'error',
        'titulo' => 'Tu carrito cambió',
        'mensaje' => 'Los productos actuales ya no coinciden con el pedido preparado. Vuelve al checkout para generar el pedido correcto.',
        'codigo_pedido' => (string) ($pedidoSesion['codigo_pedido'] ?? ''),
    ];

    header(
        'Location: '
        . appUrl('public/webpay/resultado.php')
    );
    exit;
}
$idPedido = (int) $pedidoSesion['id_pedido'];
$pdo = database();

$statement = $pdo->prepare(
    "SELECT
        id_pedido,
        codigo_pedido,
        total,
        estado,
        estado_pago,
        estado_stock
     FROM pedidos
     WHERE id_pedido = :id_pedido
     LIMIT 1"
);
$statement->execute(['id_pedido' => $idPedido]);
$pedido = $statement->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    unset($_SESSION['checkout_pedido']);

    header('Location: ' . appUrl('public/checkout.php'));
    exit;
}

if ((string) $pedido['estado_pago'] === 'pagado') {
    header('Location: ' . appUrl('public/webpay/resultado.php'));
    exit;
}

if ((string) $pedido['estado'] === 'cancelado') {
    $_SESSION['webpay_resultado'] = [
        'tipo' => 'error',
        'titulo' => 'El pedido está cancelado',
        'mensaje' => 'Este pedido ya no puede enviarse a Webpay.',
        'codigo_pedido' => (string) $pedido['codigo_pedido'],
    ];

    header('Location: ' . appUrl('public/webpay/resultado.php'));
    exit;
}

$attemptStatement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM pagos_webpay
     WHERE id_pedido = :id_pedido"
);
$attemptStatement->execute(['id_pedido' => $idPedido]);
$numeroIntento = (int) $attemptStatement->fetchColumn() + 1;

$identificadores = generarIdentificadoresWebpay(
    $idPedido,
    $numeroIntento
);

$buyOrder = $identificadores['buy_order'];
$sessionId = $identificadores['session_id'];
$amount = (int) $pedido['total'];
$returnUrl = webpayReturnUrl();

$pdo->beginTransaction();

try {
    $estadoStock = (string) ($pedido['estado_stock'] ?? '');

    if (in_array($estadoStock, ['liberado', 'sin_reserva'], true)) {
        reservarStockPedidoCheckout($pdo, $idPedido);
    } elseif ($estadoStock === 'consumido') {
        throw new RuntimeException(
            'El stock de este pedido ya fue consumido y no puede iniciar un nuevo pago.'
        );
    } elseif ($estadoStock !== 'reservado') {
        throw new RuntimeException(
            'El pedido no se encuentra en un estado de stock válido para iniciar Webpay.'
        );
    }

    $insert = $pdo->prepare(
        "INSERT INTO pagos_webpay (
            id_pedido,
            buy_order,
            session_id,
            monto,
            estado
         ) VALUES (
            :id_pedido,
            :buy_order,
            :session_id,
            :monto,
            'creado'
         )
         RETURNING id_pago_webpay"
    );

    $insert->execute([
        'id_pedido' => $idPedido,
        'buy_order' => $buyOrder,
        'session_id' => $sessionId,
        'monto' => $amount,
    ]);

    $idPagoWebpay = (int) $insert->fetchColumn();

    $transaction = webpayTransaction();
    $response = $transaction->create(
        $buyOrder,
        $sessionId,
        $amount,
        $returnUrl
    );

    $token = trim((string) $response->getToken());
    $url = trim((string) $response->getUrl());

    if ($token === '' || $url === '') {
        throw new RuntimeException(
            'Transbank no devolvió los datos de redirección.'
        );
    }

    $update = $pdo->prepare(
        "UPDATE pagos_webpay
         SET
            token_ws = :token_ws,
            url_redireccion = :url_redireccion,
            estado = 'redirigido',
            respuesta_creacion = CAST(:respuesta_creacion AS JSONB),
            actualizado_en = NOW()
         WHERE id_pago_webpay = :id_pago_webpay"
    );

    $update->execute([
        'token_ws' => $token,
        'url_redireccion' => $url,
        'respuesta_creacion' => json_encode(
            [
                'token' => $token,
                'url' => $url,
                'return_url' => $returnUrl,
            ],
            JSON_UNESCAPED_SLASHES
        ),
        'id_pago_webpay' => $idPagoWebpay,
    ]);

    $pdo->prepare(
        "UPDATE pedidos
         SET
            estado_pago = 'pendiente',
            metodo_pago = 'webpay',
            actualizado_en = NOW()
         WHERE id_pedido = :id_pedido"
    )->execute(['id_pedido' => $idPedido]);

    $pdo->commit();

    $_SESSION['webpay_pago_actual'] = [
        'id_pago_webpay' => $idPagoWebpay,
        'id_pedido' => $idPedido,
        'buy_order' => $buyOrder,
        'session_id' => $sessionId,
        'monto' => $amount,
        'token_ws' => $token,
    ];
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Error al iniciar Webpay: ' . $exception->getMessage()
    );

    $_SESSION['webpay_resultado'] = [
        'tipo' => 'error',
        'titulo' => 'No pudimos iniciar el pago',
        'mensaje' => 'El pedido continúa guardado. Inténtalo nuevamente.',
        'codigo_pedido' => (string) $pedido['codigo_pedido'],
    ];

    header('Location: ' . appUrl('public/webpay/resultado.php'));
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirigiendo a Webpay | Coratto Pet</title>
</head>
<body>
    <form id="webpay-form" action="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" method="post">
        <input type="hidden" name="token_ws" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <noscript>
            <button type="submit">Continuar a Webpay</button>
        </noscript>
    </form>

    <script>
        document.querySelector('#webpay-form')?.submit();
    </script>
</body>
</html>
