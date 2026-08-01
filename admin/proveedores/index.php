<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-proveedores.php';
require_once __DIR__ . '/includes/consultas-proveedores.php';
requireAuthentication();
$buscar = trim((string) ($_GET['buscar'] ?? ''));
$estado = trim((string) ($_GET['estado'] ?? ''));
if (!in_array($estado, ['', 'activo', 'inactivo'], true))
    $estado = '';
$providers = [];
$loadError = false;
try {
    $providers = listarProveedores(database(), $buscar, $estado);
} catch (Throwable $e) {
    $loadError = true;
    $reference = registrarExcepcionAdmin('Suppliers list error', $e);
    $adminModal = ['type' => 'error', 'title' => 'No fue posible cargar los proveedores', 'message' => 'Intenta nuevamente más tarde.', 'reference' => $reference];
}
$pageTitle = 'Proveedores';
$activeSection = 'proveedores';
$csrfToken = csrfToken();
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php'; ?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-title admin-page-title--paw">Proveedores</h1>
            <p>Gestiona contactos, condiciones comerciales y productos asociados.</p>
        </div><a class="admin-button admin-button--primary"
            href="<?= escape(appUrl('admin/proveedores/crear.php')) ?>">Nuevo proveedor</a>
    </header>
    <section class="admin-panel admin-panel--soft">
        <form class="admin-provider-filters" method="get">
            <div class="admin-field"><label for="buscar">Buscar</label><input id="buscar" name="buscar" type="search"
                    value="<?= escape($buscar) ?>" placeholder="Nombre, razón social, RUT, contacto o email"></div>
            <div class="admin-field"><label for="estado">Estado</label><select id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                </select></div>
            <div class="admin-actions"><button
                    class="admin-button admin-button--primary">Filtrar</button><?php if ($buscar !== '' || $estado !== ''): ?><a
                        class="admin-button"
                        href="<?= escape(appUrl('admin/proveedores/index.php')) ?>">Limpiar</a><?php endif; ?></div>
        </form>
    </section>
    <section class="admin-panel">
        <div class="admin-panel__header">
            <h2>Listado</h2>
            <p><?= count($providers) ?> proveedor(es)</p>
        </div>
        <?php if ($providers !== []): ?>
            <div class="admin-table-wrap">
                <table class="admin-table admin-provider-table">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Contacto</th>
                            <th>Condición comercial</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $p):
                            $active = booleanoPostgresMantenedor($p['activo']);
                            $id = (int) $p['id_proveedor']; ?>
                            <tr>
                                <td data-label="Proveedor">
                                    <strong><?= escape($p['nombre']) ?></strong><small><?= escape((string) ($p['razon_social'] ?: $p['rut'] ?: 'Sin razón social')) ?></small>
                                </td>
                                <td data-label="Contacto">
                                    <span><?= escape((string) ($p['contacto_principal'] ?: 'Sin contacto')) ?></span><small><?= escape((string) ($p['email'] ?: $p['telefono'] ?: 'Sin datos')) ?></small>
                                </td>
                                <td data-label="Condición"><?= escape((string) ($p['condicion_pago'] ?: 'No informada')) ?></td>
                                <td data-label="Productos"><?= (int) $p['productos_activos'] ?></td>
                                <td data-label="Estado"><span
                                        class="admin-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>"><?= $active ? 'Activo' : 'Inactivo' ?></span>
                                </td>
                                <td data-label="Acciones">
                                    <div class="admin-actions-inline"><a class="admin-button admin-button--small"
                                            href="<?= escape(appUrl('admin/proveedores/ver.php?id_proveedor=' . $id)) ?>">Ver</a><a
                                            class="admin-button admin-button--small admin-button--primary"
                                            href="<?= escape(appUrl('admin/proveedores/editar.php?id_proveedor=' . $id)) ?>">Editar</a>
                                        <form method="post"
                                            action="<?= escape(appUrl('admin/proveedores/cambiar-estado.php')) ?>"><input
                                                type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input
                                                type="hidden" name="id_proveedor" value="<?= $id ?>"><button
                                                class="admin-button admin-button--small"
                                                type="submit"><?= $active ? 'Desactivar' : 'Activar' ?></button></form>
                                    </div>
                                </td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$loadError): ?>
            <div class="admin-empty-state">
                <strong><?= $buscar !== '' || $estado !== '' ? 'No se encontraron proveedores' : 'Aún no hay proveedores' ?></strong><span><?= $buscar !== '' || $estado !== '' ? 'Prueba con otros criterios.' : 'Crea el primer proveedor para comenzar.' ?></span>
            </div><?php endif; ?>
    </section>
</main><?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>