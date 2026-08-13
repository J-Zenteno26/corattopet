<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/shared/seguridad.php';
require_once dirname(__DIR__) . '/shared/funciones-carrito.php';
require_once dirname(__DIR__) . '/shared/funciones-checkout.php';
require_once __DIR__ . '/includes/consultas-publicas.php';
require_once __DIR__ . '/clientes/includes/funciones-clientes-publicos.php';

$config = [];
$regiones = [];
$comunas = [];
$resumen = [
    'items' => [],
    'subtotal' => 0,
    'peso_total_gramos' => 0,
    'peso_tarifable_gramos' => 0,
    'errores' => [],
    'valido' => false,
];
$databaseError = false;
$checkoutClient = null;

try {
    $pdo = database();
    $config = obtenerConfiguracionPublica($pdo);
    $regiones = obtenerRegionesCheckout($pdo);
    $comunas = obtenerComunasCheckout($pdo);
    $resumen = obtenerResumenCheckout(
        $pdo,
        !valorBooleanoCheckout($config['permite_retiro'] ?? false)
    );
    $checkoutClient = clientePublicoSesion($pdo);
} catch (Throwable $exception) {
    error_log('Error al cargar el checkout público: ' . $exception->getMessage());
    $databaseError = true;
    http_response_code(503);
}

if (!$databaseError && $resumen['items'] === []) {
    header('Location: ' . appUrl('public/carrito.php'));
    exit;
}

$currentPage = 'carrito';
$whatsappUrl = obtenerWhatsappPublico($config);
$csrfToken = csrfToken();
$totalUnits = contarUnidadesCarritoSesion();
$modalidadesEntrega = modalidadesEntregaCheckout($config);
$permiteDespacho = isset($modalidadesEntrega['despacho']);
$permiteRetiro = isset($modalidadesEntrega['retiro_en_tienda']);
$metodoEntregaInicial = $permiteDespacho ? 'despacho' : ($permiteRetiro ? 'retiro_en_tienda' : '');

$montoMinimoDespacho = max(
    0,
    (int) ($config['monto_minimo_despacho'] ?? 12000)
);

$montoMinimoRetiro = max(
    0,
    (int) ($config['monto_minimo_retiro'] ?? 4000)
);

$clienteNombreCheckout = '';
$clienteEmailCheckout = '';
$clienteTelefonoCheckout = '';
$clienteDireccionCheckout = '';
$clienteRegionIdCheckout = '';
$clienteComunaIdCheckout = '';

if (is_array($checkoutClient)) {
    $clienteNombreCheckout = trim(
        (string) $checkoutClient['nombre']
        . ' '
        . (string) ($checkoutClient['apellido'] ?? '')
    );
    $clienteEmailCheckout = (string) ($checkoutClient['email'] ?? '');
    $clienteTelefonoCheckout = (string) ($checkoutClient['telefono'] ?? '');
    $clienteDireccionCheckout = (string) ($checkoutClient['direccion'] ?? '');

    foreach ($regiones as $region) {
        if (
            mb_strtolower(trim((string) $region['nombre']))
            === mb_strtolower(trim((string) ($checkoutClient['region'] ?? '')))
        ) {
            $clienteRegionIdCheckout = (string) $region['id_region'];
            break;
        }
    }

    if ($clienteRegionIdCheckout !== '') {
        foreach ($comunas as $comuna) {
            if (
                (string) $comuna['id_region'] === $clienteRegionIdCheckout
                && mb_strtolower(trim((string) $comuna['nombre']))
                    === mb_strtolower(trim((string) ($checkoutClient['comuna'] ?? '')))
            ) {
                $clienteComunaIdCheckout = (string) $comuna['id_comuna'];
                break;
            }
        }
    }
}


if (
    !isset($_SESSION['checkout_token'])
    || !is_string($_SESSION['checkout_token'])
    || $_SESSION['checkout_token'] === ''
) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(24));
}

$checkoutToken = $_SESSION['checkout_token'];

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta
        name="description"
        content="Completa tus datos y elige cómo recibir tu compra Coratto Pet."
    >
    <title>Entrega de tu pedido | Coratto Pet</title>

    <link
        rel="stylesheet"
        href="<?= e(appUrl('public/assets/css/home.css')) ?>?v=<?= filemtime(__DIR__ . '/assets/css/home.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= e(appUrl('public/assets/css/public-pages.css')) ?>?v=<?= filemtime(__DIR__ . '/assets/css/public-pages.css') ?>"
    >
    <link
        rel="stylesheet"
        href="<?= e(appUrl('public/assets/css/checkout.css')) ?>?v=<?= filemtime(__DIR__ . '/assets/css/checkout.css') ?>"
    >
