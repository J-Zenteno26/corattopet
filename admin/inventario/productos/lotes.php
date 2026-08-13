<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
require_once dirname(__DIR__) . '/includes/funciones-inventario.php';
require_once __DIR__ . '/includes/consultas-lotes-producto.php';
requireAuthentication();
$productId = normalizarIdFiltro($_GET['id_producto'] ?? null);
if ($productId === null) {
    guardarModalAdmin('error', 'Producto inválido', 'El producto indicado no es válido.');
    header('Location: ' . appUrl('admin/inventario/index.php'));
    exit;
}
try {
    $pdo = database();
    $product = obtenerProductoDetalleLotes($pdo, $productId);
    $lots = $product === null ? [] : obtenerLotesDetalleProducto($pdo, $productId);
} catch (Throwable $e) {
    error_log('Product lots detail error: ' . $e->getMessage());
    guardarModalAdmin('error', 'No fue posible cargar los lotes', 'Intenta nuevamente más tarde.');
    header('Location: ' . appUrl('admin/inventario/index.php'));
    exit;
}
if ($product === null) {
    guardarModalAdmin('error', 'Producto no encontrado', 'El producto indicado no existe.');
    header('Location: ' . appUrl('admin/inventario/index.php'));
    exit;
}
$worstPriority = null;
$nextExpiration = null;
$statusCounts = ['expired' => 0, 'critical' => 0, 'upcoming' => 0, 'current' => 0];
$today = new DateTimeImmutable('today');
foreach ($lots as &$lot) {
    $lot['_estado'] = estadoFechaLoteInventario((string) $lot['fecha_vencimiento'], $today);
    $statusCounts[$lot['_estado']['key']]++;
    $worstPriority = $worstPriority === null ? $lot['_estado']['priority'] : min($worstPriority, $lot['_estado']['priority']);
    if ($lot['fecha_vencimiento'] >= $today->format('Y-m-d') && ($nextExpiration === null || $lot['fecha_vencimiento'] < $nextExpiration))
        $nextExpiration = $lot['fecha_vencimiento'];
}
unset($lot);
$generalState = estadoLotesPorPrioridadInventario($worstPriority);
$pageTitle = 'Lotes · ' . $product['nombre'];
$activeSection = 'inventario';
require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php'; ?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-lot-detail-hero">
        <div><a class="admin-back-link" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">← Volver al
                inventario</a><span>TRAZABILIDAD DE INVENTARIO</span>
            <h1><?= escape((string) $product['nombre']) ?></h1>
            <p>SKU <?= escape((string) ($product['sku'] ?: 'sin asignar')) ?> ·
                <?= escape((string) $product['categoria']) ?> · <?= escape((string) ($product['marca'] ?: 'Sin marca')) ?>
            </p>
        </div>
        <div><span>Stock
                vendible</span><strong><?= escape(formatearPesoLoteInventario($product['stock_vendible'])) ?></strong><a
                class="admin-button"
                href="<?= escape(appUrl('admin/inventario/stock/index.php?id=' . $productId)) ?>">Gestionar stock</a>
        </div>
    </header>
    <section class="admin-lot-detail-summary" aria-label="Resumen de lotes">
        <article><span>Lotes activos</span><strong><?= count($lots) ?></strong></article>
        <article><span>Próximo
                vencimiento</span><strong><?= $nextExpiration ? escape((new DateTimeImmutable($nextExpiration))->format('d-m-Y')) : 'Sin fecha futura' ?></strong>
        </article>
        <article class="is-<?= escape($generalState['key']) ?>"><span>Estado
                general</span><strong><?= escape($generalState['label']) ?></strong></article>
        <article><span>Distribución</span>
            <p><b><?= $statusCounts['expired'] ?></b> vencidos · <b><?= $statusCounts['critical'] ?></b> críticos ·
                <b><?= $statusCounts['upcoming'] ?></b> próximos</p>
        </article>
    </section>
    <section class="admin-panel admin-lot-detail-panel">
        <div class="admin-panel__header">
            <h2>Lotes del producto</h2>
            <p class="admin-panel__intro">Ordenados por fecha de vencimiento, desde el más próximo.</p>
        </div>
        <?php if ($lots !== []): ?>
            <div class="admin-table-wrap">
                <table class="admin-table admin-lot-detail-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Proveedor</th>
                            <th>Elaboración</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                            <th>Cantidad inicial</th>
                            <th>Cantidad disponible</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($lots as $lot): ?>
                            <tr>
                                <td data-label="Código"><strong><?= escape((string) $lot['codigo_lote']) ?></strong></td>
                                <td data-label="Proveedor"><?= escape((string) ($lot['proveedor'] ?: 'Sin proveedor')) ?></td>
                                <td data-label="Elaboración">
                                    <?= $lot['fecha_elaboracion'] ? escape((new DateTimeImmutable((string) $lot['fecha_elaboracion']))->format('d-m-Y')) : 'No informada' ?>
                                </td>
                                <td data-label="Vencimiento">
                                    <strong><?= escape((new DateTimeImmutable((string) $lot['fecha_vencimiento']))->format('d-m-Y')) ?></strong>
                                </td>
                                <td data-label="Estado"><span
                                        class="admin-lot-status is-<?= escape($lot['_estado']['key']) ?>"><?= escape($lot['_estado']['label']) ?></span>
                                </td>
                                <td data-label="Cantidad inicial">
                                    <?= escape(formatearPesoLoteInventario($lot['cantidad_inicial_g'])) ?></td>
                                <td data-label="Cantidad disponible">
                                    <strong><?= escape(formatearPesoLoteInventario($lot['cantidad_disponible_g'])) ?></strong>
                                </td>
                                <td data-label="Acciones"><a class="admin-button"
                                        href="<?= escape(appUrl('admin/inventario/productos/editar-lote.php?id_lote=' . (int) $lot['id_lote'])) ?>">Editar
                                        lote</a></td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div><?php else: ?>
            <div class="admin-lot-detail-empty"><i class="bi bi-box-seam" aria-hidden="true"></i>
                <h2>Este producto aún no tiene lotes</h2>
                <p>Los lotes aparecerán aquí cuando se registre una entrada de stock.</p><a
                    class="admin-button admin-button--primary"
                    href="<?= escape(appUrl('admin/inventario/stock/index.php?id=' . $productId)) ?>">Registrar stock</a>
            </div><?php endif; ?>
    </section>
</main><?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>