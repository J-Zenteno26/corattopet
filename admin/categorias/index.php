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

$messages = [
    'creada' => 'La categoría fue creada correctamente.',
    'actualizada' => 'La categoría fue actualizada correctamente.',
    'activada' => 'La categoría fue activada correctamente.',
    'desactivada' => 'La categoría fue desactivada correctamente.',
    'error' => 'No fue posible cambiar el estado de la categoría.',
];
$message = $messages[$_GET['mensaje'] ?? ''] ?? null;
$csrfToken = csrfToken();
$pageTitle = 'Categorías';
$activeSection = 'categorias';
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div><h1 class="admin-page-title admin-page-title--paw">Categorías</h1><p>Administra las categorías disponibles para los productos.</p></div>
        <div class="admin-actions"><a class="admin-button admin-button--primary" href="<?= escape(appUrl('admin/categorias/crear.php')) ?>">Agregar categoría</a></div>
    </header>
    <?php if ($message !== null): ?><div class="admin-alert <?= ($_GET['mensaje'] ?? '') === 'error' ? 'admin-alert--error' : 'admin-alert--success' ?>" role="status"><strong><?= escape($message) ?></strong></div><?php endif; ?>
    <?php if ($loadError): ?><div class="admin-alert admin-alert--error" role="alert">No fue posible cargar las categorías.</div><?php endif; ?>
    <section class="admin-panel" aria-labelledby="categories-title">
        <div class="admin-panel__header"><h2 id="categories-title">Lista de categorías</h2></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Nombre</th><th>Slug</th><th>Descripción</th><th>Orden</th><th>Estado</th><th>Actualizada</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($categories as $category): ?>
            <?php $active = booleanoPostgresMantenedor($category['activo']); ?>
            <tr><td><?= escape((string) $category['nombre']) ?></td><td><?= escape((string) $category['slug']) ?></td><td><?= escape($category['descripcion'] !== null ? (string) $category['descripcion'] : 'Sin descripción') ?></td><td><?= escape((string) $category['orden']) ?></td><td><?= $active ? 'Activa' : 'Inactiva' ?></td><td><?= escape(formatearFechaMantenedor($category['actualizado_en'])) ?></td><td><div class="admin-actions"><a class="admin-button" href="<?= escape(appUrl('admin/categorias/editar.php?id=' . $category['id_categoria'])) ?>">Editar</a><form method="post" action="<?= escape(appUrl('admin/categorias/cambiar-estado.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="id_categoria" value="<?= escape((string) $category['id_categoria']) ?>"><button class="admin-button" type="submit"><?= $active ? 'Desactivar' : 'Activar' ?></button></form></div></td></tr>
        <?php endforeach; ?>
        <?php if ($categories === [] && !$loadError): ?><tr class="admin-empty-state"><td colspan="7"><strong>Aún no hay categorías registradas</strong><span>Crea la primera categoría para organizar los productos.</span></td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
