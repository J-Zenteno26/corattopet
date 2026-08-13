<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit('Servicio temporalmente no disponible.');
}

$cliente = exigirClientePublico($pdo, 'public/clientes/pedidos.php');
$pedidos = pedidosCuentaCliente($pdo, (int) $cliente['id_cliente']);

renderPublicPageStart(
    'Mis pedidos | Coratto Pet',
    'Revisa el estado de tus pedidos Coratto Pet.',
    'cuenta'
);
?>
<main id="contenido" class="customer-area">
    <section class="customer-shell">
        <header class="customer-section-heading">
            <div>
                <a class="customer-back" href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">← Mi cuenta</a>
                <span>MIS COMPRAS</span>
                <h1>Mis pedidos</h1>
                <p>
                    Tu historial Coratto, ordenado para que puedas encontrar rápidamente
                    cada compra y saber en qué etapa se encuentra.
                </p>
            </div>
        </header>

        <nav class="customer-account-nav" aria-label="Secciones de mi cuenta">
            <a href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">Resumen</a>
            <a class="active" href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">Mis pedidos</a>
            <a href="<?= e(appUrl('public/clientes/fichas-alimentacion.php')) ?>">Mis fichas de alimentación</a>
            <a href="<?= e(appUrl('public/clientes/perfil.php')) ?>">Mis datos</a>
            <a href="<?= e(appUrl('public/clientes/seguridad.php')) ?>">Seguridad</a>
        </nav>

        <?php if ($pedidos === []): ?>
            <section class="customer-empty customer-empty--standalone">
                <strong>Aún no tienes pedidos.</strong>
                <p>Cuando compres en Coratto, tus pedidos aparecerán aquí.</p>
                <a class="customer-primary-link" href="<?= e(appUrl('public/catalogo.php')) ?>">
                    Explorar la tienda
                </a>
            </section>
        <?php else: ?>
            <section class="customer-orders-history">
                <header>
                    <div>
                        <span>HISTORIAL DE COMPRAS</span>
                        <h2><?= e((string) count($pedidos)) ?> pedido<?= count($pedidos) === 1 ? '' : 's' ?></h2>
                    </div>
                </header>

                <div class="customer-orders-table-wrap">
                    <table class="customer-orders-table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Fecha</th>
                                <th>Entrega</th>
                                <th>Estado</th>
                                <th>Pago</th>
                                <th>Total</th>
                                <th aria-label="Acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td>
                                        <strong><?= e((string) $pedido['codigo_pedido']) ?></strong>
                                    </td>
                                    <td><?= e(fechaCliente($pedido['creado_en'], 'd-m-Y')) ?></td>
                                    <td>
                                        <?= $pedido['metodo_entrega'] === 'retiro_en_tienda'
                                            ? 'Retiro en tienda'
                                            : 'Despacho a domicilio' ?>
                                    </td>
                                    <td>
                                        <span class="customer-badge customer-badge--<?= e(claseEstadoCliente((string) $pedido['estado'])) ?>">
                                            <?= e(estadoPedidoCliente((string) $pedido['estado'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="customer-badge customer-badge--payment">
                                            <?= e(estadoPagoCliente((string) $pedido['estado_pago'])) ?>
                                        </span>
                                    </td>
                                    <td class="customer-orders-table__total">
                                        <?= e(dineroCliente($pedido['total'])) ?>
                                    </td>
                                    <td>
                                        <a class="customer-table-action" href="<?= e(appUrl('public/clientes/pedido.php?id=' . (int) $pedido['id_pedido'])) ?>">
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="customer-orders-mobile">
                    <?php foreach ($pedidos as $pedido): ?>
                        <details class="customer-order-accordion">
                            <summary>
                                <div>
                                    <span><?= e(fechaCliente($pedido['creado_en'], 'd-m-Y')) ?></span>
                                    <strong><?= e((string) $pedido['codigo_pedido']) ?></strong>
                                </div>
                                <div>
                                    <strong><?= e(dineroCliente($pedido['total'])) ?></strong>
                                    <span>Ver detalle</span>
                                </div>
                            </summary>

                            <div class="customer-order-accordion__content">
                                <dl>
                                    <div>
                                        <dt>Estado</dt>
                                        <dd>
                                            <span class="customer-badge customer-badge--<?= e(claseEstadoCliente((string) $pedido['estado'])) ?>">
                                                <?= e(estadoPedidoCliente((string) $pedido['estado'])) ?>
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Pago</dt>
                                        <dd><?= e(estadoPagoCliente((string) $pedido['estado_pago'])) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Entrega</dt>
                                        <dd>
                                            <?= $pedido['metodo_entrega'] === 'retiro_en_tienda'
                                                ? 'Retiro en tienda'
                                                : 'Despacho a domicilio' ?>
                                        </dd>
                                    </div>
                                </dl>

                                <a class="customer-primary-link" href="<?= e(appUrl('public/clientes/pedido.php?id=' . (int) $pedido['id_pedido'])) ?>">
                                    Abrir pedido completo
                                </a>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
