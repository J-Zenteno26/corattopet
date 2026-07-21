<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';

requireAuthentication();

$error = $_SESSION['inventario_importacion_error'] ?? null;
unset($_SESSION['inventario_importacion_error']);
$adminModal = is_string($error) && $error !== '' ? [
    'type' => 'error', 'title' => 'No fue posible procesar el archivo',
    'message' => $error, 'primaryText' => 'Volver al formulario',
] : null;
$csrfToken = csrfToken();
$pageTitle = 'Importar productos desde Excel';
$activeSection = 'inventario';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-title">Importar productos desde Excel</h1>
            <p>Valida productos, alimentos fraccionables y sus presentaciones antes de incorporarlos al inventario.</p>
        </div>
        <div class="admin-actions" aria-label="Acciones de importación">
            <a class="admin-button" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">Volver a Inventario</a>
            <a class="admin-button admin-button--dark" href="<?= escape(appUrl('admin/inventario/importar/descargar-plantilla.php')) ?>">Descargar plantilla Excel</a>
        </div>
    </header>

    <section class="admin-panel" aria-labelledby="subir-excel-title">
        <div class="admin-panel__header">
            <div>
                <h2 id="subir-excel-title">Selecciona el archivo XLSX</h2>
                <p>El archivo debe incluir las hojas Productos y Presentaciones. El límite es de 5 MB.</p>
            </div>
        </div>
        <form class="admin-form-panel" method="post" enctype="multipart/form-data" action="<?= escape(appUrl('admin/inventario/importar/validar.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <div class="admin-field">
                <label for="archivo-excel">Archivo Excel</label>
                <input id="archivo-excel" name="archivo_excel" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                <span class="admin-field__help">La carga solo prepara una previsualización; todavía no inserta información.</span>
            </div>
            <div class="admin-form-actions admin-form-actions--inside">
                <button class="admin-button admin-button--primary" type="submit">Validar archivo</button>
            </div>
        </form>
    </section>
<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
