<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-categoria.php';

requireAuthentication();
$state = consumirEstadoMantenedor('categoria_crear');
$values = array_merge(valoresInicialesCategoria(), $state['valores'] ?? []);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$csrfToken = csrfToken();
$pageTitle = 'Categorías';
$activeSection = 'categorias';
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header"><div><a class="admin-back-link" href="<?= escape(appUrl('admin/categorias/index.php')) ?>">← Volver a categorías</a><h1 class="admin-page-title admin-page-title--paw">Agregar categoría</h1><p>Registra una categoría para organizar los productos.</p></div></header>
    <?php if ($errors !== [] || $generalError !== null): ?><div class="admin-alert admin-alert--error" role="alert"><strong>No fue posible guardar la categoría.</strong><?php if ($generalError): ?><p><?= escape($generalError) ?></p><?php endif; ?><ul><?php foreach ($errors as $error): ?><li><?= escape((string) $error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="admin-product-form" method="post" action="<?= escape(appUrl('admin/categorias/guardar.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
        <section class="admin-panel"><div class="admin-panel__header"><h2>Información de la categoría</h2></div><div class="admin-form-grid">
            <div class="admin-field admin-field--full<?= isset($errors['nombre']) ? ' admin-field--invalid' : '' ?>"><label for="nombre">Nombre *</label><input id="nombre" name="nombre" type="text" maxlength="120" required value="<?= escape((string) $values['nombre']) ?>" <?= isset($errors['nombre']) ? 'aria-invalid="true" aria-describedby="nombre-error"' : '' ?>><?php if (isset($errors['nombre'])): ?><span class="admin-field__error" id="nombre-error"><?= escape($errors['nombre']) ?></span><?php endif; ?></div>
            <div class="admin-field admin-field--full<?= isset($errors['descripcion']) ? ' admin-field--invalid' : '' ?>"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" maxlength="1000" rows="4" <?= isset($errors['descripcion']) ? 'aria-invalid="true" aria-describedby="descripcion-error"' : '' ?>><?= escape((string) $values['descripcion']) ?></textarea><?php if (isset($errors['descripcion'])): ?><span class="admin-field__error" id="descripcion-error"><?= escape($errors['descripcion']) ?></span><?php endif; ?></div>
            <div class="admin-field<?= isset($errors['orden']) ? ' admin-field--invalid' : '' ?>"><label for="orden">Orden *</label><input id="orden" name="orden" type="number" min="0" step="1" required value="<?= escape((string) $values['orden']) ?>" <?= isset($errors['orden']) ? 'aria-invalid="true" aria-describedby="orden-error"' : '' ?>><?php if (isset($errors['orden'])): ?><span class="admin-field__error" id="orden-error"><?= escape($errors['orden']) ?></span><?php endif; ?></div>
            <div class="admin-field"><label for="activo"><input id="activo" name="activo" type="checkbox" value="1" <?= $values['activo'] ? 'checked' : '' ?>> Categoría activa</label></div>
        </div></section>
        <section class="admin-panel admin-form-actions"><a class="admin-button" href="<?= escape(appUrl('admin/categorias/index.php')) ?>">Cancelar</a><button class="admin-button admin-button--primary" type="submit">Guardar categoría</button></section>
    </form>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
