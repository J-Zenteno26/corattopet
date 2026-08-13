<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-categoria-despacho.php';

requireAuthentication();

$id = idPositivoCategoriaDespacho($_GET['id'] ?? null);
$category = null;

try {
    if ($id !== null) {
        $category = obtenerCategoriaDespacho(database(), $id);
    }
} catch (Throwable $exception) {
    error_log('Shipping category edit load error: ' . $exception->getMessage());
}

if ($category === null) {
    http_response_code(404);
}

$state = consumirEstadoMantenedor('categoria_despacho_editar_' . ($id ?? 0));
$values = $category === null
    ? valoresInicialesCategoriaDespacho()
    : [
        'nombre' => $category['nombre'],
        'peso_estimado_gramos' => $category['peso_estimado_gramos'],
        'tamano' => $category['tamano'],
        'activo' => booleanoPostgresMantenedor($category['activo']),
    ];
$values = array_merge($values, $state['valores'] ?? []);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';

if ($errors !== [] || $generalError !== null) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible actualizar la categoría',
        'message' => $errors !== []
            ? 'Revisa los campos marcados antes de continuar.'
            : 'No se pudo completar la acción.',
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
}

$csrfToken = csrfToken();
$pageTitle = 'Editar categoría de despacho';
$activeSection = 'despachos';
$shippingCssPath = dirname(__DIR__, 3) . '/public/css/admin-despachos-categorias.css';
$shippingCssVersion = is_file($shippingCssPath)
    ? (string) filemtime($shippingCssPath)
    : '1';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<link
    rel="stylesheet"
    href="<?= escape(appUrl('public/css/admin-despachos-categorias.css') . '?v=' . $shippingCssVersion) ?>"
>

<main class="admin-main admin-shipping-categories" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <a
                class="admin-back-link"
                href="<?= escape(appUrl('admin/despachos/categorias/index.php')) ?>"
            >
                ← Volver a categorías de despacho
            </a>

            <h1 class="admin-page-title admin-page-title--paw">Editar categoría de despacho</h1>
            <?php if ($category !== null): ?>
                <p><?= (int) $category['productos_asignados'] ?> producto<?= (int) $category['productos_asignados'] === 1 ? '' : 's' ?> utiliza<?= (int) $category['productos_asignados'] === 1 ? '' : 'n' ?> actualmente esta categoría.</p>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($category === null): ?>
        <div class="admin-alert admin-alert--error" role="alert">
            La categoría de despacho solicitada no existe.
        </div>
    <?php else: ?>
        <div class="admin-form-layout admin-shipping-form-layout">
            <form
                class="admin-form-layout__form"
                method="post"
                action="<?= escape(appUrl('admin/despachos/categorias/actualizar.php')) ?>"
            >
                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                <input type="hidden" name="id_categoria_despacho" value="<?= (int) $id ?>">

                <section class="admin-panel admin-form-panel">
                    <div class="admin-panel__header">
                        <h2>Datos de la categoría</h2>
                    </div>

                    <div class="admin-form-grid admin-shipping-form-grid">
                        <div class="admin-field admin-field--full<?= isset($errors['nombre']) ? ' admin-field--invalid' : '' ?>">
                            <label for="nombre">Nombre <span class="admin-required">*</span></label>
                            <input
                                id="nombre"
                                name="nombre"
                                type="text"
                                maxlength="100"
                                required
                                autocomplete="off"
                                value="<?= escape((string) $values['nombre']) ?>"
                            >
                            <?php if (isset($errors['nombre'])): ?>
                                <span class="admin-field__error"><?= escape((string) $errors['nombre']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-field<?= isset($errors['peso_estimado_gramos']) ? ' admin-field--invalid' : '' ?>">
                            <label for="peso_estimado_gramos">Peso estimado en gramos <span class="admin-required">*</span></label>
                            <input
                                id="peso_estimado_gramos"
                                name="peso_estimado_gramos"
                                type="number"
                                min="1"
                                max="50000"
                                step="1"
                                required
                                value="<?= escape((string) $values['peso_estimado_gramos']) ?>"
                            >
                            <?php if (isset($errors['peso_estimado_gramos'])): ?>
                                <span class="admin-field__error"><?= escape((string) $errors['peso_estimado_gramos']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-field<?= isset($errors['tamano']) ? ' admin-field--invalid' : '' ?>">
                            <label for="tamano">Tamaño estimado <span class="admin-required">*</span></label>
                            <select id="tamano" name="tamano" required>
                                <option value="">Seleccionar tamaño</option>
                                <option value="pequeno" <?= $values['tamano'] === 'pequeno' ? 'selected' : '' ?>>Pequeño</option>
                                <option value="mediano" <?= $values['tamano'] === 'mediano' ? 'selected' : '' ?>>Mediano</option>
                                <option value="grande" <?= $values['tamano'] === 'grande' ? 'selected' : '' ?>>Grande</option>
                            </select>
                            <?php if (isset($errors['tamano'])): ?>
                                <span class="admin-field__error"><?= escape((string) $errors['tamano']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-status-control admin-field--full">
                            <div class="admin-status-control__copy">
                                <strong>Estado de la categoría</strong>
                                <span id="activo-help">Las categorías inactivas no estarán disponibles para nuevas asignaciones.</span>
                            </div>

                            <label class="admin-switch" for="activo">
                                <input
                                    id="activo"
                                    name="activo"
                                    type="checkbox"
                                    value="1"
                                    aria-describedby="activo-help"
                                    <?= $values['activo'] ? 'checked' : '' ?>
                                >
                                <span class="admin-switch__track" aria-hidden="true"></span>
                                <span class="admin-switch__label">Categoría activa</span>
                            </label>
                        </div>
                    </div>

                    <div class="admin-form-actions admin-form-actions--inside">
                        <a class="admin-button" href="<?= escape(appUrl('admin/despachos/categorias/index.php')) ?>">
                            Cancelar
                        </a>
                        <button class="admin-button admin-button--primary" type="submit">
                            Guardar cambios
                        </button>
                    </div>
                </section>
            </form>
        </div>
    <?php endif; ?>

    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
