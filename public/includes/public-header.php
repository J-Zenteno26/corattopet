<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once __DIR__ . '/consultas-publicas.php';

$currentPage = $currentPage ?? 'inicio';
$publicCategories = $publicCategories ?? [];
if ($publicCategories === []) {
    try {
        $publicCategories = obtenerCategoriasPublicas($pdo ?? database());
    } catch (Throwable) {
        $publicCategories = [];
    }
}
$clientLoggedIn = isset($_SESSION['id_cliente']) && is_numeric($_SESSION['id_cliente']);
$clientName = $clientLoggedIn && is_string($_SESSION['cliente_nombre'] ?? null) ? $_SESSION['cliente_nombre'] : '';
$active = static fn (string $page): string => $currentPage === $page ? ' active' : '';
?>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="site-header" id="cabecera-publica">
  <div class="container header-inner">
    <a class="brand" href="<?= e(appUrl('index.php')) ?>" aria-label="Ir al inicio de Coratto Pet">
      <img src="<?= e(appUrl('public/assets/img/logo-coratto-navbar.png')) ?>" alt="Coratto Pet" width="600" height="200">
    </a>
    <nav class="main-nav" id="main-nav" aria-label="Navegación principal">
      <a class="<?= trim($active('inicio')) ?>" href="<?= e(appUrl('index.php')) ?>">Inicio</a>
      <div class="nav-dropdown" data-nav-dropdown>
        <button class="main-nav__products<?= $currentPage === 'catalogo' ? ' active' : '' ?>" type="button" aria-expanded="false" aria-controls="productos-menu">Productos
          <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m7 9 5 5 5-5" /></svg>
        </button>
        <div class="nav-dropdown__menu" id="productos-menu">
          <?php foreach ($publicCategories as $category): ?>
            <a href="<?= e(appUrl('catalogo.php?categoria=' . rawurlencode((string) $category['id']))) ?>"><?= e($category['nombre']) ?></a>
          <?php endforeach; ?>
          <a class="nav-dropdown__all" href="<?= e(appUrl('catalogo.php')) ?>">Ver todos los productos</a>
        </div>
      </div>
      <a class="<?= trim($active('nosotros')) ?>" href="<?= e(appUrl('public/nosotros.php')) ?>">Nosotros</a>
      <a class="<?= trim($active('blog')) ?>" href="<?= e(appUrl('public/blog.php')) ?>">Blog</a>
      <a class="<?= trim($active('contacto')) ?>" href="<?= e(appUrl('public/contacto.php')) ?>">Contacto</a>
    </nav>
    <div class="header-actions">
      <button class="header-action header-action--search" type="button" aria-label="Buscar productos" aria-expanded="false" aria-controls="public-search">
        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4" /></svg>
      </button>
      <a class="header-action header-action--user" href="<?= e(appUrl($clientLoggedIn ? 'public/clientes/cuenta.php' : 'public/clientes/registro.php')) ?>" aria-label="<?= $clientLoggedIn ? 'Mi cuenta, ' . e($clientName) : 'Crear cuenta o iniciar sesión' ?>">
        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5" /><path d="M5 20c.7-4 3.1-6 7-6s6.3 2 7 6" /></svg>
      </a>
      <a class="header-action header-cart" href="<?= e(appUrl('catalogo.php')) ?>" aria-label="Carrito, 0 productos"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 8h10l1 12H6L7 8Z" /><path d="M9 9V6a3 3 0 0 1 6 0v3" /></svg><span class="header-cart__badge">0</span></a>
      <a class="button header-cta" href="<?= e(appUrl('catalogo.php')) ?>">Tienda online</a>
    </div>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span><span class="sr-only">Abrir menú</span></button>
  </div>
  <div class="public-search" id="public-search" hidden>
    <form action="<?= e(appUrl('public/catalogo.php')) ?>" method="get" role="search">
      <label for="public-search-input">Buscar productos</label>
      <input id="public-search-input" name="buscar" type="search" maxlength="100" placeholder="Nombre, SKU, marca o categoría">
      <button class="button" type="submit">Buscar</button>
      <button class="public-search__close" type="button" aria-label="Cerrar buscador">×</button>
    </form>
  </div>
</header>
