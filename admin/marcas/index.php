<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php'; require_once dirname(__DIR__, 2) . '/config/database.php'; require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php'; require_once __DIR__ . '/consultas/listar-marcas.php';
requireAuthentication();
$brands = []; $loadError = false;
try { $brands = listarMarcas(database()); } catch (Throwable $exception) { $loadError = true; error_log('Brands list error: ' . $exception->getMessage()); }
$messages = ['creada' => 'La marca fue creada correctamente.', 'actualizada' => 'La marca fue actualizada correctamente.', 'activada' => 'La marca fue activada correctamente.', 'desactivada' => 'La marca fue desactivada correctamente.', 'error' => 'No fue posible cambiar el estado de la marca.'];
$message = $messages[$_GET['mensaje'] ?? ''] ?? null; $csrfToken = csrfToken(); $pageTitle = 'Marcas'; $activeSection = 'marcas';
require dirname(__DIR__, 2) . '/shared/admin-header.php'; require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal"><header class="admin-page-header"><div><h1 class="admin-page-title admin-page-title--paw">Marcas</h1><p>Administra las marcas disponibles para los productos.</p></div><div class="admin-actions"><a class="admin-button admin-button--primary" href="<?= escape(appUrl('admin/marcas/crear.php')) ?>">Agregar marca</a></div></header>
<?php if ($message): ?><div class="admin-alert <?= ($_GET['mensaje'] ?? '') === 'error' ? 'admin-alert--error' : 'admin-alert--success' ?>" role="status"><strong><?= escape($message) ?></strong></div><?php endif; ?>
<?php if ($loadError): ?><div class="admin-alert admin-alert--error" role="alert">No fue posible cargar las marcas.</div><?php endif; ?>
<section class="admin-panel"><div class="admin-panel__header"><h2>Lista de marcas</h2></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Nombre</th><th>Slug</th><th>Estado</th><th>Actualizada</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($brands as $brand): ?><?php $active = booleanoPostgresMantenedor($brand['activo']); ?><tr><td><?= escape((string) $brand['nombre']) ?></td><td><?= escape((string) $brand['slug']) ?></td><td><?= $active ? 'Activa' : 'Inactiva' ?></td><td><?= escape(formatearFechaMantenedor($brand['actualizado_en'])) ?></td><td><div class="admin-actions"><a class="admin-button" href="<?= escape(appUrl('admin/marcas/editar.php?id=' . $brand['id_marca'])) ?>">Editar</a><form method="post" action="<?= escape(appUrl('admin/marcas/cambiar-estado.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="id_marca" value="<?= escape((string) $brand['id_marca']) ?>"><button class="admin-button" type="submit"><?= $active ? 'Desactivar' : 'Activar' ?></button></form></div></td></tr><?php endforeach; ?>
<?php if ($brands === [] && !$loadError): ?><tr class="admin-empty-state"><td colspan="5"><strong>Aún no hay marcas registradas</strong><span>Crea la primera marca para asociarla a los productos.</span></td></tr><?php endif; ?>
</tbody></table></div></section>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
