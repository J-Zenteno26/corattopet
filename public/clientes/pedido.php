<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit('Servicio temporalmente no disponible.');
}

$idPedido = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$cliente = exigirClientePublico(
    $pdo,
    'public/clientes/pedidos.php'
);

$pedido = $idPedido === false
    ? null
    : pedidoCuentaCliente($pdo, (int) $cliente['id_cliente'], (int) $idPedido);

if ($pedido === null) {
    http_response_code(404);
    renderPublicPageStart(
        'Pedido no encontrado | Coratto Pet',
        'No encontramos el pedido solicitado.',
        'cuenta'
    );
    ?>
    <main id="contenido" class="customer-area">
        <section class="customer-shell">
            <div class="customer-empty customer-empty--standalone">
                <strong>No encontramos ese pedido.</strong>
                <p>Puede que no pertenezca a tu cuenta o que el enlace no sea válido.</p>
                <a class="button" href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">Volver a mis pedidos</a>
            </div>
        </section>
    </main>
    <?php
    renderPublicPageEnd();
    exit;
}

$detalles = detallesPedidoCuentaCliente($pdo, (int) $pedido['id_pedido']);

renderPublicPageStart(
    'Pedido ' . (string) $pedido['codigo_pedido'] . ' | Coratto Pet',
    'Detalle de tu pedido Coratto Pet.',
    'cuenta'
);
?>
<main id="contenido" class="customer-area">
    <section class="customer-shell">
        <header class="customer-order-heading">
            <div>
                <a class="customer-back" href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">← Mis pedidos</a>
                <span>DETALLE DEL PEDIDO</span>
                <h1><?= e((string) $pedido['codigo_pedido']) ?></h1>
                <p>Realizado el <?= e(fechaCliente($pedido['creado_en'])) ?></p>
            </div>
            <strong><?= e(dineroCliente($pedido['total'])) ?></strong>
        </header>

        <section class="customer-order-state">
            <div>
                <span>Estado del pedido</span>
                <strong class="customer-badge customer-badge--<?= e(claseEstadoCliente((string) $pedido['estado'])) ?>">
                    <?= e(estadoPedidoCliente((string) $pedido['estado'])) ?>
                </strong>
            </div>
            <div>
                <span>Estado del pago</span>
                <strong><?= e(estadoPagoCliente((string) $pedido['estado_pago'])) ?></strong>
            </div>
            <div>
                <span>Modalidad</span>
                <strong>
                    <?= $pedido['metodo_entrega'] === 'retiro_en_tienda'
                        ? 'Retiro en tienda'
                        : 'Despacho a domicilio' ?>
                </strong>
            </div>
        </section>

        <div class="customer-order-detail-grid">
            <section class="customer-panel">
                <header>
                    <div>
                        <span>PRODUCTOS</span>
                        <h2>Tu compra</h2>
                    </div>
                </header>

                <div class="customer-order-products">
                    <?php foreach ($detalles as $detalle): ?>
                        <article>
                            <div>
                                <strong><?= e((string) $detalle['nombre_producto']) ?></strong>
                                <?php if (!empty($detalle['sku'])): ?>
                                    <small>SKU <?= e((string) $detalle['sku']) ?></small>
                                <?php endif; ?>
                                <?php if ((int) ($detalle['cantidad_gramos'] ?? 0) > 0): ?>
                                    <small>
                                        Presentación:
                                        <?= e(number_format((int) $detalle['cantidad_gramos'], 0, ',', '.')) ?> g
                                    </small>
                                <?php endif; ?>
                            </div>
                            <span><?= e((string) $detalle['cantidad']) ?> un.</span>
                            <strong><?= e(dineroCliente($detalle['subtotal'])) ?></strong>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="customer-panel customer-order-summary-panel">
                <header>
                    <div>
                        <span>ENTREGA</span>
                        <h2>Información del pedido</h2>
                    </div>
                </header>

                <dl>
                    <div>
                        <dt>Contacto</dt>
                        <dd><?= e((string) ($pedido['nombre_cliente'] ?: $cliente['nombre'])) ?></dd>
                    </div>
                    <div>
                        <dt>Correo</dt>
                        <dd><?= e((string) ($pedido['email_cliente'] ?: $cliente['email'])) ?></dd>
                    </div>
                    <div>
                        <dt>Teléfono</dt>
                        <dd><?= e((string) ($pedido['telefono_cliente'] ?: 'No informado')) ?></dd>
                    </div>

                    <?php if ($pedido['metodo_entrega'] === 'despacho'): ?>
                        <div>
                            <dt>Dirección</dt>
                            <dd><?= e((string) ($pedido['direccion_entrega'] ?: 'No informada')) ?></dd>
                        </div>
                        <div>
                            <dt>Comuna</dt>
                            <dd>
                                <?= e(trim(
                                    (string) $pedido['comuna_entrega']
                                    . ($pedido['region_entrega'] ? ', ' . (string) $pedido['region_entrega'] : '')
                                )) ?>
                            </dd>
                        </div>
                    <?php else: ?>
                        <div>
                            <dt>Retiro</dt>
                            <dd>Espera nuestra confirmación antes de acercarte a la tienda.</dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <div class="customer-order-totals">
                    <div>
                        <span>Subtotal</span>
                        <strong><?= e(dineroCliente($pedido['subtotal'])) ?></strong>
                    </div>
                    <div>
                        <span>Despacho</span>
                        <strong>
                            <?= (int) $pedido['costo_despacho'] > 0
                                ? e(dineroCliente($pedido['costo_despacho']))
                                : 'Sin costo' ?>
                        </strong>
                    </div>
                    <div class="is-total">
                        <span>Total</span>
                        <strong><?= e(dineroCliente($pedido['total'])) ?></strong>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
