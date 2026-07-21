<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-categoria.php';

requireAuthentication();
$id = idPositivoCategoria($_GET['id'] ?? null);
$category = null;
try { if ($id !== null) { $category = obtenerCategoria(database(), $id); } } catch (Throwable $exception) { error_log('Category edit load error: ' . $exception->getMessage()); }
if ($category === null) { http_response_code(404); }
$state = consumirEstadoMantenedor('categoria_editar_' . ($id ?? 0));
$values = $category === null ? valoresInicialesCategoria() : ['nombre' => $category['nombre'], 'descripcion' => $category['descripcion'] ?? '', 'orden' => $category['orden'], 'maneja_fraccionamiento' => booleanoPostgresMantenedor($category['maneja_fraccionamiento']), 'activo' => booleanoPostgresMantenedor($category['activo'])];
$values = array_merge($values, $state['valores'] ?? []);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';
if ($errors !== [] || $generalError !== null) {
    $adminModal = ['type' => 'error', 'title' => 'No fue posible actualizar la categoría', 'message' => $errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.', 'detail' => resumenErroresFormulario($errors, $generalError), 'reference' => $errorReference, 'primaryText' => 'Aceptar'];
}
$csrfToken = csrfToken(); $pageTitle = 'Categorías'; $activeSection = 'categorias';
require dirname(__DIR__, 2) . '/shared/admin-header.php'; require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal"><header class="admin-page-header"><div><a class="admin-back-link" href="<?= escape(appUrl('admin/categorias/index.php')) ?>">← Volver a categorías</a><h1 class="admin-page-title admin-page-title--paw">Editar categoría</h1></div></header>
<?php if ($category === null): ?><div class="admin-alert admin-alert--error" role="alert">La categoría solicitada no existe.</div>
<?php else: ?>
<form class="admin-product-form" method="post" action="<?= escape(appUrl('admin/categorias/actualizar.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="id_categoria" value="<?= escape((string) $id) ?>"><section class="admin-panel"><div class="admin-panel__header"><h2>Información de la categoría</h2></div><div class="admin-form-grid">
<div class="admin-field admin-field--full<?= isset($errors['nombre']) ? ' admin-field--invalid' : '' ?>"><label for="nombre">Nombre *</label><input id="nombre" name="nombre" maxlength="120" required value="<?= escape((string) $values['nombre']) ?>"><?php if (isset($errors['nombre'])): ?><span class="admin-field__error"><?= escape($errors['nombre']) ?></span><?php endif; ?></div>
<div class="admin-field admin-field--full<?= isset($errors['descripcion']) ? ' admin-field--invalid' : '' ?>"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" maxlength="1000" rows="4"><?= escape((string) $values['descripcion']) ?></textarea><?php if (isset($errors['descripcion'])): ?><span class="admin-field__error"><?= escape($errors['descripcion']) ?></span><?php endif; ?></div>
<div class="admin-field<?= isset($errors['orden']) ? ' admin-field--invalid' : '' ?>"><label for="orden">Orden *</label><input id="orden" name="orden" type="number" min="0" step="1" required value="<?= escape((string) $values['orden']) ?>"><?php if (isset($errors['orden'])): ?><span class="admin-field__error"><?= escape($errors['orden']) ?></span><?php endif; ?></div>
<div class="admin-field"><label><input name="maneja_fraccionamiento" type="checkbox" value="1" <?= $values['maneja_fraccionamiento'] ? 'checked' : '' ?>> Categoría fraccionable</label><span class="admin-field__help">Administra stock en gramos y permite venta por presentaciones.</span></div>
<div class="admin-field"><label><input name="activo" type="checkbox" value="1" <?= $values['activo'] ? 'checked' : '' ?>> Categoría activa</label></div>
</div></section><section class="admin-panel admin-form-actions"><a class="admin-button" href="<?= escape(appUrl('admin/categorias/index.php')) ?>">Cancelar</a><button class="admin-button admin-button--primary" type="submit">Actualizar categoría</button></section></form>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
