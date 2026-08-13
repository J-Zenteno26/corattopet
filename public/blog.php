<?php

declare(strict_types=1);

require __DIR__ . '/includes/public-page-bootstrap.php';
require_once dirname(__DIR__) . '/shared/funciones-contenido-blog.php';

function fechaBlogPublico(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value))->format('d-m-Y');
    } catch (Throwable) {
        return '';
    }
}

function slugBlogPublico(mixed $value): ?string
{
    if (!is_scalar($value)) {
        return null;
    }
    $slug = trim((string) $value);
    return $slug !== '' && strlen($slug) <= 210 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1
        ? $slug
        : null;
}

function paginaBlogPublico(mixed $value): int
{
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $page === false ? 1 : (int) $page;
}

function urlListadoBlogPublico(?string $category, int $page = 1): string
{
    $query = [];
    if ($category !== null) {
        $query['categoria'] = $category;
    }
    if ($page > 1) {
        $query['pagina'] = $page;
    }
    return appUrl('public/blog.php') . ($query === [] ? '' : '?' . http_build_query($query));
}

$requestedSlug = array_key_exists('slug', $_GET) ? slugBlogPublico($_GET['slug']) : null;
$detailMode = array_key_exists('slug', $_GET);

if ($detailMode) {
    $article = null;
    if ($requestedSlug === null) {
        http_response_code(404);
    } elseif (!($pdo instanceof PDO)) {
        http_response_code(500);
    } else {
        try {
            $article = obtenerArticuloBlogPublicoPorSlug($pdo, $requestedSlug);
        } catch (Throwable) {
            http_response_code(500);
        }
    }
    if (!is_array($article)) {
        if (http_response_code() !== 500) {
            http_response_code(404);
        }
        renderPublicPageStart('Artículo no encontrado | Coratto Pet', 'El artículo solicitado no está disponible.', 'blog'); ?>
        <main id="contenido" class="public-blog public-blog--error">
            <div class="public-shell">
                <section class="public-blog-state">
                    <h1><?= http_response_code() === 500 ? 'No fue posible cargar el artículo' : 'Artículo no encontrado' ?>
                    </h1>
                    <p><?= http_response_code() === 500 ? 'Intenta nuevamente más tarde.' : 'El contenido solicitado no existe o no está publicado.' ?>
                    </p><a href="<?= e(appUrl('public/blog.php')) ?>">Volver al Blog</a>
                </section>
            </div>
        </main>
        <?php renderPublicPageEnd();
        exit;
    }

    $coverUrl = urlPortadaBlog($article['imagen_portada'] ?? null);
    $complementaryUrl = urlPortadaBlog($article['imagen_complementaria'] ?? null);
    $articleImages = array_values(array_filter([$coverUrl, $complementaryUrl], static fn (?string $url): bool => $url !== null));
    $videoUrl = urlVideoBlog($article['video_url'] ?? null);
    $safeContent = sanitizarContenidoBlog($article['contenido_html'] ?? '');
    $title = trim((string) ($article['seo_titulo'] ?? '')) ?: (string) $article['titulo'];
    $description = trim((string) ($article['seo_descripcion'] ?? '')) ?: (string) $article['extracto'];
    $canonical = appUrl('public/blog.php?slug=' . rawurlencode($requestedSlug));
    renderPublicPageStart($title . ' | Coratto Pet', $description, 'blog', $canonical); ?>
    <main id="contenido" class="public-blog public-blog--article">
        <div class="public-shell">
            <article class="public-blog-article">
                <header class="public-blog-article__header"><a class="public-blog-back"
                        href="<?= e(appUrl('public/blog.php')) ?>">← Volver al Blog</a>
                    <p class="public-blog-category"><?= e($article['categoria']) ?></p>
                    <h1><?= e($article['titulo']) ?></h1>
                    <p class="public-blog-excerpt"><?= e($article['extracto']) ?></p>
                    <div class="public-blog-byline"><span>Por
                            <?= e($article['autor_publico']) ?></span><?php if (fechaBlogPublico($article['fecha_publicacion']) !== ''): ?><time
                                datetime="<?= e($article['fecha_publicacion']) ?>"><?= e(fechaBlogPublico($article['fecha_publicacion'])) ?></time><?php endif; ?>
                    </div>
                </header><?php if ($articleImages !== []): ?>
                    <figure class="public-blog-article__cover<?= count($articleImages) > 1 ? ' public-blog-gallery' : '' ?>"<?= count($articleImages) > 1 ? ' data-blog-gallery tabindex="0" aria-label="Galería de imágenes del artículo"' : '' ?>>
                        <div class="public-blog-gallery__track">
                            <?php foreach ($articleImages as $imageIndex => $imageUrl): ?>
                                <img src="<?= e($imageUrl) ?>"
                                    alt="<?= $imageIndex === 0 && $coverUrl !== null ? 'Portada' : 'Imagen complementaria' ?> de <?= e($article['titulo']) ?>"
                                    <?= $imageIndex > 0 ? 'loading="lazy"' : '' ?>
                                    data-blog-gallery-slide<?= $imageIndex === 0 ? '' : ' hidden' ?>>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($articleImages) > 1): ?>
                            <button class="public-blog-gallery__arrow public-blog-gallery__arrow--previous" type="button" data-blog-gallery-previous aria-label="Mostrar imagen anterior">←</button>
                            <button class="public-blog-gallery__arrow public-blog-gallery__arrow--next" type="button" data-blog-gallery-next aria-label="Mostrar imagen siguiente">→</button>
                            <span class="public-blog-gallery__status" aria-live="polite" aria-atomic="true"><b data-blog-gallery-current>1</b>/2</span>
                        <?php endif; ?>
                    </figure>
                    <?php endif; ?>
                    <div class="public-blog-article__content"><?= $safeContent ?></div><?php if ($videoUrl !== null): ?>
                        <aside class="public-blog-article__video">
                            <div class="public-blog-article__video-copy">
                                <span class="public-blog-article__video-eyebrow">Contenido recomendado</span>

                                <h2>Sigue aprendiendo</h2>

                                <p>
                                    Complementa esta lectura con un contenido en video pensado para ayudarte
                                    a tomar decisiones más informadas para tu mascota.
                                </p>
                            </div>

                            <a
                                class="public-blog-article__video-link"
                                href="<?= e($videoUrl) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="Ver contenido recomendado en video"
                            >
                                <span class="public-blog-article__video-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M9 7.5v9l7-4.5-7-4.5Z" />
                                    </svg>
                                </span>

                                <span class="public-blog-article__video-link-text">
                                    <strong>Ver contenido</strong>
                                    <small>Video recomendado</small>
                                </span>
                            </a>
                        </aside>
                    <?php endif; ?>
            </article>
        </div>
    </main>
    <?php if (count($articleImages) > 1): ?>
        <script src="<?= e(appUrl('public/assets/js/blog-gallery.js?v=' . filemtime(__DIR__ . '/assets/js/blog-gallery.js'))) ?>" defer></script>
    <?php endif; ?>
    <?php renderPublicPageEnd();
    exit;
}

