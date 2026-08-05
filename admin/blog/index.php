<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/includes/funciones-blog.php';
require_once __DIR__ . '/includes/consultas-blog.php';

requireRoles(['administrador', 'Blog']);

$parameters = normalizarParametrosBlog($_GET);
$metrics = ['total' => 0, 'publicados' => 0, 'borradores' => 0, 'destacados' => 0];
$categories = [];
$listing = [
    'registros' => [],
    'total_registros' => 0,
    'total_paginas' => 1,
    'pagina_actual' => 1,
    'por_pagina' => $parameters['por_pagina'],
];
$databaseError = false;

try {
    $connection = database();
    $metrics = obtenerMetricasBlog($connection);
    $categories = listarCategoriasFiltroBlog($connection);
    $listing = listarArticulosBlog($connection, $parameters);
} catch (Throwable $exception) {
    $databaseError = true;
    error_log('Blog listing query error: ' . $exception->getMessage());
}

$parameters['pagina'] = $listing['pagina_actual'];
$hasActiveFilters = hayFiltrosBlogActivos($parameters);
$firstRecord = $listing['total_registros'] === 0
    ? 0
    : (($listing['pagina_actual'] - 1) * $listing['por_pagina']) + 1;
$lastRecord = min(
    $listing['pagina_actual'] * $listing['por_pagina'],
    $listing['total_registros']
);
$pageTitle = 'Blog';
$activeSection = 'blog';
$csrfToken = csrfToken();
$blogCssPath = dirname(__DIR__, 2) . '/public/css/admin-blog.css';
$blogCssVersion = is_file($blogCssPath) ? (string) filemtime($blogCssPath) : '1';
$blogPreviewJsPath = dirname(__DIR__, 2) . '/public/js/admin-blog-vista-previa.js';
$blogPreviewJsVersion = is_file($blogPreviewJsPath) ? (string) filemtime($blogPreviewJsPath) : '1';
$previewModalCssPath = dirname(__DIR__, 2) . '/public/css/admin-blog-vista-previa.css';
$previewModalCssVersion = is_file($previewModalCssPath)
    ? (string) filemtime($previewModalCssPath)
    : '1';

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= escape(appUrl('public/css/admin-blog.css') . '?v=' . $blogCssVersion) ?>">
<link
    rel="stylesheet"
    href="<?= escape(appUrl('public/css/admin-blog-vista-previa.css') . '?v=' . $previewModalCssVersion) ?>"
