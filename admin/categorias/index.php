<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/consultas/listar-categorias.php';

requireAuthentication();

$categories = [];
$loadError = false;
try {
    $categories = listarCategorias(database());
} catch (Throwable $exception) {
    $loadError = true;
    error_log('Categories list error: ' . $exception->getMessage());
}

if ($loadError) {
    $adminModal = ['type' => 'error', 'title' => 'No fue posible cargar las categorías', 'message' => 'Intenta nuevamente más tarde.', 'primaryText' => 'Aceptar'];
}
$csrfToken = csrfToken();
$pageTitle = 'Categorías';
$activeSection = 'categorias';

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-title admin-page-title--paw">Categorías</h1>
            <p>Administra las categorías disponibles para los productos.</p>
        </div>
        <div class="admin-actions"><a class="admin-button admin-button--primary"
                href="<?= escape(appUrl('admin/categorias/crear.php')) ?>">Agregar categoría</a></div>
    </header>
    <section class="admin-panel admin-panel--soft" aria-label="Listado de categorías">
        <div class="admin-panel__header">
            <h2>Lista de categorías</h2>
            <p class="admin-panel__intro">
                Gestiona el nombre, el orden y el estado de cada categoría.
            </p>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>NOMBRE</th>
                        <th>DESCRIPCIÓN</th>
                        <th>ORDEN</th>
                        <th>TIPO DE STOCK</th>
                        <th>ESTADO</th>
                        <th>ACTUALIZADA</th>
                        <th>ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <?php $active = booleanoPostgresMantenedor($category['activo']); ?>
                        <tr>
                            <td><?= escape((string) $category['nombre']) ?></td>
                            <td><?= escape($category['descripcion'] !== null ? (string) $category['descripcion'] : 'Sin descripción') ?>
                            </td>
                            <td><?= escape((string) $category['orden']) ?></td>
                            <td><?= booleanoPostgresMantenedor($category['maneja_fraccionamiento']) ? 'Peso (gramos)' : 'Unidades' ?></td>
                            <td>
                                <span class="admin-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>">
                                    <?= $active ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td><?= escape(formatearFechaMantenedor($category['actualizado_en'])) ?></td>
                            <td>
                                <div class="admin-actions-inline">
                                    <a class="admin-button admin-button--small admin-button--primary" href="<?= escape(appUrl(
                                        'admin/categorias/editar.php?id=' . (int) $category['id_categoria']
                                    )) ?>">
                                        Editar
                                    </a>

                                    <?php $formId = 'estado-categoria-' . (int) $category['id_categoria']; ?>
                                    <form id="<?= escape($formId) ?>" method="post"
                                        action="<?= escape(appUrl('admin/categorias/cambiar-estado.php')) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

                                        <input type="hidden" name="id_categoria"
                                            value="<?= (int) $category['id_categoria'] ?>">

                                        <button class="admin-button admin-button--small" type="button"
                                            data-admin-confirm-form="<?= escape($formId) ?>"
                                            data-modal-title="<?= $active ? 'Desactivar categoría' : 'Activar categoría' ?>"
                                            data-modal-message="<?= $active ? 'Esta categoría dejará de estar disponible para nuevos productos. Los productos existentes no se eliminarán.' : 'La categoría volverá a estar disponible para nuevos productos.' ?>"
                                            data-modal-primary="<?= $active ? 'Desactivar' : 'Activar' ?>"
                                            data-modal-destructive="<?= $active ? 'true' : 'false' ?>"
                                            data-modal-secondary="Cancelar">
                                            <?= $active ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                    <?php if ($categories === [] && !$loadError): ?>
                        <tr class="admin-empty-state">
                            <td colspan="7"><strong>Aún no hay categorías registradas</strong><span>Crea la primera
                                    categoría para organizar los productos.</span></td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