$databaseError = !($pdo instanceof PDO);
$categories = [];
$featured = null;
$articles = [];
$categoryParameter = $_GET['categoria'] ?? null;
$categoryParameterIsScalar = is_scalar($categoryParameter);
$category = $categoryParameterIsScalar ? slugBlogPublico($categoryParameter) : null;
$requestedCategory = $categoryParameterIsScalar && trim((string) $categoryParameter) !== '';
$page = paginaBlogPublico($_GET['pagina'] ?? 1);
$totalPages = 1;

if (!$databaseError) {
    try {
        $categories = obtenerCategoriasBlogPublico($pdo);
        $activeCategorySlugs = array_column($categories, 'slug');
        if ($requestedCategory && ($category === null || !in_array($category, $activeCategorySlugs, true))) {
            $category = null;
            $articles = [];
        } else {
            $featured = obtenerArticuloDestacadoBlogPublico($pdo);
            $excludedId = is_array($featured) ? (int) $featured['id_articulo'] : null;
            $total = contarArticulosBlogPublico($pdo, $category, $excludedId);
            $totalPages = max(1, (int) ceil($total / 6));
            $page = min($page, $totalPages);
            $articles = obtenerArticulosBlogPublico($pdo, $category, $excludedId, $page, 6);
        }
    } catch (Throwable) {
        $databaseError = true;
        $categories = [];
        $featured = null;
        $articles = [];
        $totalPages = 1;
    }
}

