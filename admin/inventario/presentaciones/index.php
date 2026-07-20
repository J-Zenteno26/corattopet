<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once __DIR__ . '/includes/consultas-presentaciones.php';
require_once __DIR__ . '/includes/validaciones-presentacion.php';
requireAuthentication();

$productId = idPositivoPresentacion($_GET['id_producto'] ?? null);
try {
    $connection = database();
    $product = $productId === null ? null : buscarProductoFraccionable($connection, $productId);
    $presentations = $product === null ? [] : listarPresentaciones($connection, $productId);
} catch (Throwable $exception) {
    error_log('Presentation list error: ' . $exception->getMessage());
    $product = null;
    $presentations = [];
}
if ($product === null) {
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=presentaciones_no_disponibles'), true, 302);
    exit;
}
$csrfToken = csrfToken();
$pageTitle = 'Presentaciones';
$activeSection = 'inventario';
require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div><a class="admin-back-link" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">← Volver al
                inventario</a>
            <h1 class="admin-page-title admin-page-title--paw">Presentaciones</h1>
            <p><?= escape((string) $product['nombre']) ?> · El precio de venta se define por presentación.</p>
        </div><a class="admin-button admin-button--primary"
            href="<?= escape(appUrl('admin/inventario/presentaciones/crear.php?id_producto=' . $productId)) ?>">Agregar
            presentación</a>
    </header>
    <?php if (($_GET['creado'] ?? null) === '1'): ?>
        <div class="admin-alert admin-alert--success" role="status">Producto base creado. Ahora configura sus
            presentaciones.</div><?php endif; ?>
    <?php if (isset($_GET['mensaje'])): ?>
        <div class="admin-alert admin-alert--success" role="status">La presentación fue actualizada correctamente.</div>
    <?php endif; ?>
    <section class="admin-panel">
        <div class="admin-panel__header">
            <h2>Presentaciones disponibles</h2>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>NOMBRE</th>
                        <th>CANTIDAD</th>
                        <th>PRECIO</th>
                        <th>SKU</th>
                        <th>ORDEN</th>
                        <th>ESTADO</th>
                        <th>ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presentations as $presentation):
                        $active = in_array($presentation['activo'], [true, 1, '1', 't', 'true'], true); ?>
                        <tr>
                            <td><?= escape((string) $presentation['nombre']) ?></td>
                            <td><?= escape(formatearCantidadStock((int) $presentation['cantidad_gramos'], true)) ?></td>
                            <td><?= escape('$' . number_format((int) $presentation['precio_venta'], 0, ',', '.')) ?></td>
                            <td><?= escape($presentation['sku'] ?: 'Sin SKU') ?></td>
                            <td><?= (int) $presentation['orden'] ?></td>
                            <td><span
                                    class="admin-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>"><?= $active ? 'Activa' : 'Inactiva' ?></span>
                            </td>
                            <td>
                                <div class="admin-actions-inline"><a class="admin-button admin-button--dark"
                                        href="<?= escape(appUrl('admin/inventario/presentaciones/editar.php?id=' . $presentation['id_presentacion'])) ?>">Editar</a>
                                    <form method="post"
                                        action="<?= escape(appUrl('admin/inventario/presentaciones/cambiar-estado.php')) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input
                                            type="hidden" name="id_presentacion"
                                            value="<?= (int) $presentation['id_presentacion'] ?>"><button
                                            class="admin-button"
                                            type="submit"><?= $active ? 'Desactivar' : 'Activar' ?></button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($presentations === []): ?>
                        <tr class="admin-empty-state">
                            <td colspan="7"><strong>Aún no hay presentaciones</strong><span>Agrega formatos como Bolsa 250 g
                                    o Bolsa 1 kg.</span></td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>