<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/includes/consultas-importaciones.php';
requireAuthentication();
$filters = filtrosImportaciones($_GET);
$imports = [];
$summary = ['total' => 0, 'ultima' => null, 'procesados' => 0, 'incidencias' => 0];
$historyAvailable = false;
$loadError = false;
try {
    $connection = database();
    $historyAvailable = tablaImportacionesDisponible($connection);
    if ($historyAvailable) {
        $imports = obtenerImportaciones($connection, $filters);
        $summary = resumenImportaciones($connection);
    }
} catch (Throwable $exception) {
    error_log('Import history error: ' . $exception->getMessage());
    $loadError = true;
}
$hasFilters = implode('', $filters) !== '';
$pageTitle = 'Importaciones';
$activeSection = 'importaciones';
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
<header class="admin-page-header"><div><h1 class="admin-page-title">Importaciones</h1><p>Historial de cargas de productos y presentaciones realizadas mediante Excel.</p></div><div class="admin-actions"><a class="admin-button admin-button--primary" href="<?= escape(appUrl('admin/inventario/importar/index.php')) ?>">Nueva importación</a><a class="admin-button" href="<?= escape(appUrl('admin/importaciones/drive/index.php')) ?>">Importar imágenes desde Drive</a></div></header>
<section class="admin-import-history-summary" aria-label="Resumen de importaciones">
<article><span>Total importaciones</span><strong><?= escape((string) $summary['total']) ?></strong></article>
<article><span>Última importación</span><strong><?= $summary['ultima'] ? escape(date('d-m-Y H:i', strtotime((string) $summary['ultima']))) : 'Sin registros' ?></strong></article>
<article><span>Registros procesados</span><strong><?= escape(number_format((int) $summary['procesados'], 0, ',', '.')) ?></strong></article>
<article class="<?= (int) $summary['incidencias'] > 0 ? 'has-incidents' : '' ?>"><span>Errores y advertencias</span><strong><?= escape(number_format((int) $summary['incidencias'], 0, ',', '.')) ?></strong></article>
</section>
<section class="admin-panel admin-import-history-panel" aria-labelledby="history-title">
<div class="admin-panel__header"><div><h2 id="history-title">Registro de cargas</h2><p>Se muestran hasta las 250 importaciones más recientes.</p></div></div>
<form class="admin-import-filters" method="get" action="<?= escape(appUrl('admin/importaciones/index.php')) ?>">
<div class="admin-field admin-import-filter-search"><label for="q">Buscar archivo o tipo</label><input id="q" name="q" type="search" value="<?= escape($filters['q']) ?>" placeholder="Ej. productos_julio.xlsx"></div>
<div class="admin-field"><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><?php foreach (['cargado' => 'Cargado', 'procesando' => 'Procesando', 'completado' => 'Completado', 'error' => 'Con errores'] as $value => $label): ?><option value="<?= escape($value) ?>" <?= $filters['estado'] === $value ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></div>
<div class="admin-field"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= escape($filters['desde']) ?>"></div><div class="admin-field"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= escape($filters['hasta']) ?>"></div>
<div class="admin-import-filter-actions"><button class="admin-button admin-button--dark" type="submit">Filtrar</button><?php if ($hasFilters): ?><a class="admin-button" href="<?= escape(appUrl('admin/importaciones/index.php')) ?>">Limpiar</a><?php endif; ?></div>
</form>
<?php if ($loadError): ?>
<div class="admin-import-history-empty" role="alert"><span aria-hidden="true">!</span><strong>No fue posible cargar el historial</strong><p>Intenta nuevamente en unos minutos.</p></div>
<?php elseif (!$historyAvailable): ?>
<div class="admin-import-history-empty"><span aria-hidden="true">↥</span><strong>Aún no hay un historial disponible</strong><p>Al habilitar el registro, las próximas importaciones Excel aparecerán aquí.</p><a class="admin-button admin-button--primary" href="<?= escape(appUrl('admin/inventario/importar/index.php')) ?>">Ir a importar Excel</a></div>
<?php elseif ($imports === []): ?>
<div class="admin-import-history-empty"><span aria-hidden="true">↥</span><strong><?= $hasFilters ? 'No encontramos coincidencias' : 'Todavía no hay importaciones' ?></strong><p><?= $hasFilters ? 'Ajusta o limpia los filtros para volver a revisar el historial.' : 'La primera carga Excel completada quedará registrada en esta pantalla.' ?></p><?php if ($hasFilters): ?><a class="admin-button" href="<?= escape(appUrl('admin/importaciones/index.php')) ?>">Limpiar filtros</a><?php else: ?><a class="admin-button admin-button--primary" href="<?= escape(appUrl('admin/inventario/importar/index.php')) ?>">Realizar primera importación</a><?php endif; ?></div>
<?php else: ?>
<div class="admin-table-wrap"><table class="admin-table admin-table--mobile-cards admin-import-history-table"><thead><tr><th>Fecha</th><th>Archivo</th><th>Usuario</th><th>Tipo</th><th>Procesados</th><th>Exitosos</th><th>Errores</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($imports as $import): ?><tr>
<td data-label="Fecha"><time datetime="<?= escape((string) $import['creado_en']) ?>"><?= escape(date('d-m-Y H:i', strtotime((string) $import['creado_en']))) ?></time></td><td data-label="Archivo"><strong class="admin-import-file"><?= escape((string) $import['archivo']) ?></strong></td><td data-label="Usuario"><?= escape((string) $import['usuario']) ?></td><td data-label="Tipo"><?= escape((string) $import['tipo_importacion']) ?></td><td data-label="Procesados"><?= escape(number_format((int) $import['registros_procesados'], 0, ',', '.')) ?></td><td data-label="Exitosos"><?= escape(number_format((int) $import['registros_exitosos'], 0, ',', '.')) ?></td><td data-label="Errores"><?= escape((string) $import['errores']) ?><?= (int) $import['advertencias'] > 0 ? ' · ' . escape((string) $import['advertencias']) . ' adv.' : '' ?></td><td data-label="Estado"><span class="admin-import-status is-<?= escape((string) $import['estado']) ?>"><?= escape(etiquetaEstadoImportacionHistorial((string) $import['estado'])) ?></span></td><td data-label="Acciones"><a class="admin-button admin-button--small" href="<?= escape(appUrl('admin/importaciones/ver.php?id=' . (int) $import['id_importacion'])) ?>">Ver detalle</a></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?></section></main>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
