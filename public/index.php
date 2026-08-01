<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/includes/consultas-publicas.php';

$config = [];
$products = [];

try {
    $pdo = database();
    $config = obtenerConfiguracionPublica($pdo);
    $featuredProducts = obtenerProductosDestacadosHome($pdo);
    $catalogProducts = obtenerProductosCatalogoPublico($pdo);
    $catalogById = [];
    foreach ($catalogProducts as $catalogProduct) {
        $catalogById[(int) $catalogProduct['id_producto']] = $catalogProduct;
    }
    foreach ($featuredProducts as $featuredProduct) {
        $productId = (int) ($featuredProduct['id_producto'] ?? 0);
        if (!isset($catalogById[$productId])) {
            continue;
        }
        $product = array_merge($catalogById[$productId], $featuredProduct);
        $product['presentaciones'] = !empty($product['fraccionable'])
            ? obtenerPresentacionesPublicasProducto($pdo, $productId)
            : [];
        $products[] = $product;
    }
} catch (Throwable) {
    // La portada conserva su contenido editorial si la base no está disponible.
}

$whatsappUrl = obtenerWhatsappPublico($config);

/**
 * Renderiza un recurso visual de la home.
 * Mientras el archivo no exista, conserva el espacio con un placeholder visible.
 */
function renderHomeAsset(
    string $relativePath,
    string $alt,
    string $class = '',
    bool $eager = false
): void {
    $normalizedPath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $absolutePath = __DIR__ . '/' . $normalizedPath;

    if (is_file($absolutePath)) {
        $loading = $eager ? 'eager' : 'lazy';
        $fetchPriority = $eager ? ' fetchpriority="high"' : '';

        echo '<img src="' . e($normalizedPath) . '" alt="' . e($alt) . '" class="' . e($class)
            . '" loading="' . $loading . '"' . $fetchPriority . ' decoding="async">';
        return;
    }

    echo '<span class="home-media-placeholder ' . e($class) . '" role="img" aria-label="' . e($alt)
        . '" data-asset="' . e($normalizedPath) . '">'
        . '<span>Imagen pendiente</span>'
        . '<small>' . e(basename($normalizedPath)) . '</small>'
        . '</span>';
}

$brands = [
    ['Acana', 'acana.webp'],
    ['Alpha Spirit', 'alpha-spirit.webp'],
    ['Biofresh', 'biofresh.webp'],
    ['Bravery', 'bravery.png'],
    ['Josera', 'josera.png'],
    ['Nómade', 'nomade.png'],
    ['Orijen', 'orijen.webp'],
    ['Pet Family', 'pet-family.webp'],
    ['Purina', 'purina.webp'],
    ['Taste of the Wild', 'taste-of-the-wild.png'],
];

$productCategories = [
    [
        'title' => 'Alimentos',
        'image' => 'alimentos.png',
        'href' => 'catalogo.php?categoria=alimentos',
    ],
    [
        'title' => 'Accesorios',
        'image' => 'accesorios.png',
        'href' => 'catalogo.php?categoria=accesorios',
    ],
    [
        'title' => 'Higiene',
        'image' => 'higiene.png',
        'href' => 'catalogo.php?categoria=higiene',
    ],
    [
        'title' => 'Juguetes',
        'image' => 'juguetes.png',
        'href' => 'catalogo.php?categoria=juguetes',
    ],
    [
        'title' => 'Viaje y paseo',
        'image' => 'viaje_paseo.png',
        'href' => 'catalogo.php?categoria=viaje-y-paseo',
    ],
];

$needs = [
    ['Cachorro', 'Crecimiento', 'growth'],
    ['Adulto activo', 'Energía diaria', 'activity'],
    ['Senior', 'Bienestar y cuidado', 'heart'],
    ['Control de peso', 'Porciones adecuadas', 'balance'],
    ['Digestión sensible', 'Buena tolerancia', 'digest'],
    ['Piel y pelaje', 'Nutrición visible', 'sparkle'],
    ['Gato adulto', 'Naturaleza felina', 'cat'],
    ['Hidratación felina', 'Más agua en su rutina', 'drop'],
];

$criteria = [
    ['Etapa de vida', 'growth'],
    ['Proteína principal', 'protein'],
    ['Digestibilidad', 'digest'],
    ['Ingredientes funcionales', 'leaf'],
    ['Piel y pelaje', 'sparkle'],
    ['Cuidado digestivo', 'care'],
    ['Condición corporal', 'balance'],
    ['Hidratación', 'drop'],
];

