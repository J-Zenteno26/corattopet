<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-categoria-despacho.php';

requireAuthentication();

$categories = [];
$loadError = false;

try {
    $categories = listarCategoriasDespacho(database());
} catch (Throwable $exception) {
    $loadError = true;
    error_log('Shipping categories list error: ' . $exception->getMessage());
}

if ($loadError) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible cargar las categorías de despacho',
        'message' => 'Intenta nuevamente más tarde.',
        'primaryText' => 'Aceptar',
    ];
}

$csrfToken = csrfToken();
$pageTitle = 'Categorías de despacho';
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
            <span class="admin-shipping-eyebrow">Configuración logística</span>
            <h1 class="admin-page-title admin-page-title--paw">Categorías de despacho</h1>
            <p>Clasifica productos sin peso definido usando una estimación simple de peso y tamaño.</p>
        </div>

        <div class="admin-actions">
            <a
                class="admin-button admin-button--primary"
                href="<?= escape(appUrl('admin/despachos/categorias/crear.php')) ?>"
            >
                Agregar categoría
            </a>
            <a
                class="admin-button"
                href="<?= escape(appUrl('admin/despachos/asignaciones/index.php')) ?>"
            >
                Asignar productos
            </a>
        </div>
    </header>

    <section class="admin-panel admin-panel--soft" aria-label="Listado de categorías de despacho">
        <div class="admin-panel__header">
            <h2>Clasificaciones disponibles</h2>
            <p class="admin-panel__intro">
                Una categoría puede agrupar varios productos. Cada producto podrá tener una sola categoría de despacho.
            </p>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-shipping-table">
                <thead>
                    <tr>
                        <th>NOMBRE</th>
                        <th>PESO ESTIMADO</th>
                        <th>TAMAÑO</th>
                        <th>PRODUCTOS</th>
                        <th>ESTADO</th>
                        <th>ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <?php
                        $active = booleanoPostgresMantenedor($category['activo']);
                        $assignedProducts = (int) $category['productos_asignados'];
                        $formId = 'estado-categoria-despacho-' . (int) $category['id_categoria_despacho'];
                        ?>
                        <tr>
                            <td>
                                <strong class="admin-shipping-category-name">
                                    <?= escape((string) $category['nombre']) ?>
                                </strong>
                            </td>
                            <td><?= escape(formatearPesoDespacho((int) $category['peso_estimado_gramos'])) ?></td>
                            <td>
                                <span class="admin-shipping-size admin-shipping-size--<?= escape((string) $category['tamano']) ?>">
                                    <?= escape(nombreTamanoDespacho((string) $category['tamano'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="admin-shipping-count">
                                    <?= $assignedProducts ?> producto<?= $assignedProducts === 1 ? '' : 's' ?>
                                </span>
                            </td>
                            <td>
                                <span class="admin-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>">
                                    <?= $active ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td>
                                <div class="admin-actions-inline">
                                    <a
                                        class="admin-button admin-button--small admin-button--primary"
                                        href="<?= escape(appUrl(
                                            'admin/despachos/categorias/editar.php?id=' . (int) $category['id_categoria_despacho']
                                        )) ?>"
                                    >
                                        Editar
                                    </a>

                                    <form
                                        id="<?= escape($formId) ?>"
                                        method="post"
                                        action="<?= escape(appUrl('admin/despachos/categorias/cambiar-estado.php')) ?>"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                        <input
                                            type="hidden"
                                            name="id_categoria_despacho"
                                            value="<?= (int) $category['id_categoria_despacho'] ?>"
                                        >

                                        <button
                                            class="admin-button admin-button--small"
                                            type="button"
                                            data-admin-confirm-form="<?= escape($formId) ?>"
                                            data-modal-title="<?= $active ? 'Desactivar categoría' : 'Activar categoría' ?>"
                                            data-modal-message="<?= $active
                                                ? 'La categoría dejará de estar disponible para nuevas asignaciones. Los productos ya asignados no se modificarán.'
                                                : 'La categoría volverá a estar disponible para asignar productos.' ?>"
                                            data-modal-primary="<?= $active ? 'Desactivar' : 'Activar' ?>"
                                            data-modal-destructive="<?= $active ? 'true' : 'false' ?>"
                                            data-modal-secondary="Cancelar"
                                        >
                                            <?= $active ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($categories === [] && !$loadError): ?>
                        <tr class="admin-empty-state">
                            <td colspan="6">
                                <strong>Aún no hay categorías de despacho</strong>
                                <span>Crea la primera categoría para comenzar a clasificar productos.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
