<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/includes/consultas-importaciones.php';
requireAuthentication();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$import = null;
if (is_int($id)) {
    try {
        $connection = database();
        if (tablaImportacionesDisponible($connection)) {
            $statement = $connection->prepare("SELECT i.id_importacion, i.nombre_archivo AS archivo, 'Productos y presentaciones' AS tipo_importacion, i.total_filas AS registros_procesados, (i.productos_creados + i.productos_actualizados) AS registros_exitosos, i.filas_con_error AS errores, 0 AS advertencias, i.estado, i.resumen_errores AS resumen, i.creado_en, COALESCE(u.nombre, 'Usuario no disponible') AS usuario FROM importaciones i LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario WHERE i.id_importacion = :id");
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();
            $import = is_array($row) ? $row : null;
        }
    } catch (Throwable $exception) {
        error_log('Import detail error: ' . $exception->getMessage());
    }
}
if ($import === null)
    http_response_code(404);
$detail = $import !== null ? json_decode((string) $import['resumen'], true) : [];
$detail = is_array($detail) ? $detail : [];
$pageTitle = 'Detalle de importación';
$activeSection = 'importaciones';
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div><a class="admin-back-link" href="<?= escape(appUrl('admin/importaciones/index.php')) ?>">← Volver a
                importaciones</a>
            <h1 class="admin-page-title">Detalle de importación</h1>
            <p><?= $import ? escape((string) $import['archivo']) : 'El registro solicitado no está disponible.' ?></p>
        </div>
    </header>
    <?php if ($import === null): ?>
        <section class="admin-panel">
            <div class="admin-import-history-empty"><span aria-hidden="true">!</span><strong>Importación no
                    encontrada</strong>
                <p>Puede haber sido eliminada o la dirección no es válida.</p>
            </div>
        </section>
    <?php else: ?>
        <section class="admin-import-detail-grid" aria-label="Datos de importación">
            <article>
                <span>Fecha</span><strong><?= escape(date('d-m-Y H:i', strtotime((string) $import['creado_en']))) ?></strong>
            </article>
            <article><span>Usuario</span><strong><?= escape((string) $import['usuario']) ?></strong></article>
            <article><span>Tipo</span><strong><?= escape((string) $import['tipo_importacion']) ?></strong></article>
            <article><span>Estado</span><strong><span
                        class="admin-import-status is-<?= escape((string) $import['estado']) ?>"><?= escape(etiquetaEstadoImportacionHistorial((string) $import['estado'])) ?></span></strong>
            </article>
            <article><span>Procesados</span><strong><?= escape((string) $import['registros_procesados']) ?></strong>
            </article>
            <article><span>Exitosos</span><strong><?= escape((string) $import['registros_exitosos']) ?></strong></article>
            <article><span>Errores</span><strong><?= escape((string) $import['errores']) ?></strong></article>
            <article><span>Advertencias</span><strong><?= escape((string) $import['advertencias']) ?></strong></article>
        </section>
        <?php if ($detail !== []): ?>
            <section class="admin-panel">
                <div class="admin-panel__header">
                    <div>
                        <h2>Resumen procesado</h2>
                        <p>Conteos guardados al confirmar el archivo.</p>
                    </div>
                </div>
                <dl class="admin-import-detail-summary"><?php foreach ($detail as $key => $value):
                    if (is_scalar($value)): ?>
                            <div>
                                <dt><?= escape(ucfirst(str_replace('_', ' ', (string) $key))) ?></dt>
                                <dd><?= escape((string) $value) ?></dd>
                            </div><?php endif; endforeach; ?>
                </dl>
            </section><?php endif; endif; ?>
</main>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>