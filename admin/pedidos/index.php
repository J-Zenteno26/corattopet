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
$summary = ['recibidos' => 0, 'en_preparacion' => 0, 'pendientes_pago' => 0, 'entregados' => 0, 'cancelados' => 0];
$listing = ['registros' => [], 'total_registros' => 0, 'total_paginas' => 1, 'pagina_actual' => 1, 'por_pagina' => 20];
$databaseError = false;
try { $connection = database(); $summary = obtenerResumenPedidos($connection); $listing = listarPedidos($connection, $filters); }
catch (Throwable $exception) { $databaseError = true; $reference = registrarExcepcionAdmin('Orders listing error', $exception); $adminModal = ['type' => 'error', 'title' => 'No fue posible cargar los pedidos', 'message' => 'No se pudo completar la acción.', 'reference' => $reference, 'primaryText' => 'Aceptar']; }

$hasFilters = $filters['buscar'] !== '' || $filters['estado'] !== '' || $filters['estado_pago'] !== '' || $filters['fecha_desde'] !== '' || $filters['fecha_hasta'] !== '';
$query = array_filter($filters, static fn (mixed $value, string $key): bool => !in_array($key, ['pagina', 'por_pagina'], true) && $value !== '', ARRAY_FILTER_USE_BOTH);
$pageUrl = static fn (int $page): string => appUrl('admin/pedidos/index.php') . '?' . http_build_query(array_merge($query, ['pagina' => $page]));
$pageTitle = 'Pedidos'; $activeSection = 'pedidos'; $csrfToken = csrfToken();
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header admin-orders-page-header"><div><span class="admin-orders-eyebrow">Operación comercial</span><h1 class="admin-page-title admin-page-title--paw">Pedidos</h1><p>Gestiona las compras, pagos y estados de preparación.</p></div></header>
    <section class="admin-order-summary" aria-label="Resumen de pedidos">
        <?php foreach ([['recibidos','Pedidos recibidos','inbox'],['en_preparacion','En preparación','preparing'],['pendientes_pago','Pendientes de pago','payment'],['entregados','Entregados','delivered'],['cancelados','Cancelados','cancelled']] as [$key,$label,$class]): ?>
            <article class="admin-order-summary__card admin-order-summary__card--<?= escape($class) ?>"><span><?= escape($label) ?></span><strong><?= escape(number_format((int) $summary[$key], 0, ',', '.')) ?></strong></article>
        <?php endforeach; ?>
    </section>
    <section class="admin-orders-panel" aria-labelledby="orders-list-title">
        <header><div><span>Control de pedidos</span><h2 id="orders-list-title">Listado de compras</h2></div><strong><?= escape((string) $listing['total_registros']) ?> pedidos</strong></header>
        <form class="admin-order-filters" method="get" action="<?= escape(appUrl('admin/pedidos/index.php')) ?>">
            <div class="admin-field admin-order-filter-search"><label for="buscar">Código o cliente</label><input id="buscar" name="buscar" type="search" maxlength="160" value="<?= escape($filters['buscar']) ?>" placeholder="COR-000001, nombre o email"></div>
            <div class="admin-field"><label for="estado">Estado pedido</label><select id="estado" name="estado"><option value="">Todos</option><?php foreach (estadosPedido() as $value => $label): ?><option value="<?= escape($value) ?>" <?= $filters['estado'] === $value ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></div>
            <div class="admin-field"><label for="estado_pago">Estado pago</label><select id="estado_pago" name="estado_pago"><option value="">Todos</option><?php foreach (estadosPagoPedido() as $value => $label): ?><option value="<?= escape($value) ?>" <?= $filters['estado_pago'] === $value ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></div>
            <div class="admin-field"><label for="fecha_desde">Desde</label><input id="fecha_desde" name="fecha_desde" type="date" value="<?= escape($filters['fecha_desde']) ?>"></div>
            <div class="admin-field"><label for="fecha_hasta">Hasta</label><input id="fecha_hasta" name="fecha_hasta" type="date" value="<?= escape($filters['fecha_hasta']) ?>"></div>
            <div class="admin-order-filter-actions"><button class="admin-button admin-button--primary" type="submit">Aplicar filtros</button><?php if ($hasFilters): ?><a class="admin-button" href="<?= escape(appUrl('admin/pedidos/index.php')) ?>">Limpiar</a><?php endif; ?></div>
        </form>
        <?php if ($listing['registros'] !== []): ?>
        <div class="admin-order-table-wrap"><table class="admin-order-table admin-data-table"><thead><tr><th>Pedido</th><th>Cliente</th><th>Fecha</th><th>Estado</th><th>Pago</th><th>Total</th><th>Entrega</th><th></th></tr></thead><tbody>
            <?php foreach ($listing['registros'] as $order): ?><tr>
                <td data-label="Pedido"><strong class="admin-order-code"><?= escape((string) $order['codigo_pedido']) ?></strong></td>
                <td data-label="Cliente"><span class="admin-order-customer"><strong><?= escape((string) ($order['cliente_nombre'] ?: 'Cliente no asociado')) ?></strong><small><?= escape((string) ($order['cliente_email'] ?: 'Sin email')) ?></small></span></td>
                <td data-label="Fecha"><time datetime="<?= escape((string) $order['creado_en']) ?>"><?= escape(formatearFechaPedido($order['creado_en'], 'd-m-Y')) ?><small><?= escape(formatearFechaPedido($order['creado_en'], 'H:i')) ?></small></time></td>
                <td data-label="Estado"><span class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado'])) ?>"><?= escape(etiquetaEstadoPedido((string) $order['estado'])) ?></span></td>
                <td data-label="Pago"><span class="admin-order-badge <?= escape(claseEstadoPedido((string) $order['estado_pago'], true)) ?>"><?= escape(etiquetaEstadoPagoPedido((string) $order['estado_pago'])) ?></span></td>
                <td data-label="Total"><strong><?= escape(formatearDineroPedido($order['total'])) ?></strong></td>
                <td data-label="Entrega"><?= escape(descripcionEntregaPedido($order['metodo_entrega'])) ?></td>
                <td class="admin-order-table__action"><a class="admin-order-view" href="<?= escape(appUrl('admin/pedidos/ver.php?id_pedido=' . $order['id_pedido'])) ?>">Ver detalle <span aria-hidden="true">→</span></a></td>
            </tr><?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?><div class="admin-orders-empty"><span aria-hidden="true">CP</span><h3><?= $databaseError ? 'No fue posible cargar los pedidos' : ($hasFilters ? 'No encontramos pedidos' : 'Aún no hay pedidos registrados') ?></h3><p><?= $databaseError ? 'Intenta nuevamente más tarde.' : ($hasFilters ? 'Prueba con otros criterios o limpia los filtros seleccionados.' : 'Las compras aparecerán aquí cuando el checkout comience a registrar pedidos.') ?></p><?php if ($hasFilters && !$databaseError): ?><a class="admin-button" href="<?= escape(appUrl('admin/pedidos/index.php')) ?>">Limpiar filtros</a><?php endif; ?></div><?php endif; ?>
        <?php if ($listing['total_paginas'] > 1): ?><nav class="admin-order-pagination" aria-label="Paginación de pedidos"><?php if ($listing['pagina_actual'] > 1): ?><a href="<?= escape($pageUrl($listing['pagina_actual'] - 1)) ?>">← Anterior</a><?php endif; ?><span>Página <?= escape((string) $listing['pagina_actual']) ?> de <?= escape((string) $listing['total_paginas']) ?></span><?php if ($listing['pagina_actual'] < $listing['total_paginas']): ?><a href="<?= escape($pageUrl($listing['pagina_actual'] + 1)) ?>">Siguiente →</a><?php endif; ?></nav><?php endif; ?>
    </section>
</main>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
