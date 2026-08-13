<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 3);

require_once $projectRoot . '/config/app.php';
require_once $projectRoot . '/shared/seguridad.php';
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-tarifas-despacho.php';

requireAuthentication();

$filters = filtrosTarifasDespacho($_GET);
$regions = [];
$communes = [];
$summary = [
    'comunas_catalogo' => 0,
    'comunas_con_tarifa' => 0,
    'tarifas_activas' => 0,
];
$listing = [
    'registros' => [],
    'total' => 0,
    'pagina' => 1,
    'paginas' => 1,
    'por_pagina' => $filters['por_pagina'],
];
$loadError = false;

try {
    $connection = database();
    $regions = listarRegionesTarifasDespacho($connection);
    $communes = listarComunasFiltroTarifasDespacho(
        $connection,
        $filters['id_region']
    );
    $summary = resumenTarifasDespacho($connection);
    $listing = listarTarifasDespacho($connection, $filters);
} catch (Throwable $exception) {
    $loadError = true;
    error_log('Shipping tariffs list error: ' . $exception->getMessage());
}

if ($loadError) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible cargar las tarifas',
        'message' => 'Intenta nuevamente más tarde.',
        'primaryText' => 'Aceptar',
    ];
}

$hasFilters = $filters['id_region'] !== null
    || $filters['id_comuna'] !== null
    || $filters['buscar'] !== '';

$returnQuery = http_build_query(array_filter(
    $filters,
    static fn(mixed $value): bool => $value !== null && $value !== ''
));

$csrfToken = csrfToken();
$pageTitle = 'Tarifas de despacho';
$activeSection = 'despachos';
$cssPath = $projectRoot . '/public/css/admin-despachos-tarifas.css';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : '1';
$jsPath = $projectRoot . '/public/js/admin-despachos-tarifas.js';
$jsVersion = is_file($jsPath) ? (string) filemtime($jsPath) : '1';

require $projectRoot . '/shared/admin-header.php';
require $projectRoot . '/shared/admin-sidebar.php';
?>
<link
    rel="stylesheet"
    href="<?= escape(appUrl('public/css/admin-despachos-tarifas.css') . '?v=' . $cssVersion) ?>"
>

