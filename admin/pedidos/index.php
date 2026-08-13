<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-pedidos.php';
require_once __DIR__ . '/includes/validaciones-pedidos.php';
require_once __DIR__ . '/includes/consultas-pedidos.php';

requireAuthentication();

$filters = normalizarFiltrosPedidos($_GET);
$summary = [
    'recibidos' => 0,
    'en_preparacion' => 0,
    'pendientes_pago' => 0,
    'entregados' => 0,
    'cancelados' => 0,
];
$listing = [
    'registros' => [],
    'total_registros' => 0,
    'total_paginas' => 1,
    'pagina_actual' => 1,
    'por_pagina' => 20,
];
$databaseError = false;

try {
    $connection = database();
    $summary = obtenerResumenPedidos($connection);
    $listing = listarPedidos($connection, $filters);
} catch (Throwable $exception) {
    $databaseError = true;
    $reference = registrarExcepcionAdmin('Orders listing error', $exception);
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible cargar los pedidos',
        'message' => 'No se pudo completar la acción.',
        'reference' => $reference,
        'primaryText' => 'Aceptar',
    ];
}

$hasFilters = $filters['buscar'] !== ''
    || $filters['estado'] !== ''
    || $filters['estado_pago'] !== ''
    || $filters['fecha_desde'] !== ''
    || $filters['fecha_hasta'] !== '';

$query = array_filter(
    $filters,
    static fn (mixed $value, string $key): bool =>
        !in_array($key, ['pagina', 'por_pagina'], true) && $value !== '',
    ARRAY_FILTER_USE_BOTH
);

$pageUrl = static fn (int $page): string =>
    appUrl('admin/pedidos/index.php')
    . '?'
    . http_build_query(array_merge($query, ['pagina' => $page]));

$returnQuery = http_build_query(
    array_merge($query, ['pagina' => $listing['pagina_actual']])
);

$pageTitle = 'Pedidos';
$activeSection = 'pedidos';
$csrfToken = csrfToken();

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';

$ordersCssPath = dirname(__DIR__, 2) . '/public/css/admin-pedidos.css';
$ordersCssVersion = is_file($ordersCssPath)
    ? (string) filemtime($ordersCssPath)
    : '1';
?>
<link rel="stylesheet" href="<?= escape(appUrl('public/css/admin-pedidos.css') . '?v=' . $ordersCssVersion) ?>">