>
<main class="admin-main admin-blog" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-title admin-page-title--paw">Blog</h1>
            <p>Gestiona los artículos educativos de Coratto Pet.</p>
        </div>
        <a class="admin-button admin-button--primary" href="<?= escape(appUrl('admin/blog/crear.php')) ?>">Nuevo artículo</a>
    </header>

    <section class="admin-summary-grid admin-blog-metrics" aria-label="Resumen del blog">
        <?php foreach ([
            'Total' => $metrics['total'],
            'Publicados' => $metrics['publicados'],
            'Borradores' => $metrics['borradores'],
            'Destacados' => $metrics['destacados'],
        ] as $label => $value): ?>
            <article class="admin-summary-card">
                <span><?= escape($label) ?></span>
                <strong><?= escape((string) $value) ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="admin-panel admin-panel--soft">
        <form class="admin-toolbar admin-blog-filters" method="get" action="<?= escape(appUrl('admin/blog/index.php')) ?>">
            <input type="hidden" name="por_pagina" value="<?= escape((string) $parameters['por_pagina']) ?>">
            <div class="admin-field admin-field--search">
                <label for="blog-search">Buscar</label>
                <input
                    id="blog-search"
                    name="buscar"
                    type="search"
                    maxlength="120"
                    value="<?= escape($parameters['buscar']) ?>"
                    placeholder="Título del artículo"
                >
            </div>
            <div class="admin-field">
                <label for="blog-state">Estado</label>
                <select id="blog-state" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['publicado' => 'Publicados', 'borrador' => 'Borradores', 'archivado' => 'Archivados'] as $value => $label): ?>
                        <option value="<?= escape($value) ?>" <?= $parameters['estado'] === $value ? 'selected' : '' ?>>
                            <?= escape($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-field">
                <label for="blog-category">Categoría</label>
                <select id="blog-category" name="id_categoria_blog">
                    <option value="">Todas</option>
                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= escape((string) $category['id_categoria_blog']) ?>"
                            <?= $parameters['id_categoria_blog'] === (int) $category['id_categoria_blog'] ? 'selected' : '' ?>
                        ><?= escape((string) $category['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter-actions">
                <button class="admin-button admin-button--primary" type="submit">Filtrar</button>
                <?php if ($hasActiveFilters): ?>
                    <a class="admin-button" href="<?= escape(appUrl('admin/blog/index.php')) ?>">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="admin-panel admin-blog-panel">
        <div class="admin-panel__header">
            <h2>Artículos</h2>
            <p><?= escape((string) $listing['total_registros']) ?> artículo(s)</p>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table admin-table--mobile-cards admin-blog-table">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th>Categoría</th>
                        <th>Autor</th>
                        <th>Estado</th>
                        <th>Publicación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listing['registros'] as $article): ?>
                        <?php $coverUrl = urlPortadaBlog($article['imagen_portada'] ?? null); ?>
                        <tr>
                            <td data-label="Artículo">
                                <div class="admin-product-cell">
                                    <?php if ($coverUrl !== null): ?>
                                        <img
                                            class="admin-product-thumb"
                                            src="<?= escape($coverUrl) ?>"
                                            alt=""
                                            width="48"
                                            height="48"
                                            loading="lazy"
                                        >
                                    <?php endif; ?>
                                    <div class="admin-product-main">
                                        <strong class="admin-product-name"><?= escape((string) $article['titulo']) ?></strong>
                                        <span class="admin-product-kind"><?= escape((string) $article['extracto']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Categoría"><?= escape((string) $article['categoria']) ?></td>
                            <td data-label="Autor"><?= escape((string) $article['autor']) ?></td>
                            <td data-label="Estado">
                                <span class="admin-status-badge <?= escape(claseEstadoBlog((string) $article['estado'])) ?>">
                                    <?= escape(etiquetaEstadoBlog((string) $article['estado'])) ?>
                                </span>
                                <?php if (filter_var($article['destacado'], FILTER_VALIDATE_BOOLEAN)): ?>
                                    <small class="admin-blog-featured">Destacado</small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Publicación"><?= escape(formatearFechaBlog($article['fecha_publicacion'])) ?></td>
                            <td data-label="Acciones">
                                <?php $articleState = (string) $article['estado']; ?>
                                <div class="admin-actions-inline">
                                    <a class="admin-button admin-button--small" href="<?= escape(appUrl('admin/blog/editar.php?id=' . (int) $article['id_articulo'])) ?>">Editar</a>
                                    <button class="admin-button admin-button--small" type="button" data-blog-preview-open data-preview-url="<?= escape(appUrl('admin/blog/vista-previa.php?id=' . (int) $article['id_articulo'])) ?>">Vista previa</button>
                                    <?php if ($articleState === 'borrador'): ?>
                                        <form method="post" action="<?= escape(appUrl('admin/blog/publicar.php')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= escape((string) $article['id_articulo']) ?>">
                                            <button class="admin-button admin-button--small" type="submit">Publicar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (in_array($articleState, ['borrador', 'publicado'], true)): ?>
                                        <form method="post" action="<?= escape(appUrl('admin/blog/archivar.php')) ?>" onsubmit="return confirm('¿Archivar este artículo?');">
                                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= escape((string) $article['id_articulo']) ?>">
                                            <button class="admin-button admin-button--small" type="submit">Archivar</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($articleState === 'publicado'): ?>
                                        <form method="post" action="<?= escape(appUrl('admin/blog/destacar.php')) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= escape((string) $article['id_articulo']) ?>">
                                            <button class="admin-button admin-button--small" type="submit"><?= filter_var($article['destacado'], FILTER_VALIDATE_BOOLEAN) ? 'Quitar destacado' : 'Destacar' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($listing['registros'] === []): ?>
                        <tr class="admin-empty-state admin-blog-empty">
                            <td colspan="6">
                                <strong><?= $databaseError ? 'No fue posible cargar los artículos' : ($hasActiveFilters ? 'No se encontraron artículos' : 'Aún no hay artículos') ?></strong>
                                <span><?= $databaseError ? 'Intenta nuevamente más tarde.' : ($hasActiveFilters ? 'Prueba con otros criterios de búsqueda.' : 'El listado se completará cuando se creen los primeros contenidos.') ?></span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!$databaseError): ?>
            <div class="admin-pagination">
                <p class="admin-pagination__summary">
                    Mostrando <?= escape((string) $firstRecord) ?> a <?= escape((string) $lastRecord) ?>
                    de <?= escape((string) $listing['total_registros']) ?> artículos
                </p>

                <nav class="admin-pagination__pages" aria-label="Paginación del blog">
                    <?php if ($listing['pagina_actual'] > 1): ?>
                        <a class="admin-pagination__button" href="<?= escape(construirUrlBlog($parameters, ['pagina' => $listing['pagina_actual'] - 1])) ?>" aria-label="Página anterior">‹</a>
                    <?php else: ?>
                        <span class="admin-pagination__button" aria-disabled="true">‹</span>
                    <?php endif; ?>

                    <?php
                    $firstPage = max(1, $listing['pagina_actual'] - 2);
                    $lastPage = min($listing['total_paginas'], $listing['pagina_actual'] + 2);
                    for ($page = $firstPage; $page <= $lastPage; $page++):
                    ?>
                        <?php if ($page === $listing['pagina_actual']): ?>
                            <span class="admin-pagination__button is-active" aria-current="page"><?= escape((string) $page) ?></span>
                        <?php else: ?>
                            <a class="admin-pagination__button" href="<?= escape(construirUrlBlog($parameters, ['pagina' => $page])) ?>"><?= escape((string) $page) ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($listing['pagina_actual'] < $listing['total_paginas']): ?>
                        <a class="admin-pagination__button" href="<?= escape(construirUrlBlog($parameters, ['pagina' => $listing['pagina_actual'] + 1])) ?>" aria-label="Página siguiente">›</a>
                    <?php else: ?>
                        <span class="admin-pagination__button" aria-disabled="true">›</span>
                    <?php endif; ?>
                </nav>

                <form class="admin-pagination__size" method="get" action="<?= escape(appUrl('admin/blog/index.php')) ?>">
                    <?php foreach (['buscar', 'estado', 'id_categoria_blog'] as $field): ?>
                        <?php if ($parameters[$field] !== '' && $parameters[$field] !== null): ?>
                            <input type="hidden" name="<?= escape($field) ?>" value="<?= escape((string) $parameters[$field]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label for="blog-per-page">Mostrar</label>
                    <select id="blog-per-page" name="por_pagina" onchange="this.form.submit()">
                        <?php foreach ([10, 20, 30] as $quantity): ?>
                            <option value="<?= $quantity ?>" <?= $parameters['por_pagina'] === $quantity ? 'selected' : '' ?>><?= $quantity ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <dialog class="blog-preview-modal" data-blog-preview-modal aria-labelledby="blog-preview-modal-title">
        <div class="blog-preview-modal__panel">
            <header class="blog-preview-modal__header">
                <h2 id="blog-preview-modal-title">Vista previa del artículo</h2>
                <button class="blog-preview-modal__close" type="button" data-blog-preview-close aria-label="Cerrar vista previa">×</button>
            </header>

            <p class="blog-preview-modal__loading" data-blog-preview-loading role="status">Cargando vista previa…</p>
            <p class="blog-preview-modal__error" data-blog-preview-error role="alert" hidden></p>

            <article class="blog-preview-modal__article" data-blog-preview-article hidden>
                <div class="blog-preview-modal__meta">
                    <span class="blog-preview-modal__state" data-blog-preview-state></span>
                    <span class="blog-preview-modal__category" data-blog-preview-category></span>
                </div>
                <h3 class="blog-preview-modal__title" data-blog-preview-title></h3>
                <pre class="blog-preview-modal__excerpt" data-blog-preview-excerpt></pre>
                <p class="blog-preview-modal__author">Por <span data-blog-preview-public-author></span></p>
                <p class="blog-preview-modal__responsible">Responsable: <span data-blog-preview-responsible></span></p>
                <img class="blog-preview-modal__cover" data-blog-preview-cover src="" alt="" hidden>
                <div class="blog-preview-modal__content" data-blog-preview-content></div>
                <p class="blog-preview-modal__video-wrap" data-blog-preview-video-wrap hidden>
                    <a class="blog-preview-modal__video" data-blog-preview-video href="" target="_blank" rel="noopener noreferrer">Ver video</a>
                </p>
            </article>

            <footer class="blog-preview-modal__actions">
                <button type="button" data-blog-preview-close>Cerrar</button>
            </footer>
        </div>
    </dialog>
</main>
<script src="<?= escape(appUrl('public/js/admin-blog-vista-previa.js') . '?v=' . $blogPreviewJsVersion) ?>" defer></script>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