<main class="admin-main shipping-tariffs-page" id="contenido-principal">
    <header class="admin-page-header shipping-tariffs-header">
        <div>
            <span class="shipping-tariffs-eyebrow">Configuración logística</span>
            <h1 class="admin-page-title admin-page-title--paw">Tarifas de despacho</h1>
            <p>Edita los valores estimados por comuna y tramo de peso antes del pago en Webpay.</p>
        </div>

        <div class="admin-actions">
            <a
                class="admin-button"
                href="<?= escape(appUrl('admin/despachos/categorias/index.php')) ?>"
            >
                Categorías
            </a>
            <a
                class="admin-button"
                href="<?= escape(appUrl('admin/despachos/asignaciones/index.php')) ?>"
            >
                Asignar productos
            </a>
        </div>
    </header>

    <section class="shipping-tariffs-summary" aria-label="Resumen de tarifas">
        <article class="admin-stat-card">
            <span>Comunas del catálogo</span>
            <strong><?= (int) $summary['comunas_catalogo'] ?></strong>
            <small>Desde RM hasta Los Ríos</small>
        </article>
        <article class="admin-stat-card">
            <span>Comunas con tarifa</span>
            <strong><?= (int) $summary['comunas_con_tarifa'] ?></strong>
            <small>Con al menos un tramo activo</small>
        </article>
        <article class="admin-stat-card">
            <span>Tarifas activas</span>
            <strong><?= (int) $summary['tarifas_activas'] ?></strong>
            <small>S, M, L y XL</small>
        </article>
    </section>

    <section class="admin-panel admin-panel--soft shipping-tariffs-panel">
        <div class="admin-panel__header shipping-tariffs-panel__header">
            <div>
                <h2>Valores por comuna</h2>
                <p>El peso tarifable corresponde al peso total del carrito más un 10% de seguridad.</p>
            </div>
        </div>

        <form class="shipping-tariffs-filters" method="get">
            <div class="admin-field">
                <label for="shipping-region">Región</label>
                <select id="shipping-region" name="id_region">
                    <option value="">Todas</option>
                    <?php foreach ($regions as $region): ?>
                        <?php $regionId = (int) $region['id_region']; ?>
                        <option
                            value="<?= $regionId ?>"
                            <?= $filters['id_region'] === $regionId ? 'selected' : '' ?>
                        >
                            <?= escape((string) $region['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field">
                <label for="shipping-commune">Comuna</label>
                <select id="shipping-commune" name="id_comuna">
                    <option value="">Todas</option>
                    <?php foreach ($communes as $commune): ?>
                        <?php $communeId = (int) $commune['id_comuna']; ?>
                        <option
                            value="<?= $communeId ?>"
                            <?= $filters['id_comuna'] === $communeId ? 'selected' : '' ?>
                        >
                            <?= escape((string) $commune['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field shipping-tariffs-search">
                <label for="shipping-search">Buscar comuna</label>
                <input
                    id="shipping-search"
                    type="search"
                    name="buscar"
                    value="<?= escape($filters['buscar']) ?>"
                    placeholder="Ej. Quillón"
                >
            </div>

            <div class="admin-field">
                <label for="shipping-per-page">Mostrar</label>
                <select id="shipping-per-page" name="por_pagina">
                    <?php foreach ([20, 40, 80] as $perPage): ?>
                        <option
                            value="<?= $perPage ?>"
                            <?= $filters['por_pagina'] === $perPage ? 'selected' : '' ?>
                        >
                            <?= $perPage ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="shipping-tariffs-filter-actions">
                <button class="admin-button admin-button--primary" type="submit">Filtrar</button>
                <?php if ($hasFilters): ?>
                    <a
                        class="admin-button"
                        href="<?= escape(appUrl('admin/despachos/tarifas/index.php')) ?>"
                    >
                        Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($loadError): ?>
            <div class="shipping-tariffs-state" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <strong>No pudimos cargar las tarifas</strong>
                <span>Revisa la conexión e inténtalo nuevamente.</span>
            </div>
        <?php elseif ($listing['registros'] === []): ?>
            <div class="shipping-tariffs-state">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                <strong>No hay comunas para esta búsqueda</strong>
                <span>Ajusta los filtros para continuar.</span>
            </div>
        <?php else: ?>
            <form
                class="shipping-tariffs-form"
                data-tariffs-form
                data-save-url="<?= escape(appUrl('admin/despachos/tarifas/guardar-cambios.php')) ?>"
                novalidate
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($csrfToken) ?>"
                    data-csrf-token
                >

                <div class="shipping-tariffs-toolbar shipping-tariffs-toolbar--sticky">
                    <div class="shipping-tariffs-toolbar__copy">
                        <strong><?= (int) $listing['total'] ?> comunas encontradas</strong>
                        <span>
                            Modifica los valores necesarios. Solo se enviarán los campos marcados como editados.
                        </span>
                    </div>

                    <div class="shipping-tariffs-change-actions">
                        <div
                            class="shipping-tariffs-change-counter"
                            data-change-counter
                            aria-live="polite"
                        >
                            Sin cambios pendientes
                        </div>

                        <button
                            class="admin-button"
                            type="button"
                            data-discard-changes
                            disabled
                        >
                            Descartar
                        </button>

                        <button
                            class="admin-button admin-button--primary"
                            type="submit"
                            data-save-changes
                            disabled
                        >
                            Guardar cambios
                        </button>
                    </div>
                </div>

                <div
                    class="shipping-tariffs-feedback"
                    data-tariffs-feedback
                    role="status"
                    aria-live="polite"
                    hidden
                ></div>

                <div class="admin-table-wrap shipping-tariffs-table-wrap">
                    <table class="admin-table shipping-tariffs-table">
                        <thead>
                            <tr>
                                <th>REGIÓN</th>
                                <th>COMUNA</th>
                                <th>S<br><small>Hasta 3 kg</small></th>
                                <th>M<br><small>Hasta 6 kg</small></th>
                                <th>L<br><small>Hasta 16 kg</small></th>
                                <th>XL<br><small>Hasta 25 kg</small></th>
                                <th>GRATIS DESDE<br><small>Opcional</small></th>
                                <th>ACTIVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listing['registros'] as $row): ?>
                                <?php
                                $communeId = (int) $row['id_comuna'];
                                $configured = (int) $row['tramos_configurados'];
                                $active = $configured === 4
                                    && booleanoPostgresMantenedor($row['tarifas_activas']);
                                ?>
                                <tr
                                    data-tariff-row
                                    data-commune-id="<?= $communeId ?>"
                                >
                                    <td data-label="Región">
                                        <span class="shipping-tariffs-region">
                                            <?= escape((string) $row['region']) ?>
                                        </span>
                                    </td>

                                    <td data-label="Comuna">
                                        <strong><?= escape((string) $row['comuna']) ?></strong>

                                        <span
                                            class="shipping-tariffs-row-status"
                                            data-row-status
                                            hidden
                                        >
                                            Editada
                                        </span>

                                        <?php if ($configured < 4): ?>
                                            <small class="shipping-tariffs-warning">
                                                <?= $configured ?>/4 tramos configurados
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <?php foreach ([
                                        3000 => 'valor_s',
                                        6000 => 'valor_m',
                                        16000 => 'valor_l',
                                        25000 => 'valor_xl',
                                    ] as $weight => $field): ?>
                                        <?php $value = formatearValorTarifaDespacho($row[$field]); ?>

                                        <td data-label="<?= escape(TRAMOS_TARIFA_DESPACHO[$weight]) ?>">
                                            <label class="shipping-tariffs-money">
                                                <span aria-hidden="true">$</span>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="1000000"
                                                    step="100"
                                                    value="<?= escape($value) ?>"
                                                    data-tariff-input
                                                    data-commune-id="<?= $communeId ?>"
                                                    data-weight="<?= $weight ?>"
                                                    data-original="<?= escape($value) ?>"
                                                    aria-label="Tarifa <?= escape(TRAMOS_TARIFA_DESPACHO[$weight]) ?> para <?= escape((string) $row['comuna']) ?>"
                                                    required
                                                >
                                            </label>
                                        </td>
                                    <?php endforeach; ?>

                                    <?php
                                    $freeShippingValue = formatearValorTarifaDespacho(
                                        $row['monto_envio_gratis'] ?? null
                                    );
                                    ?>
                                    <td data-label="Gratis desde">
                                        <label class="shipping-tariffs-money shipping-tariffs-money--free">
                                            <span aria-hidden="true">$</span>
                                            <input
                                                type="number"
                                                min="0"
                                                max="10000000"
                                                step="1000"
                                                value="<?= escape($freeShippingValue) ?>"
                                                placeholder="Sin beneficio"
                                                data-free-shipping-input
                                                data-commune-id="<?= $communeId ?>"
                                                data-original="<?= escape($freeShippingValue) ?>"
                                                aria-label="Despacho gratis desde para <?= escape((string) $row['comuna']) ?>"
                                            >
                                        </label>
                                        <small class="shipping-tariffs-free-help">Vacío = no aplica</small>
                                    </td>

                                    <td data-label="Activa">
                                        <label class="shipping-tariffs-toggle">
                                            <input
                                                type="checkbox"
                                                value="1"
                                                data-active-input
                                                data-commune-id="<?= $communeId ?>"
                                                data-original="<?= $active ? '1' : '0' ?>"
                                                <?= $active ? 'checked' : '' ?>
                                            >
                                            <span data-active-label>
                                                <?= $active ? 'Sí' : 'No' ?>
                                            </span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="shipping-tariffs-toolbar shipping-tariffs-toolbar--bottom">
                    <span>
                        Página <?= (int) $listing['pagina'] ?>
                        de <?= (int) $listing['paginas'] ?>
                    </span>

                    <span>
                        Los cambios guardados se aplican sin recargar la página.
                    </span>
                </div>
            </form>

            <?php if ($listing['paginas'] > 1): ?>
                <nav class="admin-pagination" aria-label="Paginación de tarifas">
                    <?php if ($listing['pagina'] > 1): ?>
                        <a
                            href="<?= escape(urlPaginacionTarifasDespacho(
                                $filters,
                                $listing['pagina'] - 1
                            )) ?>"
                        >
                            Anterior
                        </a>
                    <?php endif; ?>

                    <span>
                        Página <?= (int) $listing['pagina'] ?>
                        de <?= (int) $listing['paginas'] ?>
                    </span>

                    <?php if ($listing['pagina'] < $listing['paginas']): ?>
                        <a
                            href="<?= escape(urlPaginacionTarifasDespacho(
                                $filters,
                                $listing['pagina'] + 1
                            )) ?>"
                        >
                            Siguiente
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php require $projectRoot . '/shared/admin-footer.php'; ?>

<script
    src="<?= escape(appUrl('public/js/admin-despachos-tarifas.js') . '?v=' . $jsVersion) ?>"
    defer
></script>
