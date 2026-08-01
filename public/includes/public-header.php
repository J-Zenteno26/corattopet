<?php declare(strict_types=1); ?>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="site-header" id="inicio">
  <div class="container header-inner">
    <a class="brand" href="<?= e(appUrl('public/index.php')) ?>" aria-label="Ir al inicio de Coratto Pet">
        <img
            src="assets/img/logo-coratto-navbar.png"
            alt="Coratto Pet"
            width="600"
            height="200"
        >
    </a>
    <nav class="main-nav" id="main-nav" aria-label="Navegación principal">
      <a class="active" href="#inicio">Inicio</a>
      <a href="#contenido">Nosotros</a>
      <a class="main-nav__products" href="#seleccion">Productos
        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m7 9 5 5 5-5" /></svg>
      </a>
      <a href="#marcas">Marcas</a>
      <a href="#aprende">Blog</a>
      <a href="#contacto">Contacto</a>
    </nav>
    <div class="header-actions">
      <button class="header-action header-action--search" type="button" aria-label="Buscar">
        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /></svg>
      </button>
      <button class="header-action header-action--user" type="button" aria-label="Mi cuenta">
        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5" /><path d="M5 20c.7-4 3.1-6 7-6s6.3 2 7 6" /></svg>
      </button>
      <a class="header-action header-cart" href="catalogo.php" aria-label="Carrito, 0 productos">
        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 8h10l1 12H6L7 8Z" /><path d="M9 9V6a3 3 0 0 1 6 0v3" /></svg>
        <span class="header-cart__badge">0</span>
      </a>
      <a class="button header-cta" href="catalogo.php">Tienda online</a>
    </div>
    <button class="nav-toggle" type="button" aria-expanded="false"
      aria-controls="main-nav"><span></span><span></span><span></span><span class="sr-only">Abrir menú</span></button>
  </div>
</header>
