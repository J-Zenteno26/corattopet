<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-marca.php';

requireAuthentication();

$state = consumirEstadoMantenedor('marca_crear');

$values = array_merge(
    valoresInicialesMarca(),
    $state['valores'] ?? []
);

$errors = is_array($state['errores'] ?? null)
    ? $state['errores']
    : [];

$generalError = is_string($state['error_general'] ?? null)
    ? $state['error_general']
    : null;

$csrfToken = csrfToken();
$pageTitle = 'Marcas';
$activeSection = 'marcas';

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>

<main class="admin-main" id="contenido-principal">

    <header class="admin-page-header">
        <div>
            <a
                class="admin-back-link"
                href="<?= escape(appUrl('admin/marcas/index.php')) ?>"
            >
                ← Volver a marcas
            </a>

            <h1 class="admin-page-title admin-page-title--paw">
                Agregar marca
            </h1>

            <p>
                Registra una marca para asociarla a los productos.
            </p>
        </div>
    </header>

    <div class="admin-form-layout">

        <?php if ($errors !== [] || $generalError !== null): ?>
            <div class="admin-alert admin-alert--error" role="alert">
                <strong>No fue posible guardar la marca.</strong>

                <?php if ($generalError !== null): ?>
                    <p><?= escape($generalError) ?></p>
                <?php endif; ?>

                <?php if ($errors !== []): ?>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape((string) $error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form
            class="admin-form-layout__form"
            method="post"
            action="<?= escape(appUrl('admin/marcas/guardar.php')) ?>"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($csrfToken) ?>"
            >

            <section class="admin-panel admin-form-panel">

                <div class="admin-panel__header">
                    <h2>Información de la marca</h2>

                    <p class="admin-panel__intro">
                        Los campos marcados con
                        <span class="admin-required">*</span>
                        son obligatorios.
                    </p>
                </div>

                <div class="admin-form-grid">

                    <div
                        class="admin-field admin-field--full<?= isset($errors['nombre'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="nombre">
                            Nombre
                            <span class="admin-required" aria-hidden="true">*</span>
                        </label>

                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            maxlength="120"
                            required
                            autocomplete="off"
                            value="<?= escape((string) $values['nombre']) ?>"
                            <?= isset($errors['nombre'])
                                ? 'aria-invalid="true" aria-describedby="nombre-help nombre-error"'
                                : 'aria-describedby="nombre-help"' ?>
                        >

                        <p class="admin-field__help" id="nombre-help">
                            Será el nombre visible al administrar y clasificar los productos.
                        </p>

                        <?php if (isset($errors['nombre'])): ?>
                            <span class="admin-field__error" id="nombre-error">
                                <?= escape((string) $errors['nombre']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="admin-status-control admin-field--full">
                        <div class="admin-status-control__copy">
                            <strong>Estado de la marca</strong>

                            <span id="activo-help">
                                Al estar activa, podrá asignarse a nuevos productos.
                            </span>
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

                            <span
                                class="admin-switch__track"
                                aria-hidden="true"
                            ></span>

                            <span class="admin-switch__label">
                                Marca activa
                            </span>
                        </label>
                    </div>

                </div>

                <div class="admin-form-actions admin-form-actions--inside">
                    <a
                        class="admin-button"
                        href="<?= escape(appUrl('admin/marcas/index.php')) ?>"
                    >
                        Cancelar
                    </a>

                    <button
                        class="admin-button admin-button--primary"
                        type="submit"
                    >
                        Guardar marca
                    </button>
                </div>

            </section>
        </form>

    </div>

<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>