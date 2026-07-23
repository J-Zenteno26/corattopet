<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-stock-fraccionado.php';
require_once dirname(__DIR__) . '/pedidos/includes/funciones-pedidos.php';
require_once dirname(__DIR__) . '/clientes/includes/funciones-clientes.php';
require_once __DIR__ . '/includes/consultas-dashboard.php';
requireAuthentication();
$metrics = ['ventas_mes' => 0, 'pedidos_mes' => 0, 'pedidos_pendientes' => 0, 'pagos_pendientes' => 0, 'clientes_registrados' => 0, 'productos_activos' => 0, 'stock_bajo' => 0, 'sin_stock' => 0];
$orders = [];
$alerts = [];
$clients = [];
$pending = ['sin_imagen' => 0, 'sin_sku' => 0, 'sin_presentaciones' => 0, 'sin_stock' => 0];
$settings = ['nombre_tienda' => 'Coratto Pet', 'descripcion_breve' => 'Centro de control de la tienda.', 'email_contacto' => '', 'whatsapp_principal' => '', 'moneda' => 'CLP', 'modo_tienda' => 'activa', 'permite_despacho' => false, 'permite_retiro' => true, 'permitir_venta_sin_stock' => false, 'mostrar_stock' => true];
$databaseError = false;
try {
    $pdo = database();
    $metrics = array_merge($metrics, obtenerResumenDashboard($pdo));
    $orders = obtenerPedidosRecientesDashboard($pdo);
    $alerts = obtenerAlertasStockDashboard($pdo);
    $clients = obtenerClientesRecientesDashboard($pdo);
    $pending = array_merge($pending, obtenerPendientesCatalogoDashboard($pdo));
    $settings = array_merge($settings, obtenerConfiguracionDashboard($pdo));
} catch (Throwable $e) {
    $databaseError = true;
    $ref = registrarExcepcionAdmin('Dashboard load error', $e);
    $adminModal = ['type' => 'error', 'title' => 'No fue posible cargar el dashboard', 'message' => 'No se pudo completar la acción.', 'reference' => $ref, 'primaryText' => 'Aceptar'];
}
$bool = static fn(mixed $v): bool => in_array($v, [true, 1, '1', 't', 'true'], true);
$pageTitle = 'Dashboard';
$activeSection = 'dashboard';
$csrfToken = csrfToken();
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php'; ?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header admin-dashboard-heading">
        <div><span>Centro de control</span>
            <h1 class="admin-page-title admin-page-title--paw">Dashboard</h1>
            <p>Hola, <?= escape((string) $_SESSION['nombre']) ?>. Este es el estado general de Coratto Pet.</p>
        </div>
    </header>
    <section class="admin-dashboard-hero">
        <div><span>Identidad de tienda</span>
            <h2><?= escape((string) $settings['nombre_tienda']) ?></h2>
            <p><?= escape((string) ($settings['descripcion_breve'] ?: 'Resumen operativo y comercial de la tienda.')) ?>
            </p>
        </div>
        <dl>
            <div>
                <dt>Modo</dt>
                <dd class="admin-dashboard-mode admin-dashboard-mode--<?= escape((string) $settings['modo_tienda']) ?>">
                    <?= escape(ucfirst((string) $settings['modo_tienda'])) ?>
                </dd>
            </div>
            <div>
                <dt>Contacto</dt>
                <dd><?= escape((string) ($settings['email_contacto'] ?: $settings['whatsapp_principal'] ?: 'Por definir')) ?>
                </dd>
            </div>
            <div>
                <dt>Moneda</dt>
                <dd><?= escape((string) $settings['moneda']) ?></dd>
            </div>
        </dl>
    </section>
    <section class="admin-dashboard-metrics" aria-label="Métricas principales">
        <?php foreach ([['ventas_mes', 'Ventas del mes', true, 'Pagos confirmados'], ['pedidos_mes', 'Pedidos del mes', false, 'Compras registradas'], ['pedidos_pendientes', 'Pedidos pendientes', false, 'En gestión'], ['pagos_pendientes', 'Pagos pendientes', false, 'Por confirmar'], ['clientes_registrados', 'Clientes registrados', false, 'Base comercial'], ['productos_activos', 'Productos disponibles', false, 'Catálogo vigente'], ['stock_bajo', 'Stock bajo', false, 'Requieren atención'], ['sin_stock', 'Sin stock', false, 'No disponibles']] as [$key, $label, $money, $detail]): ?>
            <article class="admin-dashboard-metric admin-dashboard-metric--<?= escape($key) ?>">
                <span><?= escape($label) ?></span><strong><?= escape($money ? formatearDineroCliente($metrics[$key]) : number_format((int) $metrics[$key], 0, ',', '.')) ?></strong><small><?= escape($detail) ?></small>
            </article><?php endforeach; ?>
    </section>
    <div class="admin-dashboard-grid">
        <section class="admin-dashboard-card admin-dashboard-card--wide">
            <header>
                <div><span>Actividad reciente</span>
                    <h2>Pedidos recientes</h2>
                </div><a class="admin-dashboard-action-button" href="<?= escape(appUrl('admin/pedidos/index.php')) ?>"><i class="bi bi-eye-fill" aria-hidden="true"></i><span>Ver todos</span></a>
            </header><?php if ($orders !== []): ?>
                <div class="admin-dashboard-table-wrap">
                    <table class="admin-data-table admin-dashboard-table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Estado</th>
                                <th>Pago</th>
                                <th>Total</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($orders as $order): ?>
                                <tr>
                                    <td data-label="Pedido"><strong><?= escape((string) $order['codigo_pedido']) ?></strong>
                                    </td>
                                    <td data-label="Cliente"><?= escape((string) ($order['cliente'] ?: 'Sin cliente')) ?></td>
                                    <td data-label="Estado"><span
                                            class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado'])) ?>"><?= escape(etiquetaEstadoPedido((string) $order['estado'])) ?></span>
                                    </td>
                                    <td data-label="Pago"><span
                                            class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado_pago'], true)) ?>"><?= escape(etiquetaEstadoPagoPedido((string) $order['estado_pago'])) ?></span>
                                    </td>
                                    <td data-label="Total">
                                        <strong><?= escape(formatearDineroCliente($order['total'])) ?></strong>
                                    </td>
                                    <td data-label="Fecha"><?= escape(formatearFechaCliente($order['creado_en'], 'd-m-Y')) ?>
                                    </td>
                                    <td><a class="admin-order-view"
                                            href="<?= escape(appUrl('admin/pedidos/ver.php?id_pedido=' . $order['id_pedido'])) ?>"><i class="bi bi-eye-fill" aria-hidden="true"></i><span>Ver detalle</span></a></td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div><?php else: ?>
                <div class="admin-dashboard-empty">
                    <strong><?= $databaseError ? 'Información no disponible' : 'Aún no hay pedidos' ?></strong><span>Los
                        pedidos
                        recientes aparecerán aquí.</span>
                </div><?php endif; ?>
        </section>
        <section class="admin-dashboard-card admin-dashboard-card--compact admin-dashboard-card--alerts">
            <header>
                <div><span>Inventario</span>
                    <h2>Alertas de stock</h2>
                </div><a class="admin-dashboard-action-button admin-dashboard-action-button--warning" href="<?= escape(appUrl('admin/inventario/index.php?estado_stock=stock_bajo')) ?>"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>Revisar</span></a>
            </header><?php if ($alerts !== []): ?>
                <div class="admin-dashboard-alerts">
                    <?php foreach ($alerts as $item):
                        $fractionable = $bool($item['maneja_fraccionamiento']); ?>
                        <article class="admin-dashboard-alert-item">
                            <span class="admin-dashboard-alert-indicator" aria-hidden="true">!</span>
                            <div class="admin-dashboard-alert-copy">
                                <strong><?= escape((string) $item['nombre']) ?></strong><span><?= escape((string) ($item['sku'] ?: 'Sin SKU')) ?>
                                    · <?= escape((string) $item['categoria']) ?></span></div>
                            <dl>
                                <div>
                                    <dt>Actual</dt>
                                    <dd><?= escape(formatearCantidadStock((int) $item['stock_actual'], $fractionable)) ?></dd>
                                </div>
                                <div>
                                    <dt>Mínimo</dt>
                                    <dd><?= escape(formatearCantidadStock((int) $item['stock_minimo'], $fractionable)) ?></dd>
                                </div>
                            </dl><span
                                class="admin-dashboard-stock admin-dashboard-stock--<?= escape((string) $item['estado_stock']) ?>"><?= $item['estado_stock'] === 'sin_stock' ? 'Sin stock' : 'Stock bajo' ?></span><a
                                class="admin-dashboard-inline-button" href="<?= escape(appUrl('admin/inventario/stock/index.php?id=' . $item['id_producto'])) ?>"><i class="bi bi-sliders" aria-hidden="true"></i><span>Gestionar</span></a>
                        </article><?php endforeach; ?>
                </div><?php else: ?>
                <div class="admin-dashboard-empty"><strong>Stock saludable</strong><span>No hay alertas críticas en este
                        momento.</span></div><?php endif; ?>
        </section>
        <section class="admin-dashboard-card admin-dashboard-card--compact admin-dashboard-card--clients">
            <header>
                <div><span>Relaciones</span>
                    <h2>Clientes recientes</h2>
                </div><a class="admin-dashboard-action-button" href="<?= escape(appUrl('admin/clientes/index.php')) ?>"><i class="bi bi-people-fill" aria-hidden="true"></i><span>Ver todos</span></a>
            </header><?php if ($clients !== []): ?>
                <div class="admin-dashboard-clients"><?php foreach ($clients as $client): ?>
                        <article>
                            <div>
                                <strong><?= escape((string) $client['nombre']) ?></strong><span><?= escape((string) ($client['email'] ?: $client['telefono'] ?: 'Sin contacto')) ?></span><small><?= escape((string) ($client['comuna'] ?: 'Sin comuna')) ?></small>
                            </div>
                            <dl>
                                <dt><?= escape((string) $client['pedidos']) ?> pedidos</dt>
                                <dd><?= escape(formatearFechaCliente($client['ultima_compra'], 'd-m-Y')) ?></dd>
                            </dl><a class="admin-dashboard-inline-button admin-dashboard-inline-button--secondary"
                                href="<?= escape(appUrl('admin/clientes/ver.php?id_cliente=' . $client['id_cliente'])) ?>"><i class="bi bi-eye-fill" aria-hidden="true"></i><span>Ver cliente</span></a>
                        </article><?php endforeach; ?>
                </div><?php else: ?>
                <div class="admin-dashboard-empty"><strong>Aún no hay clientes</strong><span>Los perfiles recientes
                        aparecerán aquí.</span></div><?php endif; ?>
        </section>
    </div>
    <div class="admin-dashboard-lower">
        <section class="admin-dashboard-card admin-dashboard-card--compact admin-dashboard-card--catalog">
            <header>
                <div>
                    <span>Calidad del catálogo</span>
                    <h2>Pendientes de catálogo</h2>
                </div>
            </header>

            <?php
            $catalogPendingItems = [
                [
                    'key' => 'sin_imagen',
                    'label' => 'Sin imagen',
                    'detail' => 'Productos sin fotografía principal.',
                    'icon' => 'IMG',
                    'tone' => 'image',
                ],
                [
                    'key' => 'sin_presentaciones',
                    'label' => 'Sin presentaciones',
                    'detail' => 'Alimentos pendientes de formato.',
                    'icon' => 'PR',
                    'tone' => 'presentation',
                ],
                [
                    'key' => 'sin_sku',
                    'label' => 'Sin SKU',
                    'detail' => 'Productos sin código interno.',
                    'icon' => 'SKU',
                    'tone' => 'sku',
                ],
                [
                    'key' => 'sin_stock',
                    'label' => 'Sin stock',
                    'detail' => 'Productos sin disponibilidad.',
                    'icon' => 'STK',
                    'tone' => 'stock',
                ],
            ];
            ?>

            <div class="admin-dashboard-pending" aria-label="Pendientes de catálogo">
                <?php foreach ($catalogPendingItems as $item):
                    $value = (int) ($pending[$item['key']] ?? 0);
                    $stateClass = $value > 0 ? 'has-pending' : 'is-ok';
                    ?>
                    <a class="admin-dashboard-pending-card admin-dashboard-pending-card--<?= escape($item['tone']) ?> <?= $stateClass ?>"
                        href="<?= escape(appUrl('admin/inventario/index.php')) ?>">
                        <span class="admin-dashboard-pending-card__icon" aria-hidden="true">
                            <?= escape($item['icon']) ?>
                        </span>

                        <span class="admin-dashboard-pending-card__body">
                            <span class="admin-dashboard-pending-card__label">
                                <?= escape($item['label']) ?>
                            </span>

                            <strong class="admin-dashboard-pending-card__count">
                                <?= escape((string) $value) ?>
                            </strong>

                            <small class="admin-dashboard-pending-card__text">
                                <?= escape($item['detail']) ?>
                            </small>
                        </span>

                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="admin-dashboard-card admin-dashboard-card--compact admin-dashboard-card--status">
            <header>
                <div><span>Operación</span>
                    <h2>Estado de la tienda</h2>
                </div>
            </header>
            <dl class="admin-dashboard-status">
                <?php foreach ([['Tienda activa', $settings['modo_tienda'] === 'activa'], ['Despacho habilitado', $bool($settings['permite_despacho'])], ['Retiro habilitado', $bool($settings['permite_retiro'])], ['Venta sin stock', $bool($settings['permitir_venta_sin_stock'])], ['Mostrar stock', $bool($settings['mostrar_stock'])]] as [$label, $enabled]): ?>
                    <div class="admin-dashboard-store-status-card">
                        <dt><?= escape($label) ?></dt>
                        <dd class="<?= $enabled ? 'is-enabled' : 'is-disabled' ?>"><?= $enabled ? 'Sí' : 'No' ?></dd>
                    </div><?php endforeach; ?>
            </dl>
        </section>
    </div>
    <section class="admin-dashboard-card admin-dashboard-card--compact admin-dashboard-quick admin-dashboard-shortcuts">
        <header>
            <div><span>Navegación</span>
                <h2>Accesos rápidos</h2>
            </div>
        </header>
        <div>
            <?php foreach ([['Crear producto', 'admin/inventario/productos/crear.php', 'bi-box-seam-fill'], ['Importar productos', 'admin/inventario/importar/index.php', 'bi-file-earmark-spreadsheet-fill'], ['Ver pedidos', 'admin/pedidos/index.php', 'bi-receipt-cutoff'], ['Ver clientes', 'admin/clientes/index.php', 'bi-people-fill'], ['Configuración', 'admin/configuracion/index.php', 'bi-gear-fill'], ['Revisar stock bajo', 'admin/inventario/index.php?estado_stock=stock_bajo', 'bi-exclamation-triangle-fill'], ['Crear categoría', 'admin/categorias/crear.php', 'bi-tags-fill'], ['Crear marca', 'admin/marcas/crear.php', 'bi-award-fill']] as [$label, $path, $icon]): ?><a
                    class="admin-dashboard-shortcut-card" href="<?= escape(appUrl($path)) ?>"><span class="admin-dashboard-shortcut-card__icon" aria-hidden="true"><i class="bi <?= escape($icon) ?>"></i></span><strong><?= escape($label) ?></strong></a><?php endforeach; ?>
        </div>
    </section>
</main><?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
