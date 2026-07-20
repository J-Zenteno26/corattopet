<?php declare(strict_types=1);
$categories = [
    ['Alimentos', 'cat-alimentos.png'],
    ['Accesorios', 'cat-accesorios.png'],
    ['Higiene', 'cat-higiene.png'],
    ['Juguetes', 'cat-juguetes.png'],
    ['Viaje y paseo', 'cat-viaje.png'],
];
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Coratto Pet: nutrición, bienestar y confianza para tu mascota.">
    <title>Coratto Pet | Bienestar para tu mascota</title>
    <link rel="stylesheet" href="assets/css/home.css">
</head>

<body>
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <header class="site-header" id="inicio">
        <div class="container header-inner">
            <a class="brand" href="#inicio" aria-label="Coratto Pet, inicio">
                <span class="logo-frame"><img src="assets/img/logo-coratto.png" alt="Coratto Pet"
                        onerror="this.parentElement.classList.add('image-missing');this.remove()"><span
                        class="logo-fallback"><b>CP</b><i>CORATTO PET</i></span></span>
            </a>
            <button class="nav-toggle" type="button" aria-expanded="false"
                aria-controls="main-nav"><span></span><span></span><span></span><span class="sr-only">Abrir
                    menú</span></button>
            <nav class="main-nav" id="main-nav" aria-label="Navegación principal">
                <a class="active" href="#inicio">Inicio</a><a href="#marca">Nosotros</a><a
                    href="#categorias">Productos</a><a href="#">Blog</a><a href="#contacto">Contacto</a>
            </nav>
            <div class="header-actions" aria-label="Accesos rápidos">
                <a href="#" aria-label="Buscar">⌕</a><a href="#" aria-label="Mi cuenta">♙</a><a class="cart" href="#"
                    aria-label="Carrito, 0 productos">▱<b>0</b></a>
                <a class="button button-small" href="#categorias">Tienda online <span aria-hidden="true">♣</span></a>
            </div>
        </div>
    </header>

    <main id="contenido">
        <section class="hero" aria-labelledby="hero-title">
            <span class="decor-paw paw-left" aria-hidden="true">♣</span><span class="decor-paw paw-right"
                aria-hidden="true">♣</span><span class="decor-heart" aria-hidden="true">♡</span>
            <div class="container hero-grid">
                <div class="hero-copy">
                    <h1 id="hero-title">Porque son parte<br>de tu <em>corazón</em> <span aria-hidden="true">♡</span>
                    </h1>
                    <p class="hero-subtitle">Nutrición, bienestar y confianza <i>para tu mascota.</i></p>
                    <div class="button-row"><a class="button" href="#categorias"><span aria-hidden="true">♣</span>
                            Comprar ahora</a><a class="button button-outline" href="#marca">Conocer la marca</a></div>
                </div>
                <div class="hero-visual">
                    <div class="hero-image-frame">
                        <img src="assets/img/hero-perro-gato.png" alt="Perro y gato Coratto Pet"
                            onerror="this.parentElement.classList.add('image-missing');this.remove()">
                        <div class="image-fallback" aria-hidden="true"><span
                                class="pet-silhouette pet-silhouette-dog"></span><span
                                class="pet-silhouette pet-silhouette-cat"></span><small>Fotografía de perro y
                                gato</small></div>
                    </div>
                </div>
            </div>
            <div class="hero-wave" aria-hidden="true">
                <span></span>
            </div>
        </section>

        <section class="categories" id="categorias" aria-labelledby="category-title">
            <div class="container">
                <header class="section-heading">
                    <div><span></span><i>♣</i><span></span></div>
                    <h2 id="category-title">Nuestros productos</h2>
                </header>
                <div class="category-grid">
                    <?php foreach ($categories as [$name, $image]): ?>
                        <a class="category-card" href="#" aria-label="Ver productos de <?= htmlspecialchars($name) ?>">
                            <div class="category-image"><img src="assets/img/<?= htmlspecialchars($image) ?>"
                                    alt="<?= htmlspecialchars($name) ?>"
                                    onerror="this.parentElement.classList.add('image-missing');this.remove()">
                                <div class="image-fallback"><span></span><small>Imagen
                                        <?= htmlspecialchars(strtolower($name)) ?></small></div>
                            </div>
                            <h3><?= htmlspecialchars($name) ?></h3><span class="card-link">Ver productos <b
                                    aria-hidden="true">›</b></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="brand-benefits" id="marca" aria-labelledby="brand-title">
            <div class="container brand-row">
                <div class="story-image"><img src="assets/img/marca-mascotas.png" alt="Mascotas Coratto Pet"
                        onerror="this.parentElement.classList.add('image-missing');this.remove()">
                    <div class="image-fallback"><b>CP</b><small>Fotografía de mascotas</small></div>
                </div>
                <div class="story-copy">
                    <h2 id="brand-title">Nuestra marca <span aria-hidden="true">♡</span></h2>
                    <p>Coratto Pet nace del corazón y del compromiso con el bienestar animal. Seleccionamos
                        cuidadosamente cada producto para entregar calidad, seguridad y amor en cada etapa de su vida.
                    </p>
                </div>
                <div class="benefits-grid" aria-label="Beneficios de Coratto Pet">
                    <article><span class="benefit-icon">✦</span>
                        <h3>Calidad seleccionada</h3>
                        <p>Elegimos lo mejor para su bienestar.</p>
                    </article>
                    <article><span class="benefit-icon">◇</span>
                        <h3>Productos premium</h3>
                        <p>Ingredientes y materiales de alta calidad.</p>
                    </article>
                    <article><span class="benefit-icon">▰</span>
                        <h3>Envíos a todo Chile</h3>
                        <p>Llevamos lo que necesita a donde estés.</p>
                    </article>
                    <article><span class="benefit-icon">♡</span>
                        <h3>Atención personalizada</h3>
                        <p>Contigo en cada etapa de su vida.</p>
                    </article>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer" id="contacto">
        <div class="container footer-grid">
            <div class="footer-brand"><a class="footer-logo"
                    href="#inicio"><span>CP</span><b>CORATTO<small>PET</small></b></a>
                <p>Nutrición, bienestar y confianza<br><em>para tu mascota.</em></p><a href="#">Instagram ·
                    coratto_pet</a>
            </div>
            <div>
                <h3>Enlaces</h3><a href="#inicio">Inicio</a><a href="#marca">Nosotros</a><a
                    href="#categorias">Productos</a><a href="#">Blog</a>
            </div>
            <div>
                <h3>Ayuda</h3><a href="#">Preguntas frecuentes</a><a href="#">Políticas de envío</a><a href="#">Cambios
                    y devoluciones</a><a href="#">Términos y condiciones</a>
            </div>
            <div>
                <h3>Contacto</h3><a href="mailto:corattopet@gmail.com">corattopet@gmail.com</a><a
                    href="tel:+56962291562">+56 9 6229 1562</a><a href="#">@coratto_pet</a>
            </div>
            <div class="newsletter">
                <h3>Suscríbete a nuestra newsletter</h3>
                <p>Recibe novedades, consejos y promociones exclusivas para tu mascota.</p>
                <form action="#"><label class="sr-only" for="email">Correo electrónico</label><input id="email"
                        type="email" placeholder="Tu correo electrónico"><button type="submit"
                        aria-label="Suscribirme">♣</button></form>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">© 2026 Coratto Pet. Todos los derechos reservados.</div>
        </div>
    </footer>
    <script src="assets/js/home.js" defer></script>
</body>

</html>