</head>

<body class="checkout-page">
    <?php require __DIR__ . '/includes/public-header.php'; ?>

    <main id="contenido">
        <section class="checkout-shell">
            <header class="checkout-heading">
                <span>Tu compra Coratto</span>
                <h1>Entrega de tu pedido</h1>
                <p>
                    Completa tus datos y elige entre despacho a domicilio o
                    retiro en tienda, según las opciones disponibles.
                </p>
            </header>

            <?php if ($databaseError): ?>
                <section class="checkout-state">
                    <span>Checkout temporalmente no disponible</span>
                    <h2>No pudimos cargar los datos de entrega</h2>
                    <p>Inténtalo nuevamente en unos minutos.</p>
                    <a class="button" href="<?= e($whatsappUrl) ?>">
                        Hablar con Coratto
                    </a>
                </section>
            <?php else: ?>
                <div class="checkout-layout">
                    <section class="checkout-content">
                        <aside class="checkout-account-note">
                            <?php if (is_array($checkoutClient)): ?>
                                <div>
                                    <span>Compra asociada a tu cuenta</span>
                                    <strong>
                                        Hola, <?= e((string) $checkoutClient['nombre']) ?>.
                                        Completamos tus datos guardados para avanzar más rápido.
                                    </strong>
                                </div>
                                <a class="checkout-account-button" href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">
                                    Mi cuenta
                                </a>
                            <?php else: ?>
                                <div>
                                    <span>¿Ya tienes una cuenta?</span>
                                    <strong>
                                        Puedes iniciar sesión ahora. Tu carrito se mantendrá intacto.
                                    </strong>
                                </div>
                                <a
                                    class="checkout-account-button"
                                    href="<?= e(appUrl('public/clientes/login.php?return=' . rawurlencode('public/checkout.php'))) ?>"
                                >
                                    Iniciar sesión
                                </a>
                            <?php endif; ?>
                        </aside>

                        <?php if ($resumen['errores'] !== []): ?>
                            <div class="checkout-feedback checkout-feedback--error" role="alert">
                                <strong>Revisa tu carrito antes de continuar:</strong>
                                <ul>
                                    <?php foreach ($resumen['errores'] as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form
                            class="checkout-form"
                            id="checkout-form"
                            action="<?= e(appUrl('public/acciones-checkout/calcular-despacho.php')) ?>"
                            data-create-order-url="<?= e(appUrl('public/acciones-checkout/crear-pedido.php')) ?>"
                            method="post"
                            data-initial-delivery-method="<?= e($metodoEntregaInicial) ?>"
                            data-subtotal="<?= e((int) $resumen['subtotal']) ?>"
                            data-subtotal-formatted="<?= e(formatearDineroCheckout($resumen['subtotal'])) ?>"
                            data-minimum-shipping="<?= e($montoMinimoDespacho) ?>"
                            data-minimum-pickup="<?= e($montoMinimoRetiro) ?>"
                            data-checkout-valid="<?= $resumen['valido'] ? '1' : '0' ?>"
                            novalidate
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($csrfToken) ?>"
                            >

                            <input
                                type="hidden"
                                name="checkout_token"
                                value="<?= e($checkoutToken) ?>"
                            >

                            <section class="checkout-card">
                                <header>
                                    <span>01</span>
                                    <div>
                                        <h2>Datos de contacto</h2>
                                        <p>Los usaremos para coordinar tu pedido.</p>
                                    </div>
                                </header>

                                <div class="checkout-form-grid">
                                    <div class="checkout-field checkout-field--wide">
                                        <label for="nombre">Nombre y apellido</label>
                                        <input
                                            id="nombre"
                                            name="nombre"
                                            type="text"
                                            maxlength="120"
                                            autocomplete="name"
                                            required
                                            value="<?= e($clienteNombreCheckout) ?>"
                                        >
                                    </div>

                                    <div class="checkout-field">
                                        <label for="email">Correo electrónico</label>
                                        <input
                                            id="email"
                                            name="email"
                                            type="email"
                                            maxlength="160"
                                            autocomplete="email"
                                            required
                                            value="<?= e($clienteEmailCheckout) ?>"
                                        >
                                    </div>

                                    <div class="checkout-field">
                                        <label for="telefono">Teléfono</label>
                                        <input
                                            id="telefono"
                                            name="telefono"
                                            type="tel"
                                            maxlength="30"
                                            autocomplete="tel"
                                            placeholder="+56 9 1234 5678"
                                            required
                                            value="<?= e($clienteTelefonoCheckout) ?>"
                                        >
                                    </div>
                                </div>
                            </section>

                            <section class="checkout-card">
                                <header>
                                    <span>02</span>
                                    <div>
                                        <h2>Modalidad de entrega</h2>
                                        <p>Selecciona cómo quieres recibir tu pedido.</p>
                                    </div>
                                </header>

                                <?php if ($modalidadesEntrega === []): ?>
                                    <div class="checkout-feedback checkout-feedback--error">
                                        No hay modalidades de entrega habilitadas actualmente.
                                    </div>
                                <?php else: ?>
                                    <div class="checkout-delivery-options">
                                        <?php if ($permiteDespacho): ?>
                                            <label class="checkout-delivery-option">
                                                <input
                                                    type="radio"
                                                    name="metodo_entrega"
                                                    value="despacho"
                                                    <?= $metodoEntregaInicial === 'despacho' ? 'checked' : '' ?>
                                                    required
                                                >
                                                <span>
                                                    <strong>Despacho a domicilio</strong>
                                                        <small>
                                                            Compra mínima:
                                                            <?= e(formatearDineroCheckout($montoMinimoDespacho)) ?>.
                                                            Calcularemos el valor según tu comuna y el peso del pedido.
                                                        </small>
                                                </span>
                                            </label>
                                        <?php endif; ?>

                                        <?php if ($permiteRetiro): ?>
                                            <label class="checkout-delivery-option">
                                                <input
                                                    type="radio"
                                                    name="metodo_entrega"
                                                    value="retiro_en_tienda"
                                                    <?= $metodoEntregaInicial === 'retiro_en_tienda' ? 'checked' : '' ?>
                                                    required
                                                >
                                                <span>
                                                    <strong>Retiro en tienda</strong>
                                                        <small>
                                                            Compra mínima:
                                                            <?= e(formatearDineroCheckout($montoMinimoRetiro)) ?>.
                                                            Sin costo de despacho. Te avisaremos cuando tu pedido esté listo.
                                                        </small>
                                                </span>
                                            </label>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($permiteRetiro): ?>
                                        <div class="checkout-pickup-note" data-pickup-note hidden>
                                            <strong>Espera nuestra confirmación antes de acercarte.</strong>
                                            <span>
                                                Tu pedido será recepcionado y preparado por Coratto. Te avisaremos cuando quede listo para retiro.
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </section>

                            <section
                                class="checkout-card"
                                data-delivery-section="despacho"
                                <?= $metodoEntregaInicial !== 'despacho' ? 'hidden' : '' ?>
                            >
                                <header>
                                    <span>03</span>
                                    <div>
                                        <h2>Dirección de despacho</h2>
                                        <p>Selecciona región y comuna para calcular la tarifa.</p>
                                    </div>
                                </header>

                                <div class="checkout-form-grid">
                                    <div class="checkout-field">
                                        <label for="id_region">Región</label>
                                        <select id="id_region" name="id_region" required>
                                            <option value="">Selecciona una región</option>
                                            <?php foreach ($regiones as $region): ?>
                                                <option
                                                    value="<?= e($region['id_region']) ?>"
                                                    <?= $clienteRegionIdCheckout === (string) $region['id_region'] ? 'selected' : '' ?>
                                                >
                                                    <?= e($region['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="checkout-field">
                                        <label for="id_comuna">Comuna</label>
                                        <select
                                            id="id_comuna"
                                            name="id_comuna"
                                            required
                                            <?= $clienteRegionIdCheckout === '' ? 'disabled' : '' ?>
                                        >
                                            <option value="">Selecciona una comuna</option>
                                            <?php foreach ($comunas as $comuna): ?>
                                                <option
                                                    value="<?= e($comuna['id_comuna']) ?>"
                                                    data-region="<?= e($comuna['id_region']) ?>"
                                                    <?= $clienteComunaIdCheckout === (string) $comuna['id_comuna'] ? 'selected' : '' ?>
                                                    <?= $clienteRegionIdCheckout === (string) $comuna['id_region'] ? '' : 'hidden disabled' ?>
                                                >
                                                    <?= e($comuna['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="checkout-field checkout-field--wide">
                                        <label for="direccion">Dirección</label>
                                        <input
                                            id="direccion"
                                            name="direccion"
                                            type="text"
                                            maxlength="180"
                                            autocomplete="street-address"
                                            placeholder="Calle, número, departamento o casa"
                                            required
                                            value="<?= e($clienteDireccionCheckout) ?>"
                                        >
                                    </div>

                                    <div class="checkout-field checkout-field--wide">
                                        <label for="referencia">
                                            Referencia <span>Opcional</span>
                                        </label>
                                        <input
                                            id="referencia"
                                            name="referencia"
                                            type="text"
                                            maxlength="180"
                                            placeholder="Ej.: portón negro, casa esquina"
                                        >
                                    </div>

                                </div>
                            </section>

                            <section class="checkout-card">
                                <header>
                                    <span>04</span>
                                    <div>
                                        <h2>Observaciones</h2>
                                        <p>Cuéntanos si debemos considerar algo al preparar tu pedido.</p>
                                    </div>
                                </header>

                                <div class="checkout-form-grid">
                                    <div class="checkout-field checkout-field--wide">
                                        <label for="observaciones">
                                            Observaciones <span>Opcional</span>
                                        </label>
                                        <textarea
                                            id="observaciones"
                                            name="observaciones"
                                            rows="3"
                                            maxlength="500"
                                            placeholder="Información adicional para tu pedido"
                                        ></textarea>
                                    </div>
                                </div>
                            </section>

                            <div
                                class="checkout-feedback"
                                id="checkout-feedback"
                                hidden
                                role="status"
                            ></div>

                            <button
                                class="button checkout-calculate-button"
                                type="submit"
                                data-calculate-shipping
                                <?= !$resumen['valido'] ? 'disabled' : '' ?>
                                <?= $metodoEntregaInicial !== 'despacho' ? 'hidden' : '' ?>
                            >
                                Calcular despacho
                            </button>
                        </form>
                    </section>

                    <aside class="checkout-summary">
                        <span>Resumen</span>
                        <h2>Tu pedido</h2>

                        <dl>
                            <div>
                                <dt>Productos</dt>
                                <dd>
                                    <?= e($totalUnits) ?>
                                    unidad<?= $totalUnits === 1 ? '' : 'es' ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Subtotal</dt>
                                <dd id="checkout-subtotal">
                                    <?= e(formatearDineroCheckout($resumen['subtotal'])) ?>
                                </dd>
                            </div>

                            <div>
                                <dt id="checkout-delivery-cost-label">Despacho</dt>
                                <dd id="checkout-shipping-cost">
                                    <?= $metodoEntregaInicial === 'retiro_en_tienda' ? 'Sin costo' : 'Por calcular' ?>
                                </dd>
                            </div>
                        </dl>

                        <div class="checkout-summary__total">
                            <span>Total</span>
                            <strong id="checkout-total">
                                <?= e(formatearDineroCheckout($resumen['subtotal'])) ?>
                            </strong>
                        </div>

                        <div class="checkout-weight" data-checkout-weight <?= $metodoEntregaInicial !== 'despacho' ? 'hidden' : '' ?>>
                            <span>Peso estimado del pedido</span>
                            <strong>
                                <?= e(number_format(
                                    (int) $resumen['peso_tarifable_gramos'],
                                    0,
                                    ',',
                                    '.'
                                )) ?> g
                            </strong>
                            <small>Incluye 10% de margen para embalaje.</small>
                        </div>

                        <p class="checkout-summary__notice">
                            Al continuar, puedes revisar nuestros
                            <a href="<?= e(appUrl('public/terminos-condiciones.php')) ?>">Términos y Condiciones</a>
                            y la
                            <a href="<?= e(appUrl('public/politica-privacidad.php')) ?>">Política de Privacidad</a>.
                        </p>

                        <button
                            class="button checkout-pay-button"
                            id="checkout-pay-button"
                            type="button"
                            <?= (!$resumen['valido'] || $metodoEntregaInicial === '' || $metodoEntregaInicial === 'despacho') ? 'disabled' : '' ?>
                        >
                            Continuar al pago
                        </button>

                        <p class="checkout-summary__notice" id="checkout-delivery-notice">
                            <?php if ($metodoEntregaInicial === 'retiro_en_tienda'): ?>
                                Tras confirmar el pago, prepararemos tu pedido y te avisaremos cuando esté listo para retiro.
                            <?php else: ?>
                                El pedido quedará registrado como pendiente hasta confirmar el pago.
                            <?php endif; ?>
                        </p>

                        <a
                            class="checkout-summary__back"
                            href="<?= e(appUrl('public/carrito.php')) ?>"
                        >
                            Volver al carrito
                        </a>
                    </aside>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require __DIR__ . '/includes/public-footer.php'; ?>

    <script
        src="<?= e(appUrl('public/assets/js/public-navigation.js')) ?>?v=<?= filemtime(__DIR__ . '/assets/js/public-navigation.js') ?>"
        defer
    ></script>
    <script
        src="<?= e(appUrl('public/assets/js/checkout.js')) ?>?v=<?= filemtime(__DIR__ . '/assets/js/checkout.js') ?>"
        defer
    ></script>
</body>

</html>