$learningCards = [
    [
        'level' => 'Lección 01',
        'category' => 'Guía esencial',
        'title' => 'Cómo leer una etiqueta de alimento',
        'text' => 'Aprende a reconocer los datos que realmente importan.',
        'icon' => 'label',
    ],
    [
        'level' => 'Lección 02',
        'category' => 'Bienestar digestivo',
        'title' => 'Qué mirar ante una digestión sensible',
        'text' => 'Ingredientes, transición y señales para observar.',
        'icon' => 'digest',
    ],
    [
        'level' => 'Lección 03',
        'category' => 'Cambio responsable',
        'title' => 'Cómo cambiar su alimento sin apuros',
        'text' => 'Una pauta gradual para una adaptación más amable.',
        'icon' => 'care',
    ],
];
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="Coratto Pet reúne nutrición, bienestar y confianza para ayudarte a elegir mejor para tu mascota.">
    <meta name="theme-color" content="#f8efe5">
    <title>Coratto Pet | Nutrición, bienestar y confianza</title>

    <link rel="stylesheet" href="assets/css/home.css?v=9">
    <link rel="stylesheet" href="assets/css/home-ingredients.css?v=2">
    <link rel="stylesheet" href="assets/css/home-experience.css?v=2">
</head>

<body class="home-page">
    <svg class="icon-sprite" aria-hidden="true">
        <symbol id="i-compass" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="8" />
            <path d="m15.5 8.5-2.1 4.9-4.9 2.1 2.1-4.9 4.9-2.1Z" />
        </symbol>
        <symbol id="i-label" viewBox="0 0 24 24">
            <path d="M20 13 13 20 4 11V4h7l9 9Z" />
            <circle cx="8" cy="8" r="1" />
        </symbol>
        <symbol id="i-scoop" viewBox="0 0 24 24">
            <path d="M5 5h7a5 5 0 0 1 0 10H8M8 11v9H4v-9h4Z" />
            <path d="M13 8.5c2 .2 3 1.2 3 2.5" />
        </symbol>
        <symbol id="i-growth" viewBox="0 0 24 24">
            <path d="M12 20V8M12 12c-4 0-6-2-6-6 4 0 6 2 6 6ZM12 9c3.5 0 5-1.8 5-5-3.5 0-5 1.8-5 5ZM7 20h10" />
        </symbol>
        <symbol id="i-activity" viewBox="0 0 24 24">
            <path d="m3 13 4-4 3 3 5-6 6 6M17 6h4v4M5 18h14" />
        </symbol>
        <symbol id="i-heart" viewBox="0 0 24 24">
            <path d="M20 8.5c0 5-8 10-8 10s-8-5-8-10A4.5 4.5 0 0 1 12 5a4.5 4.5 0 0 1 8 3.5Z" />
        </symbol>
        <symbol id="i-balance" viewBox="0 0 24 24">
            <path d="M12 4v16M5 7h14M7 7l-3 6h6L7 7Zm10 0-3 6h6l-3-6ZM8 20h8" />
        </symbol>
        <symbol id="i-digest" viewBox="0 0 24 24">
            <path d="M9 4v5c0 2 1 3 3 3s3-1 3-3V5M9 8c-4 1-5 4-4 7s4 5 7 5 6-2 6-6c0-2-1-3-3-4" />
        </symbol>
        <symbol id="i-sparkle" viewBox="0 0 24 24">
            <path
                d="m12 3 1.4 4.6L18 9l-4.6 1.4L12 15l-1.4-4.6L6 9l4.6-1.4L12 3Zm7 12 .7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z" />
        </symbol>
        <symbol id="i-cat" viewBox="0 0 24 24">
            <path
                d="m6 9-1-5 4 3a9 9 0 0 1 6 0l4-3-1 5c2 4 0 10-6 10S4 13 6 9ZM9 12h.01M15 12h.01M10 15c1.3 1 2.7 1 4 0" />
        </symbol>
        <symbol id="i-drop" viewBox="0 0 24 24">
            <path d="M12 3S6 10 6 15a6 6 0 0 0 12 0c0-5-6-12-6-12ZM9 16c.5 1.3 1.5 2 3 2" />
        </symbol>
        <symbol id="i-protein" viewBox="0 0 24 24">
            <path d="M8 8c-2-2-5-1-5 2 0 2 2 3 4 2l5 5c-1 2 0 4 2 4 3 0 4-3 2-5L8 8ZM14 8c1-3 4-4 7-3-1 3-4 5-7 3Z" />
        </symbol>
        <symbol id="i-leaf" viewBox="0 0 24 24">
            <path d="M20 4C11 4 5 9 5 16c5 1 12-2 15-12ZM4 20c3-5 7-8 12-11" />
        </symbol>
        <symbol id="i-care" viewBox="0 0 24 24">
            <path d="M4 13c4 0 5 1 8 4 3-3 4-4 8-4M6 13V8a3 3 0 0 1 6 0v9M18 13V9a3 3 0 0 0-6 0" />
        </symbol>
        <symbol id="i-arrow" viewBox="0 0 24 24">
            <path d="M5 12h14M14 7l5 5-5 5" />
        </symbol>
        <symbol id="i-food" viewBox="0 0 24 24">
            <path d="M4 11h16l-1.5 8h-13L4 11ZM7 11c.5-3 2-5 5-5s4.5 2 5 5" />
            <circle cx="9" cy="14" r=".5" />
            <circle cx="13" cy="16" r=".5" />
            <circle cx="16" cy="13.5" r=".5" />
        </symbol>
        <symbol id="i-collar" viewBox="0 0 24 24">
            <path d="M5 7c4 2 10 2 14 0v5c-4 2-10 2-14 0V7Z" />
            <path d="M10 14v4l2 2 2-2v-4M8 5v3M16 5v3" />
        </symbol>
        <symbol id="i-hygiene" viewBox="0 0 24 24">
            <path d="M8 8h9v12H7V9l1-1ZM11 8V5h4M14 5V3h5" />
            <path d="M10 12h4M10 15h4" />
            <path d="m19 7 .5 1.5L21 9l-1.5.5L19 11l-.5-1.5L17 9l1.5-.5L19 7Z" />
        </symbol>
        <symbol id="i-toy" viewBox="0 0 24 24">
            <path d="M7 8c-3-1-5 1-4 4s3 3 5 1l3 3c-2 2-1 4 1 5 3 1 5-2 4-4l-9-9Z" />
            <circle cx="17" cy="7" r="4" />
            <path d="m15 7 1.3 1.3L19 5.5" />
        </symbol>
        <symbol id="i-health" viewBox="0 0 24 24">
            <path d="M12 20S4 15 4 9a4 4 0 0 1 7-2.7L12 7l1-.7A4 4 0 0 1 20 9c0 6-8 11-8 11Z" />
            <path d="M8 12h2l1-2 2 4 1-2h2" />
        </symbol>
        <symbol id="i-travel" viewBox="0 0 24 24">
            <path d="M5 8h14v10H5V8ZM9 8V5h6v3" />
            <path d="M8 18v2M16 18v2M12 10v6" />
        </symbol>
        <symbol id="i-more" viewBox="0 0 24 24">
            <circle cx="5" cy="12" r="1.5" />
            <circle cx="12" cy="12" r="1.5" />
            <circle cx="19" cy="12" r="1.5" />
        </symbol>
    </svg>

    <?php require __DIR__ . '/includes/public-header.php'; ?>

    <main id="contenido">
        <!-- 1. HERO: mascotas flotantes, franja ondulada y recorrido de patitas. -->
        <section class="home-hero" id="inicio" aria-labelledby="hero-title" data-section="hero">
            <div class="home-hero__glow" aria-hidden="true"></div>

            <div class="home-hero__paw-field" aria-hidden="true" data-animate="paw-field">
                <?php for ($paw = 1; $paw <= 12; $paw++): ?>
                    <span class="home-paw home-paw--<?= $paw ?>"></span>
                <?php endfor; ?>
            </div>

            <div class="container home-hero__layout">
                <div class="home-hero__copy" data-animate="hero-copy">
                    <span class="home-kicker">Cuidarlos también es saber elegir</span>
                    <h1 id="hero-title">Porque son parte de tu <em>corazón</em><span class="home-title-heart"
                            aria-hidden="true">♡</span></h1>
                    <p class="home-hero__tagline">
                        <span>Nutrición, Bienestar y Confianza</span>
                        <em>para tu Mascota.</em>
                    </p>

                    <div class="home-actions">
                        <a class="button" href="#guia-eleccion">Ayúdame a elegir</a>
                        <a class="button button-outline" href="#seleccion">Explorar alimentos</a>
                    </div>

                    <ul class="home-hero__promises" aria-label="Beneficios principales">
                        <li>Elegimos con intención</li>
                        <li>Formatos para probar</li>
                        <li>Orientación cercana</li>
                    </ul>
                </div>

                <div class="home-hero__stage" data-animate="hero-stage">
                    <div class="home-hero__pet-halo" aria-hidden="true"></div>

                    <figure class="home-hero__pets" data-float="pets">
                        <?php renderHomeAsset(
                            'assets/img/home/hero/mascotas-inicio.png',
                            'Perro y gato protagonistas de Coratto Pet',
                            'home-hero__pets-image',
                            true
                        ); ?>
                    </figure>

                    <span class="home-hero__floating-note home-hero__floating-note--one" data-float="slow">
                        Nutrición
                    </span>
                    <span class="home-hero__floating-note home-hero__floating-note--two" data-float="medium">
                        Bienestar
                    </span>
                    <span class="home-hero__floating-note home-hero__floating-note--three" data-float="fast">
                        Confianza
                    </span>
                </div>
            </div>
            <a class="home-hero__origin" href="#historia-coratto" aria-label="Conoce la historia de María y Simón">

                <figure class="home-hero__origin-photo">
                    <?php renderHomeAsset(
                        'assets/img/home/historia/maria-simon.png',
                        'María y Simón, inspiración de Coratto Pet',
                        'home-hero__origin-image'
                    ); ?>
                </figure>

                <span class="home-hero__origin-copy">
                    <small>Inspirado por María y Simón</small>
                    <strong>Conoce el corazón de Coratto</strong>
                    <span class="home-hero__origin-microcopy">La historia que dio vida a esta tienda.</span>
                </span>

                <svg class="home-hero__origin-arrow" aria-hidden="true">
                    <use href="#i-arrow"></use>
                </svg>
            </a>

            <div class="home-hero__wave" aria-hidden="true">
                <span class="home-hero__wave-line home-hero__wave-line--gold"></span>
                <span class="home-hero__wave-line home-hero__wave-line--black"></span>

                <div class="home-hero__wave-paws" data-animate="wave-paws">
                    <?php for ($paw = 1; $paw <= 7; $paw++): ?>
                        <span></span>
                    <?php endfor; ?>
                </div>
            </div>

            <a class="home-hero__scroll-cue" href="#marcas" aria-label="Descubrir la página">
                <span>Descubre Coratto</span>
                <svg aria-hidden="true">
                    <use href="#i-arrow"></use>
                </svg>
            </a>
        </section>

        <!-- 2. MARCAS: carrusel continuo de logotipos. -->
        <section class="brand-marquee" id="marcas" aria-labelledby="brands-title" data-section="brands">
            <div class="brand-marquee__heading">
                <span>Selección disponible en Coratto</span>
                <h2 id="brands-title">Marcas que acompañan su bienestar</h2>
            </div>

            <div class="brand-marquee__viewport" data-marquee="brands" tabindex="0"
                aria-label="Carrusel de marcas disponibles">
                <div class="brand-marquee__track">
                    <?php for ($cycle = 0; $cycle < 2; $cycle++): ?>
                        <div class="brand-marquee__group" <?= $cycle === 1 ? 'aria-hidden="true"' : '' ?>>
                            <?php foreach ($brands as [$brandName, $brandImage]): ?>
                                <figure class="brand-marquee__item" tabindex="0">
                                    <?php renderHomeAsset(
                                        'assets/img/home/marcas/' . $brandImage,
                                        'Logo ' . $brandName,
                                        'brand-marquee__logo'
                                    ); ?>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <!-- 3. PRODUCTOS: orden visual solicitado por Carolina. -->
        <section class="home-categories" id="productos" aria-labelledby="categories-title" data-section="categories">
            <div class="container">
                <header class="home-section-heading home-section-heading--center">
                    <span>Todo para ellos</span>
                    <h2 id="categories-title">Nuestros productos</h2>
                    <p>Explora por categoría sin perderte entre demasiadas opciones.</p>
                </header>

                <svg class="home-categories__curve" viewBox="0 0 1440 180" preserveAspectRatio="none"
                    aria-hidden="true">
                    <path d="M20 126 C210 22 420 154 610 78 S1010 26 1420 106"></path>
                </svg>

                <div class="home-categories__rail">
                    <?php foreach ($productCategories as $category): ?>
                        <article class="home-category-card">
                            <a href="<?= e($category['href']) ?>">
                                <figure class="home-category-card__visual">
                                    <?php renderHomeAsset(
                                        'assets/img/home/categorias/' . $category['image'],
                                        'Productos de ' . strtolower($category['title']) . ' para mascotas',
                                        'home-category-card__image'
                                    ); ?>
                                </figure>

                                <div class="home-category-card__copy">
                                    <h3><?= e($category['title']) ?></h3>
                                    <span class="home-category-card__link">
                                        Ver productos
                                        <svg aria-hidden="true">
                                            <use href="#i-arrow"></use>
                                        </svg>
                                    </span>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 4. GUÍA: selector visual de necesidades + plato llenándose. -->
        <section class="home-guide" id="guia-eleccion" aria-labelledby="guide-title" data-section="guide">
            <div class="container">
                <header class="home-section-heading">
                    <span>Una guía para comenzar</span>
                    <h2 id="guide-title">Cada mascota necesita algo distinto</h2>
                    <p>Elige una necesidad y descubre qué conviene observar.</p>
                </header>

                <div class="home-guide__experience">
                    <div class="home-guide__orbit" aria-label="Necesidades de mascotas">
                        <?php foreach ($needs as $index => [$title, $text, $icon]): ?>
                            <article class="home-need-card<?= $index === 0 ? ' is-active' : '' ?>">
                                <button class="home-need-orbit home-need-orbit--<?= $index + 1 ?>" type="button"
                                    data-need="<?= e($icon) ?>" aria-controls="guide-need-detail-<?= $index + 1 ?>"
                                    aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                                    <span class="home-need-orbit__float">
                                        <span class="home-need-orbit__icon">
                                            <svg aria-hidden="true">
                                                <use href="#i-<?= e($icon) ?>"></use>
                                            </svg>
                                        </span>
                                        <span>
                                            <strong><?= e($title) ?></strong>
                                            <small><?= e($text) ?></small>
                                        </span>
                                    </span>
                                </button>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="home-guide__center" data-animation-stage="feeding-bowl">
                        <div class="home-guide__sparkles" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>

                        <figure class="home-guide__scoop" data-animation-part="scoop" aria-hidden="true">
                            <?php renderHomeAsset(
                                'assets/img/home/guia/cuchara-alimento.png',
                                '',
                                'home-guide__scoop-image'
                            ); ?>
                        </figure>

                        <figure class="home-guide__falling-food" data-animation-part="falling-food" aria-hidden="true">
                            <?php renderHomeAsset(
                                'assets/img/home/guia/alimento-cayendo.png',
                                '',
                                'home-guide__falling-food-image'
                            ); ?>
                        </figure>

                        <figure class="home-guide__bowl home-guide__bowl--base">
                            <?php renderHomeAsset(
                                'assets/img/home/guia/plato-vacio.png',
                                'Plato Coratto Pet vacío, listo para llenarse',
                                'home-guide__bowl-image'
                            ); ?>
                        </figure>

                        <figure class="home-guide__bowl home-guide__bowl--filled" data-animation-part="bowl-fill">
                            <?php renderHomeAsset(
                                'assets/img/home/guia/plato-lleno-desbordado.png',
                                'Plato Coratto Pet lleno de alimento con algunos pellets alrededor',
                                'home-guide__bowl-image'
                            ); ?>
                        </figure>
                    </div>

                </div>
            </div>
        </section>

        <!-- 5. LO QUE IMPORTA: plato de ingredientes reales + conceptos flotantes. -->
        <section class="ingredient-universe" id="criterios-alimento" aria-labelledby="ingredients-title"
            data-section="ingredients">
            <div class="ingredient-universe__background" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>

            <div class="container ingredient-universe__layout">
                <header class="home-section-heading home-section-heading--light">
                    <span>Lo que realmente importa</span>
                    <h2 id="ingredients-title">Mira más allá del envase</h2>
                    <p>La nutrición también se entiende con los ojos.</p>
                </header>

                <div class="ingredient-universe__stage">
                    <figure class="ingredient-universe__plate" data-float="ingredient-plate">
                        <?php renderHomeAsset(
                            'assets/img/home/ingredientes/plato-ingredientes.png',
                            'Plato con ingredientes seleccionados para la alimentación de mascotas',
                            'ingredient-universe__plate-image'
                        ); ?>
                    </figure>

                    <div class="ingredient-universe__criteria" aria-label="Criterios para elegir alimento">
                        <?php foreach ($criteria as $index => [$title, $icon]): ?>
                            <article class="ingredient-chip ingredient-chip--<?= $index + 1 ?>"
                                data-orbit-item="<?= $index + 1 ?>">
                                <span>
                                    <svg aria-hidden="true">
                                        <use href="#i-<?= e($icon) ?>"></use>
                                    </svg>
                                </span>
                                <h3><?= e($title) ?></h3>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="ingredient-universe__ingredients">
                        <figure class="ingredient-universe__ingredient ingredient-universe__ingredient--one"
                            data-float="ingredient">
                            <?php renderHomeAsset(
                                'assets/img/home/ingredientes/proteina.png',
                                'Proteína seleccionada',
                                'ingredient-universe__ingredient-image'
                            ); ?>
                        </figure>
                        <figure class="ingredient-universe__ingredient ingredient-universe__ingredient--two"
                            data-float="ingredient-reverse">
                            <?php renderHomeAsset(
                                'assets/img/home/ingredientes/vegetales.png',
                                'Vegetales e ingredientes funcionales',
                                'ingredient-universe__ingredient-image'
                            ); ?>
                        </figure>
                        <figure class="ingredient-universe__ingredient ingredient-universe__ingredient--three"
                            data-float="ingredient">
                            <?php renderHomeAsset(
                                'assets/img/home/ingredientes/frutas.png',
                                'Frutas e ingredientes naturales',
                                'ingredient-universe__ingredient-image'
                            ); ?>
                        </figure>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. SELECCIÓN CORATTO: productos reales desde la base de datos. -->
        <section class="home-selection" id="seleccion" aria-labelledby="selection-title" data-section="selection">
            <div class="container">
                <header class="home-section-heading home-section-heading--split">
                    <div>
                        <span>Selección Coratto</span>
                        <h2 id="selection-title">Buenas fórmulas, elegidas con intención</h2>
                    </div>
                    <p>Menos ruido. Más claridad para encontrar una opción que tenga sentido.</p>
                </header>

                <nav class="home-selection__filters" aria-label="Explorar selección Coratto">
                    <a class="is-active" href="catalogo.php">Todos</a>
                    <a href="catalogo.php?mascota=perro">Perros</a>
                    <a href="catalogo.php?mascota=gato">Gatos</a>
                    <a href="catalogo.php?formato=fraccionado">Para probar</a>
                </nav>

                <div class="home-selection__viewport" data-slider="selection">
                    <div class="home-selection__rail">
                        <?php if ($products): ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $productHref = !empty($product['sku'])
                                    ? 'catalogo.php?sku=' . rawurlencode((string) $product['sku'])
                                    : 'catalogo.php';
                                $isFractioned = !empty($product['fraccionable']);
                                $presentationPrices = [];
                                foreach (($product['presentaciones'] ?? []) as $presentation) {
                                    $presentationPrice = (int) ($presentation['precio_venta'] ?? 0);
                                    if ($presentationPrice > 0) {
                                        $presentationPrices[] = $presentationPrice;
                                    }
                                }
                                $basePrice = $isFractioned
                                    ? ($presentationPrices !== [] ? min($presentationPrices) : 0)
                                    : (int) ($product['precio_venta'] ?? 0);
                                $futureSalePrice = (int) ($product['precio_descuento'] ?? $product['precio_oferta'] ?? 0);
                                $futureOriginalPrice = (int) ($product['precio_original'] ?? $basePrice);
                                $hasSale = $futureSalePrice > 0
                                    && $futureOriginalPrice > $futureSalePrice;
                                $currentPrice = $hasSale ? $futureSalePrice : $basePrice;
                                $discountPercentage = $hasSale
                                    ? (int) round((1 - ($futureSalePrice / $futureOriginalPrice)) * 100)
                                    : 0;
                                $productClasses = ['selection-product', 'selection-product--featured'];
                                if ($isFractioned) $productClasses[] = 'selection-product--fractioned';
                                if ($hasSale) $productClasses[] = 'selection-product--sale';
                                ?>
                                <article class="<?= e(implode(' ', $productClasses)) ?>">
                                    <a href="<?= e($productHref) ?>">
                                        <figure class="selection-product__visual">
                                            <?php if (!empty($product['imagen'])): ?>
                                                <img src="uploads/productos/<?= e(basename((string) $product['imagen'])) ?>"
                                                    alt="<?= e((string) $product['nombre']) ?>" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <span class="home-media-placeholder selection-product__placeholder" role="img"
                                                    aria-label="<?= e((string) $product['nombre']) ?>">
                                                    <span>Producto pendiente</span>
                                                    <small>Imagen de catálogo</small>
                                                </span>
                                            <?php endif; ?>

                                            <span class="selection-product__badge"><?= $isFractioned ? 'Disponible fraccionado' : 'Selección Coratto' ?></span>
                                            <?php if ($hasSale && $discountPercentage > 0): ?>
                                                <span class="selection-product__discount">-<?= e((string) $discountPercentage) ?>%</span>
                                            <?php endif; ?>
                                        </figure>

                                        <div class="selection-product__copy">
                                            <span class="selection-product__meta">
                                                <?= e((string) ($product['marca'] ?? 'Coratto')) ?>
                                                ·
                                                <?= e(ucfirst((string) ($product['tipo_mascota'] ?? 'Mascota'))) ?>
                                            </span>

                                            <h3><?= e((string) $product['nombre']) ?></h3>

                                            <?php if (!empty($product['ideal_para'])): ?>
                                                <p><?= e((string) $product['ideal_para']) ?></p>
                                            <?php elseif (!empty($product['beneficio'])): ?>
                                                <p><?= e((string) $product['beneficio']) ?></p>
                                            <?php endif; ?>

                                            <div class="selection-product__price">
                                                <?php if ($currentPrice > 0): ?>
                                                    <?php if ($isFractioned): ?><span class="selection-product__price-label">Desde</span><?php endif; ?>
                                                    <?php if ($hasSale): ?><span class="selection-product__price-original">$<?= e(number_format($futureOriginalPrice, 0, ',', '.')) ?></span><?php endif; ?>
                                                    <strong class="selection-product__price-current">$<?= e(number_format($currentPrice, 0, ',', '.')) ?></strong>
                                                <?php endif; ?>
                                            </div>

                                            <span class="selection-product__action">
                                                Conocer producto <span aria-hidden="true">→</span>
                                            </span>
                                        </div>
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <article class="selection-product selection-product--editorial">
                                <a href="<?= e($whatsappUrl) ?>">
                                    <figure class="selection-product__visual">
                                        <span class="home-media-placeholder selection-product__placeholder" role="img"
                                            aria-label="Selección de alimentos Coratto">
                                            <span>Selección en preparación</span>
                                            <small>Pronto disponible</small>
                                        </span>
                                    </figure>
                                    <div class="selection-product__copy">
                                        <span class="selection-product__meta">Orientación cercana</span>
                                        <h3>Te ayudamos a encontrar una alternativa</h3>
                                        <p>Cuéntanos la etapa y las necesidades de tu mascota.</p>
                                        <span class="selection-product__action">Hablar con Coratto</span>
                                    </div>
                                </a>
                            </article>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="home-selection__controls" aria-label="Controles de selección">
                    <button type="button" data-slider-prev="selection" aria-label="Producto anterior">←</button>
                    <span data-slider-progress="selection"></span>
                    <button type="button" data-slider-next="selection" aria-label="Producto siguiente">→</button>
                </div>
            </div>
        </section>

        <?php
        $trialPresentations = [
            [
                'id' => 'dog-250',
                'animal' => 'Perro',
                'format' => '250 g',
                'description' => 'Para conocer una nueva fórmula',
                'image' => 'assets/img/home/fraccionados/perro-negro-250.png',
                'alt' => 'Bolsa negra para perro de 250 gramos',
            ],
            [
                'id' => 'dog-1kg',
                'animal' => 'Perro',
                'format' => '1 kg',
                'description' => 'Para probar durante más días',
                'image' => 'assets/img/home/fraccionados/perro-negro-1kg.png',
                'alt' => 'Bolsa negra para perro de un kilogramo',
            ],
            [
                'id' => 'cat-250',
                'animal' => 'Gato',
                'format' => '250 g',
                'description' => 'Para conocer una nueva fórmula',
                'image' => 'assets/img/home/fraccionados/gato-azul-250.png',
                'alt' => 'Bolsa azul para gato de 250 gramos',
            ],
            [
                'id' => 'cat-1kg',
                'animal' => 'Gato',
                'format' => '1 kg',
                'description' => 'Para probar durante más días',
                'image' => 'assets/img/home/fraccionados/gato-azul-1kg.png',
                'alt' => 'Bolsa azul para gato de un kilogramo',
            ],
        ];
        ?>

        <!-- 7. FRACCIONADOS: vitrina interactiva de formatos de prueba. -->
        <section class="home-trial" id="fraccionados" aria-labelledby="trial-title" data-section="trial">
            <div class="container home-trial__layout">
                <div class="home-trial__copy">
                    <span>Prueba antes de decidir</span>
                    <h2 id="trial-title">No necesitas comenzar con el saco completo</h2>
                    <p>Prueba 250 g o 1 kg, observa cómo responde tu mascota y elige con más confianza.</p>
                    <a class="button" href="<?= e($whatsappUrl) ?>">Consultar formatos</a>
                </div>

                <div class="home-trial__stage" data-trial-showcase tabindex="0" aria-label="Selector de formatos fraccionados">
                    <svg class="home-trial__spiral" viewBox="0 0 700 520" aria-hidden="true">
                        <path d="M112 335C148 160 493 112 591 254C674 374 506 457 332 416C194 383 205 260 329 222C422 194 500 248 470 317C446 372 358 370 326 329" />
                    </svg>

                    <div class="home-trial__items">
                        <?php foreach ($trialPresentations as $index => $presentation): ?>
                            <button
                                class="home-trial__item"
                                type="button"
                                data-trial-item="<?= $index ?>"
                                data-trial-id="<?= e($presentation['id']) ?>"
                                data-animal="<?= e($presentation['animal']) ?>"
                                data-format="<?= e($presentation['format']) ?>"
                                data-description="<?= e($presentation['description']) ?>"
                                aria-label="Seleccionar <?= e($presentation['animal'] . ' ' . $presentation['format']) ?>"
                                aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                            >
                                <?php renderHomeAsset(
                                    $presentation['image'],
                                    $presentation['alt'],
                                    'home-trial__item-image'
                                ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="home-trial__active-info" aria-live="polite" aria-atomic="true">
                        <span data-trial-animal>PERRO · FORMATO DE PRUEBA</span>
                        <strong data-trial-format>250 g</strong>
                        <p data-trial-description>Para conocer una nueva fórmula</p>
                    </div>

                    <nav class="home-trial__controls" aria-label="Elegir presentación">
                        <button type="button" data-trial-previous aria-label="Presentación anterior">←</button>
                        <div class="home-trial__indicators" role="group" aria-label="Presentaciones disponibles">
                            <?php foreach ($trialPresentations as $index => $presentation): ?>
                                <button
                                    type="button"
                                    data-trial-indicator="<?= $index ?>"
                                    aria-label="<?= e($presentation['animal'] . ' ' . $presentation['format']) ?>"
                                    aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                                ></button>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" data-trial-next aria-label="Presentación siguiente">→</button>
                    </nav>
                </div>
            </div>
        </section>

        <!-- 8. APRENDE: módulo visual inspirado en una experiencia educativa. -->
        <section class="home-learning" id="aprende" aria-labelledby="learning-title" data-section="learning">
            <div class="container">
                <header class="home-learning__header">
                    <div>
                        <span>Aprende con Coratto</span>
                        <h2 id="learning-title">Una pequeña escuela para cuidar mejor</h2>
                    </div>

                    <a href="blog.php">
                        Ir a la comunidad
                        <svg aria-hidden="true">
                            <use href="#i-arrow"></use>
                        </svg>
                    </a>
                </header>

                <div class="home-learning__dashboard">
                    <aside class="home-learning__sidebar">
                        <span class="home-learning__avatar" aria-hidden="true">C</span>
                        <strong>Tu ruta Coratto</strong>
                        <p>Contenido breve, claro y pensado para aplicar.</p>

                        <div class="home-learning__progress">
                            <span>Próximamente</span>
                            <div><i></i></div>
                        </div>
                    </aside>

                    <div class="home-learning__courses">
                        <?php foreach ($learningCards as $index => $card): ?>
                            <article class="learning-card" data-course="<?= $index + 1 ?>">
                                <div class="learning-card__top">
                                    <span><?= e($card['level']) ?></span>
                                    <span class="learning-card__icon">
                                        <svg aria-hidden="true">
                                            <use href="#i-<?= e($card['icon']) ?>"></use>
                                        </svg>
                                    </span>
                                </div>

                                <small><?= e($card['category']) ?></small>
                                <h3><?= e($card['title']) ?></h3>
                                <p><?= e($card['text']) ?></p>

                                <a href="blog.php">
                                    Ver adelanto
                                    <svg aria-hidden="true">
                                        <use href="#i-arrow"></use>
                                    </svg>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- 9. CIERRE: escena emocional y CTA final. -->
        <section class="home-closing" aria-labelledby="closing-title" data-section="closing">
            <div class="home-closing__paws" aria-hidden="true">
                <span></span><span></span><span></span><span></span>
            </div>

            <div class="container home-closing__layout">
                <figure class="home-closing__pets" data-float="closing-pets">
                    <?php renderHomeAsset(
                        'assets/img/home/cierre/mascotas-coratto.webp',
                        'Perro y gato de Coratto Pet',
                        'home-closing__pets-image'
                    ); ?>
                </figure>

                <div class="home-closing__copy">
                    <span>Nuestra promesa</span>
                    <h2 id="closing-title">Elegimos con el mismo cariño con que tú cuidas a tu mascota</h2>
                    <p>Nutrición, bienestar y confianza para acompañarlos en cada etapa.</p>

                    <div class="home-actions">
                        <a class="button" href="catalogo.php">Explorar catálogo</a>
                        <a class="button button-light" href="<?= e($whatsappUrl) ?>">Hablar por WhatsApp</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require __DIR__ . '/includes/public-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js" defer></script>
    <script src="assets/js/home.js?v=8" defer></script>
</body>

</html>