<main class="admin-main admin-orders-workspace" id="contenido-principal">
    <header class="admin-page-header admin-orders-page-header">
        <div>
            <span class="admin-orders-eyebrow">Operación comercial</span>
            <h1 class="admin-page-title admin-page-title--paw">Pedidos</h1>
            <p>
                Aquí puedes revisar las compras y hacer avanzar cada pedido sin abrir su ficha completa.
            </p>
        </div>
    </header>

    <section class="admin-order-summary" aria-label="Resumen de pedidos">
        <?php foreach ([
            ['recibidos', 'Pedidos recibidos', 'inbox'],
            ['en_preparacion', 'En preparación', 'preparing'],
            ['pendientes_pago', 'Pendientes de pago', 'payment'],
            ['entregados', 'Entregados', 'delivered'],
            ['cancelados', 'Cancelados', 'cancelled'],
        ] as [$key, $label, $class]): ?>
            <article class="admin-order-summary__card admin-order-summary__card--<?= escape($class) ?>">
                <span><?= escape($label) ?></span>
                <strong><?= escape(number_format((int) $summary[$key], 0, ',', '.')) ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="admin-orders-panel" aria-labelledby="orders-list-title">
        <header>
            <div>
                <span>Centro de gestión</span>
                <h2 id="orders-list-title">Pedidos por gestionar</h2>
            </div>
        </header>

        <form class="admin-order-filters" method="get" action="<?= escape(appUrl('admin/pedidos/index.php')) ?>">
            <div class="admin-field admin-order-filter-search">
                <label for="buscar">Código o cliente</label>
                <input
                    id="buscar"
                    name="buscar"
                    type="search"
                    maxlength="160"
                    value="<?= escape($filters['buscar']) ?>"
                    placeholder="Código, nombre o email"
                >
            </div>

            <div class="admin-field">
                <label for="estado">Estado pedido</label>
                <select id="estado" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (estadosPedido() as $value => $label): ?>
                        <option value="<?= escape($value) ?>" <?= $filters['estado'] === $value ? 'selected' : '' ?>>
                            <?= escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field">
                <label for="estado_pago">Estado pago</label>
                <select id="estado_pago" name="estado_pago">
                    <option value="">Todos</option>
                    <?php foreach (estadosPagoPedido() as $value => $label): ?>
                        <option value="<?= escape($value) ?>" <?= $filters['estado_pago'] === $value ? 'selected' : '' ?>>
                            <?= escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field">
                <label for="fecha_desde">Desde</label>
                <input id="fecha_desde" name="fecha_desde" type="date" value="<?= escape($filters['fecha_desde']) ?>">
            </div>

            <div class="admin-field">
                <label for="fecha_hasta">Hasta</label>
                <input id="fecha_hasta" name="fecha_hasta" type="date" value="<?= escape($filters['fecha_hasta']) ?>">
            </div>

            <div class="admin-order-filter-actions">
                <button class="admin-button admin-button--primary" type="submit">Aplicar filtros</button>
                <?php if ($hasFilters): ?>
                    <a class="admin-button" href="<?= escape(appUrl('admin/pedidos/index.php')) ?>">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($listing['registros'] !== []): ?>
            <div class="admin-order-table-wrap">
                <table class="admin-order-table admin-data-table">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Fecha</th>
                            <th>Cliente y contacto</th>
                            <th>Entrega</th>
                            <th>Estado del pedido</th>
                            <th>Pago</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($listing['registros'] as $order): ?>
                            <?php
                            $manageableStates = estadosGestionablesPedido($order);
                            $flowText = textoFlujoOperativoPedido($order);
                            $stateSummary = resumenEstadoOperativoPedido(
                                (string) $order['estado'],
                                (string) $order['metodo_entrega']
                            );
                            $deliveryLabel = descripcionEntregaPedido($order['metodo_entrega']);
                            if (
                                (string) $order['metodo_entrega'] === 'despacho'
                                && trim((string) ($order['comuna_entrega'] ?? '')) !== ''
                            ) {
                                $deliveryLabel .= ' · ' . trim((string) $order['comuna_entrega']);
                            }
                            ?>
                            <tr>
                                <td data-label="Pedido">
                                    <strong class="admin-order-code">
                                        <?= escape((string) $order['codigo_pedido']) ?>
                                    </strong>
                                </td>

                                <td data-label="Fecha">
                                    <time class="admin-order-date" datetime="<?= escape((string) $order['creado_en']) ?>">
                                        <strong><?= escape(formatearFechaPedido($order['creado_en'], 'd-m-Y')) ?></strong>
                                        <span><?= escape(formatearFechaPedido($order['creado_en'], 'H:i')) ?> hrs</span>
                                    </time>
                                </td>

                                <td data-label="Cliente y contacto">
                                    <span class="admin-order-customer">
                                        <strong><?= escape((string) ($order['cliente_nombre'] ?: 'Cliente no asociado')) ?></strong>
                                        <span><?= escape((string) ($order['cliente_email'] ?: 'Sin email')) ?></span>
                                        <?php if (!empty($order['cliente_telefono'])): ?>
                                            <span><?= escape((string) $order['cliente_telefono']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </td>

                                <td data-label="Entrega">
                                    <strong class="admin-order-delivery">
                                        <?= escape($deliveryLabel) ?>
                                    </strong>
                                </td>

                                <td data-label="Estado del pedido">
                                    <div class="admin-order-state-cell">
                                        <span class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado'])) ?>">
                                            <?= escape(etiquetaEstadoPedido((string) $order['estado'])) ?>
                                        </span>
                                        <p><?= escape($stateSummary) ?></p>
                                    </div>
                                </td>

                                <td data-label="Pago">
                                    <span class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado_pago'], true)) ?>">
                                        <?= escape(etiquetaEstadoPagoPedido((string) $order['estado_pago'])) ?>
                                    </span>
                                </td>

                                <td data-label="Total">
                                    <strong class="admin-order-total">
                                        <?= escape(formatearDineroPedido($order['total'])) ?>
                                    </strong>
                                </td>

                                <td data-label="Acciones" class="admin-order-table__action">
                                    <div class="admin-order-actions">
                                        <a
                                            class="admin-order-action admin-order-action--info"
                                            href="<?= escape(appUrl('admin/pedidos/ver.php?id_pedido=' . $order['id_pedido'])) ?>"
                                        >
                                            Información del pedido
                                        </a>

                                        <button
                                            class="admin-order-action admin-order-action--manage"
                                            type="button"
                                            data-order-manage
                                            data-order-id="<?= escape((string) $order['id_pedido']) ?>"
                                            data-order-code="<?= escape((string) $order['codigo_pedido']) ?>"
                                            data-order-customer="<?= escape((string) ($order['cliente_nombre'] ?: 'Cliente no asociado')) ?>"
                                            data-order-contact="<?= escape((string) ($order['cliente_email'] ?: 'Sin email')) ?>"
                                            data-order-delivery="<?= escape($deliveryLabel) ?>"
                                            data-order-state="<?= escape((string) $order['estado']) ?>"
                                            data-order-state-label="<?= escape(etiquetaEstadoPedido((string) $order['estado'])) ?>"
                                            data-order-payment="<?= escape((string) $order['estado_pago']) ?>"
                                            data-order-payment-label="<?= escape(etiquetaEstadoPagoPedido((string) $order['estado_pago'])) ?>"
                                            data-order-payment-class="<?= escape(claseEstadoPedido((string) $order['estado_pago'], true)) ?>"
                                            data-order-flow="<?= escape($flowText) ?>"
                                            data-order-summary="<?= escape($stateSummary) ?>"
                                            data-order-notes="<?= escape((string) ($order['observaciones_internas'] ?? '')) ?>"
                                            data-order-states="<?= escape(json_encode($manageableStates, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>"
                                            data-order-pickup="<?= (string) $order['metodo_entrega'] === 'retiro_en_tienda' ? '1' : '0' ?>"
                                        >
                                            Gestión de entrega
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-orders-empty">
                <span aria-hidden="true">CP</span>
                <h3>
                    <?= $databaseError
                        ? 'No fue posible cargar los pedidos'
                        : ($hasFilters ? 'No encontramos pedidos' : 'Aún no hay pedidos registrados') ?>
                </h3>
                <p>
                    <?= $databaseError
                        ? 'Intenta nuevamente más tarde.'
                        : ($hasFilters
                            ? 'Prueba con otros criterios o limpia los filtros seleccionados.'
                            : 'Las compras aparecerán aquí cuando el checkout comience a registrar pedidos.') ?>
                </p>
                <?php if ($hasFilters && !$databaseError): ?>
                    <a class="admin-button" href="<?= escape(appUrl('admin/pedidos/index.php')) ?>">Limpiar filtros</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($listing['total_paginas'] > 1): ?>
            <nav class="admin-order-pagination" aria-label="Paginación de pedidos">
                <?php if ($listing['pagina_actual'] > 1): ?>
                    <a href="<?= escape($pageUrl($listing['pagina_actual'] - 1)) ?>">← Anterior</a>
                <?php endif; ?>

                <span>
                    Página <?= escape((string) $listing['pagina_actual']) ?>
                    de <?= escape((string) $listing['total_paginas']) ?>
                </span>

                <?php if ($listing['pagina_actual'] < $listing['total_paginas']): ?>
                    <a href="<?= escape($pageUrl($listing['pagina_actual'] + 1)) ?>">Siguiente →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>

<dialog class="admin-order-manage-modal" id="order-manage-modal" aria-labelledby="order-manage-title">
    <form
        class="admin-order-manage-modal__panel"
        method="post"
        action="<?= escape(appUrl('admin/pedidos/actualizar-estado.php')) ?>"
    >
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
        <input type="hidden" name="id_pedido" id="manage-order-id" value="">
        <input type="hidden" name="return_to" value="index">
        <input type="hidden" name="return_query" value="<?= escape($returnQuery) ?>">

        <header class="admin-order-manage-modal__header">
            <div>
                <span>Gestión de entrega</span>
                <h2 id="order-manage-title">Actualizar pedido</h2>
                <p>Haz avanzar el pedido sin modificar su estado de pago.</p>
            </div>

            <button
                class="admin-order-manage-modal__close"
                type="button"
                data-order-modal-close
                aria-label="Cerrar gestión del pedido"
            >
                ×
            </button>
        </header>

        <div class="admin-order-manage-modal__body">
            <section class="admin-order-manage-summary" aria-label="Resumen del pedido">
                <div>
                    <span>Pedido</span>
                    <strong id="manage-order-code"></strong>
                </div>
                <div>
                    <span>Cliente</span>
                    <strong id="manage-order-customer"></strong>
                    <small id="manage-order-contact"></small>
                </div>
                <div>
                    <span>Entrega</span>
                    <strong id="manage-order-delivery"></strong>
                </div>
            </section>

            <section class="admin-order-manage-current">
                <div>
                    <span>Estado actual</span>
                    <strong id="manage-order-current-state"></strong>
                    <p id="manage-order-summary"></p>
                </div>

                <div>
                    <span>Estado de pago</span>
                    <div id="manage-order-payment"></div>
                    <small>Webpay gestiona este estado automáticamente.</small>
                </div>
            </section>

            <section class="admin-order-manage-guide">
                <span>Flujo de este pedido</span>
                <strong id="manage-order-flow"></strong>
                <p id="manage-order-notification-note" hidden>
                    Al marcar “Listo para retiro”, el cliente recibirá automáticamente la notificación por correo.
                </p>
            </section>

            <div class="admin-field">
                <label for="manage-order-state">Nuevo estado</label>
                <select id="manage-order-state" name="estado" required></select>
            </div>

            <div class="admin-field">
                <label for="manage-order-notes">Observaciones internas</label>
                <textarea
                    id="manage-order-notes"
                    name="observaciones_internas"
                    maxlength="1000"
                    rows="4"
                    placeholder="Notas internas para el equipo de Coratto"
                ></textarea>
            </div>
        </div>

        <footer class="admin-order-manage-modal__footer">
            <button class="admin-button" type="button" data-order-modal-close>Cancelar</button>
            <button class="admin-button admin-order-manage-modal__submit" type="submit">
                Guardar estado
            </button>
        </footer>
    </form>
</dialog>

<script>
(() => {
    const dialog = document.getElementById('order-manage-modal');

    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    const orderId = document.getElementById('manage-order-id');
    const code = document.getElementById('manage-order-code');
    const customer = document.getElementById('manage-order-customer');
    const contact = document.getElementById('manage-order-contact');
    const delivery = document.getElementById('manage-order-delivery');
    const currentState = document.getElementById('manage-order-current-state');
    const stateSummary = document.getElementById('manage-order-summary');
    const payment = document.getElementById('manage-order-payment');
    const flow = document.getElementById('manage-order-flow');
    const notificationNote = document.getElementById('manage-order-notification-note');
    const stateSelect = document.getElementById('manage-order-state');
    const notes = document.getElementById('manage-order-notes');

    document.querySelectorAll('[data-order-manage]').forEach((button) => {
        button.addEventListener('click', () => {
            orderId.value = button.dataset.orderId || '';
            code.textContent = button.dataset.orderCode || '';
            customer.textContent = button.dataset.orderCustomer || '';
            contact.textContent = button.dataset.orderContact || '';
            delivery.textContent = button.dataset.orderDelivery || '';
            currentState.textContent = button.dataset.orderStateLabel || '';
            stateSummary.textContent = button.dataset.orderSummary || '';
            flow.textContent = button.dataset.orderFlow || '';
            notes.value = button.dataset.orderNotes || '';

            payment.innerHTML = '';

            const paymentBadge = document.createElement('span');
            paymentBadge.className = `admin-order-badge ${button.dataset.orderPaymentClass || ''}`;
            paymentBadge.textContent = button.dataset.orderPaymentLabel || '';
            payment.appendChild(paymentBadge);

            stateSelect.innerHTML = '';

            let states = {};
            try {
                states = JSON.parse(button.dataset.orderStates || '{}');
            } catch (_) {
                states = {};
            }

            Object.entries(states).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                option.selected = value === button.dataset.orderState;
                stateSelect.appendChild(option);
            });

            notificationNote.hidden = button.dataset.orderPickup !== '1';

            dialog.showModal();
        });
    });

    dialog.querySelectorAll('[data-order-modal-close]').forEach((button) => {
        button.addEventListener('click', () => dialog.close());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
})();
</script>

<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
