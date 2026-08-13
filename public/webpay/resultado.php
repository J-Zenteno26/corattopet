<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__) . '/includes/consultas-publicas.php';

$resultado = $_SESSION['webpay_resultado'] ?? null;

if (!is_array($resultado)) {
    header('Location: ' . appUrl('public/catalogo.php'));
    exit;
}

unset($_SESSION['webpay_resultado']);

$config = [];

try {
    $config = obtenerConfiguracionPublica(database());
} catch (Throwable $exception) {
    error_log(
        'Error al cargar resultado Webpay: '
        . $exception->getMessage()
    );
}

$currentPage = 'carrito';
$whatsappUrl = obtenerWhatsappPublico($config);
$tipo = (string) ($resultado['tipo'] ?? 'error');
$esExito = $tipo === 'exito';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $esExito ? 'Pago aprobado' : 'Resultado del pago' ?> | Coratto Pet</title>

    <link
        rel="stylesheet"
        href="<?= e(appUrl('public/assets/css/home.css')) ?>"
    >
    <link
        rel="stylesheet"
        href="<?= e(appUrl('public/assets/css/public-pages.css')) ?>"
    >

    <style>
        .webpay-result-page {
            min-height: 100vh;
            background: #f8efe3;
        }

        .webpay-result {
            width: min(calc(100% - 2rem), 760px);
            margin: 0 auto;
            padding: clamp(4rem, 9vw, 8rem) 0;
        }

        .webpay-result__card {
            padding: clamp(1.7rem, 5vw, 3.2rem);
            border: 1px solid rgb(167 115 58 / 24%);
            border-radius: 1.6rem;
            background: #fffdf8;
            box-shadow: 0 1rem 3rem rgb(72 45 27 / 10%);
            text-align: center;
        }

        .webpay-result__status {
            display: inline-flex;
            min-height: 2.5rem;
            align-items: center;
            padding: .6rem 1rem;
            border-radius: 999px;
            color: <?= $esExito ? '#315e43' : '#824735' ?>;
            background: <?= $esExito ? '#e6f2e9' : '#fff0eb' ?>;
            font-weight: 800;
        }

        .webpay-result h1 {
            margin: 1rem 0 .7rem;
            color: #3d2b22;
            font-family: var(--serif);
            font-size: clamp(2.4rem, 7vw, 4.6rem);
            line-height: 1;
        }

        .webpay-result__message {
            max-width: 40rem;
            margin: 0 auto;
            color: #68564a;
            font-size: 1.05rem;
            line-height: 1.65;
        }

        .webpay-result dl {
            display: grid;
            gap: .8rem;
            margin: 2rem 0;
            text-align: left;
        }

        .webpay-result dl div {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: .8rem;
            border-bottom: 1px solid rgb(126 88 57 / 18%);
        }

        .webpay-result dt {
            color: #715e50;
        }

        .webpay-result dd {
            margin: 0;
            color: #3d2b22;
            font-weight: 800;
            text-align: right;
        }

        .webpay-result__actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: .75rem;
        }
    </style>
</head>
<body class="webpay-result-page">
    <?php require dirname(__DIR__) . '/includes/public-header.php'; ?>

    <main class="webpay-result">
        <section class="webpay-result__card">
            <span class="webpay-result__status">
                <?= e($esExito ? 'Pago confirmado' : 'Pago no completado') ?>
            </span>

            <h1><?= e((string) ($resultado['titulo'] ?? 'Resultado del pago')) ?></h1>

            <p class="webpay-result__message">
                <?= e((string) ($resultado['mensaje'] ?? '')) ?>
            </p>

            <dl>
                <?php if (!empty($resultado['codigo_pedido'])): ?>
                    <div>
                        <dt>Número de pedido</dt>
                        <dd><?= e($resultado['codigo_pedido']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php if (isset($resultado['monto'])): ?>
                    <div>
                        <dt>Monto</dt>
                        <dd>
                            $<?= e(number_format(
                                (int) $resultado['monto'],
                                0,
                                ',',
                                '.'
                            )) ?>
                        </dd>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resultado['authorization_code'])): ?>
                    <div>
                        <dt>Código de autorización</dt>
                        <dd><?= e($resultado['authorization_code']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resultado['payment_type'])): ?>
                    <div>
                        <dt>Tipo de pago</dt>
                        <dd><?= e($resultado['payment_type']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resultado['card_last_four'])): ?>
                    <div>
                        <dt>Tarjeta terminada en</dt>
                        <dd><?= e($resultado['card_last_four']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resultado['transaction_date'])): ?>
                    <div>
                        <dt>Fecha de transacción</dt>
                        <dd><?= e($resultado['transaction_date']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <div class="webpay-result__actions">
                <?php if ($esExito): ?>
                    <a class="button" href="<?= e(appUrl('public/catalogo.php')) ?>">
                        Volver al catálogo
                    </a>
                <?php else: ?>
                    <a class="button" href="<?= e(appUrl('public/checkout.php')) ?>">
                        Intentar nuevamente
                    </a>
                <?php endif; ?>

                <a class="button button-light" href="<?= e($whatsappUrl) ?>">
                    Hablar con Coratto
                </a>
            </div>
        </section>
    </main>

    <?php require dirname(__DIR__) . '/includes/public-footer.php'; ?>
</body>
</html>
