<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-marca.php';
require_once __DIR__ . '/consultas/listar-marcas.php';

requireAuthentication();

$brands = [];
$loadError = false;
try {
    $brands = listarMarcas(database());
} catch (Throwable $exception) {
    $loadError = true;
    $reference = registrarExcepcionAdmin('Brands list error', $exception);
    $adminModal = ['type' => 'error', 'title' => 'No fue posible cargar las marcas', 'message' => 'Intenta nuevamente más tarde.', 'reference' => $reference];
}

$formState = consumirEstadoMantenedor('marca_form');
$formValues = array_merge(valoresInicialesMarca(), is_array($formState['valores'] ?? null) ? $formState['valores'] : []);
$formErrors = is_array($formState['errores'] ?? null) ? $formState['errores'] : [];
$formGeneralError = is_string($formState['error_general'] ?? null) ? $formState['error_general'] : null;
$formReference = is_string($formState['referencia'] ?? null) ? $formState['referencia'] : '';
$formMode = ($formValues['_modo'] ?? null) === 'edit' ? 'edit' : 'create';
$fallbackEditId = idPositivoMarca($_GET['editar'] ?? null);
if ($formState === [] && $fallbackEditId !== null) {
    foreach ($brands as $brand) {
        if ((int) $brand['id_marca'] === $fallbackEditId) {
            $formMode = 'edit';
            $formValues = ['id_marca' => $fallbackEditId, 'nombre' => (string) $brand['nombre'], 'activo' => booleanoPostgresMantenedor($brand['activo'])];
            $formState = ['fallback' => true];
            break;
        }
    }
}
$shouldOpenForm = $formState !== [];
$csrfToken = csrfToken();
$pageTitle = 'Marcas';
$activeSection = 'marcas';

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div><h1 class="admin-page-title admin-page-title--paw">Marcas</h1><p>Administra las marcas disponibles para los productos.</p></div>
        <div class="admin-actions"><button type="button" class="admin-button admin-button--primary" data-brand-modal-open="create">Nueva marca</button></div>
    </header>

    <section class="admin-panel admin-panel--soft" aria-label="Listado de marcas">
        <div class="admin-panel__header"><h2>Lista de marcas</h2><p class="admin-panel__intro">Gestiona el nombre y el estado de cada marca disponible.</p></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th scope="col">Nombre</th><th scope="col">Estado</th><th scope="col">Actualizada</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($brands as $brand): ?>
                    <?php $active = booleanoPostgresMantenedor($brand['activo']); $brandId = (int) $brand['id_marca']; $stateFormId = 'estado-marca-' . $brandId; ?>
                    <tr>
                        <td><?= escape((string) $brand['nombre']) ?></td>
                        <td><span class="admin-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>"><?= $active ? 'Activa' : 'Inactiva' ?></span></td>
                        <td><?= escape(formatearFechaMantenedor($brand['actualizado_en'])) ?></td>
                        <td><div class="admin-actions-inline">
                            <button type="button" class="admin-button admin-button--small admin-button--primary" data-brand-modal-open="edit" data-brand-id="<?= $brandId ?>" data-brand-name="<?= escape((string) $brand['nombre']) ?>" data-brand-active="<?= $active ? '1' : '0' ?>" aria-label="Editar marca <?= escape((string) $brand['nombre']) ?>">Editar</button>
                            <form id="<?= escape($stateFormId) ?>" method="post" action="<?= escape(appUrl('admin/marcas/cambiar-estado.php')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="id_marca" value="<?= $brandId ?>">
                                <button class="admin-button admin-button--small" type="button" data-admin-confirm-form="<?= escape($stateFormId) ?>" data-modal-title="<?= $active ? 'Desactivar marca' : 'Activar marca' ?>" data-modal-message="<?= $active ? 'La marca dejará de estar disponible para nuevos productos. Los productos existentes no se eliminarán.' : 'La marca volverá a estar disponible para nuevos productos.' ?>" data-modal-primary="<?= $active ? 'Desactivar' : 'Activar' ?>" data-modal-secondary="Cancelar" data-modal-destructive="<?= $active ? 'true' : 'false' ?>"><?= $active ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($brands === [] && !$loadError): ?><tr class="admin-empty-state"><td colspan="4"><strong>Aún no hay marcas registradas</strong><span>Crea la primera marca para asociarla a los productos.</span></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <template id="brand-form-template">
        <form class="admin-modal-form" data-brand-form method="post" data-create-action="<?= escape(appUrl('admin/marcas/guardar.php')) ?>" data-edit-action="<?= escape(appUrl('admin/marcas/actualizar.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input type="hidden" name="id_marca" value="">
            <div class="admin-field admin-field--full<?= isset($formErrors['nombre']) ? ' admin-field--invalid' : '' ?>">
                <label for="marca-modal-nombre">Nombre marca <span class="admin-required" aria-hidden="true">*</span></label>
                <input id="marca-modal-nombre" name="nombre" type="text" maxlength="120" required autocomplete="off" value="<?= escape((string) ($formValues['nombre'] ?? '')) ?>" <?= isset($formErrors['nombre']) ? 'aria-invalid="true" aria-describedby="marca-modal-help marca-modal-error"' : 'aria-describedby="marca-modal-help"' ?>>
                <p class="admin-field__help" id="marca-modal-help">Será el nombre visible al administrar y clasificar los productos.</p>
                <?php if (isset($formErrors['nombre'])): ?><span class="admin-field__error" id="marca-modal-error"><?= escape((string) $formErrors['nombre']) ?></span><?php endif; ?>
            </div>
            <div class="admin-status-control"><div class="admin-status-control__copy"><strong>Estado de la marca</strong><span id="marca-modal-activo-help">Al estar activa, podrá asignarse a nuevos productos.</span></div><label class="admin-switch" for="marca-modal-activo"><input id="marca-modal-activo" name="activo" type="checkbox" value="1" aria-describedby="marca-modal-activo-help" <?= ($formValues['activo'] ?? true) ? 'checked' : '' ?>><span class="admin-switch__track" aria-hidden="true"></span><span class="admin-switch__label">Marca activa</span></label></div>
            <div class="admin-modal-form__actions"><button class="admin-modal__button admin-modal__button--secondary" type="button" data-admin-modal-cancel>Cancelar</button><button class="admin-modal__button admin-modal__button--primary" type="submit" data-brand-submit>Guardar marca</button></div>
        </form>
    </template>

    <?php if ($shouldOpenForm): ?>
        <script id="brand-modal-state" type="application/json"><?= json_encode(['mode' => $formMode, 'id' => (int) ($formValues['id_marca'] ?? 0), 'name' => (string) ($formValues['nombre'] ?? ''), 'active' => (bool) ($formValues['activo'] ?? true), 'hasErrors' => $formErrors !== [] || $formGeneralError !== null, 'hasFieldErrors' => $formErrors !== [], 'detail' => resumenErroresFormulario($formErrors, $formGeneralError), 'reference' => $formReference], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
    <?php endif; ?>

<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