renderPublicPageStart('Blog | Coratto Pet', 'Guías y recomendaciones para cuidar mejor a tu mascota.', 'blog'); ?>
<main id="contenido" class="public-blog public-blog--listing">
    <div class="public-shell">
        <header class="public-blog-header">
             <img
                class="public-blog-header__icon"
                src="<?= e(appUrl('public/assets/img/blog/blog-icono-bienestar.png')) ?>"
                alt=""
                aria-hidden="true"
            >
            <p class="public-blog-eyebrow">Aprende con Coratto</p>
            <h1>Guías para elegir y cuidar mejor</h1>
            <p>Información práctica para acompañar el bienestar de tu mascota.</p>
        </header><?php if (!$databaseError): ?>
            <nav class="public-blog-categories" aria-label="Categorías del Blog"><a
                    href="<?= e(appUrl('public/blog.php')) ?>" <?= $category === null && !$requestedCategory ? ' aria-current="page"' : '' ?>>Todas</a><?php foreach ($categories as $item): ?><a
                        href="<?= e(urlListadoBlogPublico((string) $item['slug'])) ?>" <?= $category === $item['slug'] ? ' aria-current="page"' : '' ?>><?= e($item['nombre']) ?></a><?php endforeach; ?></nav><?php endif; ?>
        <?php if ($databaseError): ?>
            <section class="public-blog-state public-blog-state--error">
                <h2>No fue posible cargar el Blog</h2>
                <p>Intenta nuevamente más tarde.</p>
            </section><?php else: ?>
            <?php if (is_array($featured)):
                $featuredCover = urlPortadaBlog($featured['imagen_portada'] ?? null); ?>
                <section class="public-blog-featured" aria-labelledby="articulo-destacado">
                    <div class="public-blog-featured__content">
                        <p class="public-blog-category"><?= e($featured['categoria']) ?></p>
                        <h2 id="articulo-destacado"><a
                                href="<?= e(appUrl('public/blog.php?slug=' . rawurlencode((string) $featured['slug']))) ?>"><?= e($featured['titulo']) ?></a>
                        </h2>
                        <p><?= e($featured['extracto']) ?></p>
                        <div><span>Por
                                <?= e($featured['autor_publico']) ?></span><?php if (fechaBlogPublico($featured['fecha_publicacion']) !== ''): ?><time
                                    datetime="<?= e($featured['fecha_publicacion']) ?>"><?= e(fechaBlogPublico($featured['fecha_publicacion'])) ?></time><?php endif; ?>
                        </div>
                    </div><?php if ($featuredCover !== null): ?><img src="<?= e($featuredCover) ?>"
                            alt="Portada de <?= e($featured['titulo']) ?>"><?php endif; ?>
                </section><?php endif; ?>
            <section class="public-blog-list" aria-labelledby="ultimos-articulos">
                <h2 id="ultimos-articulos">Últimos artículos</h2><?php if ($articles !== []): ?>
                    <div class="public-blog-grid">
                        <?php foreach ($articles as $item):
                            $cover = urlPortadaBlog($item['imagen_portada'] ?? null); ?>
                            <a class="public-blog-card"
                                href="<?= e(appUrl('public/blog.php?slug=' . rawurlencode((string) $item['slug']))) ?>"><?php if ($cover !== null): ?><img src="<?= e($cover) ?>"
                                        alt="Portada de <?= e($item['titulo']) ?>"><?php endif; ?>
                                <div class="public-blog-card__content">
                                    <p class="public-blog-category"><?= e($item['categoria']) ?></p>
                                    <h3><?= e($item['titulo']) ?></h3>
                                    <p><?= e($item['extracto']) ?></p>
                                    <div><span>Por
                                            <?= e($item['autor_publico']) ?></span><?php if (fechaBlogPublico($item['fecha_publicacion']) !== ''): ?><time
                                                datetime="<?= e($item['fecha_publicacion']) ?>"><?= e(fechaBlogPublico($item['fecha_publicacion'])) ?></time><?php endif; ?><span
                                            class="public-blog-card__read">Leer artículo →</span>
                                    </div>
                                </div>
                            </a><?php endforeach; ?>
                    </div><?php else: ?>
                    <div class="public-blog-state">
                        <h3>No hay artículos disponibles</h3>
                        <p><?= $requestedCategory ? 'Esta categoría todavía no tiene publicaciones.' : 'Estamos preparando nuevos contenidos para ti.' ?>
                        </p>
                    </div><?php endif; ?>
            </section>
            <?php if ($totalPages > 1): ?>
                <nav class="public-blog-pagination" aria-label="Paginación del Blog"><?php if ($page > 1): ?><a
                            href="<?= e(urlListadoBlogPublico($category, $page - 1)) ?>">← Anterior</a><?php endif; ?><span>Página
                        <?= e($page) ?> de <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a
                            href="<?= e(urlListadoBlogPublico($category, $page + 1)) ?>">Siguiente →</a><?php endif; ?></nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
<?php renderPublicPageEnd(); ?>
