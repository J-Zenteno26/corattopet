<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-categoria-despacho.php';

requireAuthentication();

$state = consumirEstadoMantenedor('categoria_despacho_crear');
$values = array_merge(valoresInicialesCategoriaDespacho(), $state['valores'] ?? []);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';

if ($errors !== [] || $generalError !== null) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible guardar la categoría',
        'message' => $errors !== []
            ? 'Revisa los campos marcados antes de continuar.'
            : 'No se pudo completar la acción.',
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
}

$csrfToken = csrfToken();
$pageTitle = 'Nueva categoría de despacho';
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

            <h1 class="admin-page-title admin-page-title--paw">Agregar categoría de despacho</h1>
            <p>Define una clasificación reutilizable para productos sin peso propio.</p>
        </div>
    </header>

    <div class="admin-form-layout admin-shipping-form-layout">
        <form
            class="admin-form-layout__form"
            method="post"
            action="<?= escape(appUrl('admin/despachos/categorias/guardar.php')) ?>"
        >
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

            <section class="admin-panel admin-form-panel">
                <div class="admin-panel__header">
                    <h2>Datos de la categoría</h2>
                    <p class="admin-panel__intro">
                        Los campos marcados con <span class="admin-required">*</span> son obligatorios.
                    </p>
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
                        <p class="admin-field__help">Ejemplo: Pequeño liviano, Mediano o Grande pesado.</p>
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
                        <p class="admin-field__help">Valor interno utilizado para calcular el tramo de despacho.</p>
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
                        <p class="admin-field__help">No son medidas exactas; es una clasificación logística amigable.</p>
                        <?php if (isset($errors['tamano'])): ?>
                            <span class="admin-field__error"><?= escape((string) $errors['tamano']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="admin-status-control admin-field--full">
                        <div class="admin-status-control__copy">
                            <strong>Estado de la categoría</strong>
                            <span id="activo-help">Al estar activa, podrá seleccionarse en las asignaciones de productos.</span>
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
                        Guardar categoría
                    </button>
                </div>
            </section>
        </form>
    </div>

    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
