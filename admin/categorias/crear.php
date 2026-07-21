<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-categoria.php';

requireAuthentication();

$state = consumirEstadoMantenedor('categoria_crear');

$values = array_merge(
    valoresInicialesCategoria(),
    $state['valores'] ?? []
);

$errors = is_array($state['errores'] ?? null)
    ? $state['errores']
    : [];

$generalError = is_string($state['error_general'] ?? null)
    ? $state['error_general']
    : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';

if ($errors !== [] || $generalError !== null) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible guardar la categoría',
        'message' => $errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.',
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
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
            <a
                class="admin-back-link"
                href="<?= escape(appUrl('admin/categorias/index.php')) ?>"
            >
                ← Volver a categorías
            </a>

            <h1 class="admin-page-title admin-page-title--paw">
                Agregar categoría
            </h1>

            <p>
                Registra una categoría para organizar los productos.
            </p>
        </div>
    </header>

    <div class="admin-form-layout">

        <form
            class="admin-form-layout__form"
            method="post"
            action="<?= escape(appUrl('admin/categorias/guardar.php')) ?>"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($csrfToken) ?>"
            >

            <section class="admin-panel admin-form-panel">

                <div class="admin-panel__header">
                    <h2>Información de la categoría</h2>

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

                    <div
                        class="admin-field admin-field--full<?= isset($errors['descripcion'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="descripcion">
                            Descripción
                        </label>

                        <textarea
                            id="descripcion"
                            name="descripcion"
                            maxlength="1000"
                            rows="4"
                            <?= isset($errors['descripcion'])
                                ? 'aria-invalid="true" aria-describedby="descripcion-help descripcion-error"'
                                : 'aria-describedby="descripcion-help"' ?>
                        ><?php echo escape((string) $values['descripcion']); ?></textarea>

                        <p class="admin-field__help" id="descripcion-help">
                            Puedes indicar qué tipos de productos pertenecen a esta categoría.
                        </p>

                        <?php if (isset($errors['descripcion'])): ?>
                            <span class="admin-field__error" id="descripcion-error">
                                <?= escape((string) $errors['descripcion']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div
                        class="admin-field<?= isset($errors['orden'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="orden">
                            Orden
                            <span class="admin-required" aria-hidden="true">*</span>
                        </label>

                        <input
                            id="orden"
                            name="orden"
                            type="number"
                            min="0"
                            step="1"
                            required
                            value="<?= escape((string) $values['orden']) ?>"
                            <?= isset($errors['orden'])
                                ? 'aria-invalid="true" aria-describedby="orden-help orden-error"'
                                : 'aria-describedby="orden-help"' ?>
                        >

                        <p class="admin-field__help" id="orden-help">
                            Los números menores aparecerán primero.
                        </p>

                        <?php if (isset($errors['orden'])): ?>
                            <span class="admin-field__error" id="orden-error">
                                <?= escape((string) $errors['orden']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="admin-status-control">
                        <div class="admin-status-control__copy">
                            <strong>Categoría fraccionable</strong>
                            <span id="fraccionamiento-help">Los productos de esta categoría administran stock en gramos y pueden venderse por presentaciones.</span>
                        </div>
                        <label class="admin-switch" for="maneja_fraccionamiento">
                            <input id="maneja_fraccionamiento" name="maneja_fraccionamiento" type="checkbox" value="1" aria-describedby="fraccionamiento-help" <?= $values['maneja_fraccionamiento'] ? 'checked' : '' ?>>
                            <span class="admin-switch__track" aria-hidden="true"></span>
                            <span class="admin-switch__label">Usa stock por peso</span>
                        </label>
                    </div>

                    <div class="admin-status-control">
                        <div class="admin-status-control__copy">
                            <strong>Estado de la categoría</strong>

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
                                Categoría activa
                            </span>
                        </label>
                    </div>

                </div>

                <div class="admin-form-actions admin-form-actions--inside">
                    <a
                        class="admin-button"
                        href="<?= escape(appUrl('admin/categorias/index.php')) ?>"
                    >
                        Cancelar
                    </a>

                    <button
                        class="admin-button admin-button--primary"
                        type="submit"
                    >
                        Guardar categoría
                    </button>
                </div>

            </section>
        </form>

    </div>

<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
