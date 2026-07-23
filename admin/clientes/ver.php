<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-clientes.php';
require_once __DIR__ . '/includes/consultas-clientes.php';
require_once dirname(__DIR__) . '/pedidos/includes/funciones-pedidos.php';
requireAuthentication();
$id = idClienteValido($_GET['id_cliente'] ?? null);
if ($id === null) {
    guardarModalAdmin('error', 'No fue posible abrir el cliente', 'El cliente indicado no es válido.');
    header('Location: ' . appUrl('admin/clientes/index.php'));
    exit;
}
try {
    $pdo = database();
    $client = obtenerClientePorId($pdo, $id);
    $summary = obtenerResumenCliente($pdo, $id);
    $orders = obtenerPedidosCliente($pdo, $id);
} catch (Throwable $e) {
    $ref = registrarExcepcionAdmin('Customer detail error', $e);
    guardarModalAdmin('error', 'No fue posible abrir el cliente', 'No se pudo completar la acción.', ['reference' => $ref]);
    header('Location: ' . appUrl('admin/clientes/index.php'));
    exit;
}
if ($client === null) {
    guardarModalAdmin('error', 'No fue posible abrir el cliente', 'El cliente indicado no existe.');
    header('Location: ' . appUrl('admin/clientes/index.php'));
    exit;
}
$pageTitle = (string) $client['nombre'];
$activeSection = 'clientes';
$csrfToken = csrfToken();
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php'; ?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div><a class="admin-back-link" href="<?= escape(appUrl('admin/clientes/index.php')) ?>">← Volver a clientes</a>
            <h1 class="admin-page-title">Detalle del cliente</h1>
        </div><a class="admin-button admin-button--primary"
            href="<?= escape(appUrl('admin/clientes/editar.php?id_cliente=' . $id)) ?>">Editar cliente</a>
    </header>
    <section class="admin-customer-hero">
        <div><span>Perfil comercial</span>
            <h2><?= escape((string) $client['nombre']) ?></h2>
            <p><?= escape((string) ($client['email'] ?: $client['telefono'] ?: 'Sin contacto principal')) ?></p>
        </div>
        <dl>
            <div>
                <dt>Pedidos</dt>
                <dd><?= escape((string) ($summary['cantidad_pedidos'] ?? 0)) ?></dd>
            </div>
            <div>
                <dt>Total comprado</dt>
                <dd><?= escape(formatearDineroCliente($summary['total_comprado'] ?? 0)) ?></dd>
            </div>
            <div>
                <dt>Última compra</dt>
                <dd><?= escape(formatearFechaCliente($summary['ultima_compra'] ?? null, 'd-m-Y')) ?></dd>
            </div>
        </dl>
    </section>
    <div class="admin-customer-detail-grid">
        <section class="admin-order-card">
            <header><span>01</span>
                <div>
                    <h2>Datos del cliente</h2>
                    <p>Identificación, contacto y ubicación.</p>
                </div>
            </header>
            <dl class="admin-customer-data">
                <?php foreach (['Nombre' => 'nombre', 'Email' => 'email', 'Teléfono' => 'telefono', 'RUT' => 'rut', 'Dirección' => 'direccion', 'Comuna' => 'comuna', 'Región' => 'region'] as $label => $field): ?>
                    <div>
                        <dt><?= escape($label) ?></dt>
                        <dd><?= escape((string) ($client[$field] ?: 'No informado')) ?></dd>
                    </div><?php endforeach; ?>
                <div>
                    <dt>Registrado</dt>
                    <dd><?= escape(formatearFechaCliente($client['creado_en'])) ?></dd>
                </div>
            </dl>
        </section>
        <section class="admin-order-card">
            <header><span>02</span>
                <div>
                    <h2>Resumen comercial</h2>
                    <p>Actividad acumulada desde pedidos.</p>
                </div>
            </header>
            <div class="admin-customer-metrics">
                <?php $metrics = [['Pedidos', $summary['cantidad_pedidos'] ?? 0, false], ['Total comprado', $summary['total_comprado'] ?? 0, true], ['Ticket promedio', $summary['ticket_promedio'] ?? 0, true], ['Pendientes', $summary['pedidos_pendientes'] ?? 0, false], ['Pagados', $summary['pedidos_pagados'] ?? 0, false], ['Última compra', formatearFechaCliente($summary['ultima_compra'] ?? null, 'd-m-Y'), false]];
                foreach ($metrics as $metric):
                    $l = $metric[0];
                    $v = $metric[1];
                    $money = $metric[2]; ?>
                    <article>
                        <span><?= escape((string) $l) ?></span><strong><?= escape($money ? formatearDineroCliente($v) : (string) $v) ?></strong>
                    </article><?php endforeach; ?>
            </div>
        </section>
    </div>
    <section class="admin-order-card admin-customer-orders">
        <header><span>03</span>
            <div>
                <h2>Historial de pedidos</h2>
                <p>Compras asociadas a este cliente.</p>
            </div>
        </header><?php if ($orders !== []): ?>
            <div>
            <table class="admin-data-table admin-data-table--center">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Pago</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($orders as $order): ?>
                            <tr>
                                <td data-label="Pedido"><strong><?= escape((string) $order['codigo_pedido']) ?></strong></td>
                                <td data-label="Fecha"><?= escape(formatearFechaCliente($order['creado_en'], 'd-m-Y')) ?></td>
                                <td data-label="Estado"><span
                                        class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado'])) ?>"><?= escape(etiquetaEstadoPedido((string) $order['estado'])) ?></span>
                                </td>
                                <td data-label="Pago"><span
                                        class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado_pago'], true)) ?>"><?= escape(etiquetaEstadoPagoPedido((string) $order['estado_pago'])) ?></span>
                                </td>
                                <td data-label="Total"><strong><?= escape(formatearDineroCliente($order['total'])) ?></strong>
                                </td>
                                <td><a class="admin-order-view"
                                        href="<?= escape(appUrl('admin/pedidos/ver.php?id_pedido=' . $order['id_pedido'])) ?>">Ver
                                        detalle</a></td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div><?php else: ?>
            <p class="admin-order-inline-empty">Este cliente aún no tiene pedidos asociados.</p><?php endif; ?>
    </section>
</main><?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>