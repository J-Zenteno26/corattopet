<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if (!($pdo instanceof PDO)) {
    http_response_code(503);
    renderPublicPageStart(
        'Mi cuenta | Coratto Pet',
        'Tu cuenta de cliente Coratto Pet.',
        'cuenta'
    );
    ?>
    <main id="contenido" class="customer-area">
        <section class="customer-shell">
            <div class="customer-feedback customer-feedback--error">
                No pudimos cargar tu cuenta en este momento.
            </div>
        </section>
    </main>
    <?php
    renderPublicPageEnd();
    exit;
}

$cliente = exigirClientePublico($pdo, 'public/clientes/cuenta.php');
$resumen = resumenCuentaCliente($pdo, (int) $cliente['id_cliente']);
$pedidos = pedidosCuentaCliente($pdo, (int) $cliente['id_cliente'], 4);
$ultimoPedido = $pedidos[0] ?? null;

$ubicacionCliente = trim(
    (string) ($cliente['comuna'] ?? '')
    . (($cliente['comuna'] ?? '') && ($cliente['region'] ?? '') ? ', ' : '')
    . (string) ($cliente['region'] ?? '')
);

renderPublicPageStart(
    'Mi cuenta | Coratto Pet',
    'Revisa tus pedidos y administra tus datos en Coratto Pet.',
    'cuenta'
);
?>
<main id="contenido" class="customer-area">
    <section class="customer-shell">
        <header class="customer-welcome">
            <div>
                <span>TU ESPACIO CORATTO</span>
                <h1>Hola, <?= e((string) $cliente['nombre']) ?></h1>
                <p>
                    Tus compras, tus pedidos y todo lo importante para seguir cuidando
                    a quienes más quieres, en un solo lugar.
                </p>
            </div>
            <a class="customer-secondary-action" href="<?= e(appUrl('public/catalogo.php')) ?>">
                Ir a la tienda
            </a>
        </header>

        <nav class="customer-account-nav" aria-label="Secciones de mi cuenta">
            <a class="active" href="<?= e(appUrl('public/clientes/cuenta.php')) ?>">Resumen</a>
            <a href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">Mis pedidos</a>
            <a href="<?= e(appUrl('public/clientes/fichas-alimentacion.php')) ?>">Mis fichas de alimentación</a>
            <a href="<?= e(appUrl('public/clientes/perfil.php')) ?>">Mis datos</a>
            <a href="<?= e(appUrl('public/clientes/seguridad.php')) ?>">Seguridad</a>
        </nav>

        <section class="customer-metrics" aria-label="Resumen de la cuenta">
            <article>
                <span>Pedidos</span>
                <strong><?= e((string) $resumen['pedidos']) ?></strong>
                <small>Compras asociadas a tu cuenta</small>
            </article>
            <article class="is-active">
                <span>En curso</span>
                <strong><?= e((string) $resumen['activos']) ?></strong>
                <small>Pedidos que todavía están avanzando</small>
            </article>
            <article>
                <span>Entregados</span>
                <strong><?= e((string) $resumen['entregados']) ?></strong>
                <small>Compras que ya llegaron a destino</small>
            </article>
        </section>

        <section class="customer-home-grid">
            <article class="customer-feature-order">
                <header>
                    <div>
                        <span>ÚLTIMO PEDIDO</span>
                        <h2><?= $ultimoPedido ? 'Tu compra más reciente' : 'Tu próxima compra puede empezar aquí' ?></h2>
                    </div>
                    <?php if ($ultimoPedido): ?>
                        <a href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">Ver historial</a>
                    <?php endif; ?>
                </header>

                <?php if ($ultimoPedido === null): ?>
                    <div class="customer-feature-order__empty">
                        <strong>Aún no tienes pedidos asociados.</strong>
                        <p>Explora Coratto y encuentra algo especial para tu mascota.</p>
                        <a class="customer-primary-link" href="<?= e(appUrl('public/catalogo.php')) ?>">
                            Explorar la tienda
                        </a>
                    </div>
                <?php else: ?>
                    <div class="customer-feature-order__body">
                        <div class="customer-feature-order__main">
                            <span><?= e(fechaCliente($ultimoPedido['creado_en'], 'd-m-Y')) ?></span>
                            <strong><?= e((string) $ultimoPedido['codigo_pedido']) ?></strong>
                            <div class="customer-order-statuses">
                                <span class="customer-badge customer-badge--<?= e(claseEstadoCliente((string) $ultimoPedido['estado'])) ?>">
                                    <?= e(estadoPedidoCliente((string) $ultimoPedido['estado'])) ?>
                                </span>
                                <span class="customer-badge customer-badge--payment">
                                    <?= e(estadoPagoCliente((string) $ultimoPedido['estado_pago'])) ?>
                                </span>
                            </div>
                            <p>
                                <?= $ultimoPedido['metodo_entrega'] === 'retiro_en_tienda'
                                    ? 'Retiro en tienda'
                                    : 'Despacho a domicilio' ?>
                            </p>
                        </div>

                        <div class="customer-feature-order__aside">
                            <span>Total</span>
                            <strong><?= e(dineroCliente($ultimoPedido['total'])) ?></strong>
                            <a class="customer-primary-link" href="<?= e(appUrl('public/clientes/pedido.php?id=' . (int) $ultimoPedido['id_pedido'])) ?>">
                                Ver mi pedido
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </article>

            <aside class="customer-profile-card">
                <header>
                    <span>MI INFORMACIÓN</span>
                    <h2>Datos principales</h2>
                </header>

                <dl>
                    <div>
                        <dt>Nombre</dt>
                        <dd><?= e(trim((string) $cliente['nombre'] . ' ' . (string) ($cliente['apellido'] ?? ''))) ?></dd>
                    </div>
                    <div>
                        <dt>Correo</dt>
                        <dd><?= e((string) $cliente['email']) ?></dd>
                    </div>
                    <div>
                        <dt>Teléfono</dt>
                        <dd><?= e((string) ($cliente['telefono'] ?: 'No informado')) ?></dd>
                    </div>
                    <div>
                        <dt>Ubicación</dt>
                        <dd><?= e($ubicacionCliente !== '' ? $ubicacionCliente : 'No informada') ?></dd>
                    </div>
                </dl>

                <div class="customer-profile-card__actions">
                    <a class="customer-secondary-action" href="<?= e(appUrl('public/clientes/perfil.php')) ?>">
                        Editar mis datos
                    </a>

                    <form method="post" action="<?= e(appUrl('public/clientes/logout.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <button class="customer-logout" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </aside>
        </section>

        <?php if (count($pedidos) > 1): ?>
            <section class="customer-recent-strip">
                <div>
                    <span>HISTORIAL</span>
                    <strong>También puedes revisar tus compras anteriores.</strong>
                </div>
                <a class="customer-secondary-action" href="<?= e(appUrl('public/clientes/pedidos.php')) ?>">
                    Ver todos mis pedidos
                </a>
            </section>
        <?php endif; ?>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
