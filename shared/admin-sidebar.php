<?php

declare(strict_types=1);

$activeSection = isset($activeSection) ? (string) $activeSection : '';
$adminLogoUrl = appUrl('public/img/logo-coratto-pet.png');
$adminHomeUrl = appUrl(adminLandingPath());
$currentAdminRole = (string) ($_SESSION['rol'] ?? '');

$navigationItems = [
    'dashboard' => ['Dashboard', 'admin/dashboard/index.php', 'bi-speedometer2'],
    'inventario' => ['Inventario', 'admin/inventario/index.php', 'bi-box-seam'],
    'pedidos' => ['Pedidos', 'admin/pedidos/index.php', 'bi-receipt'],
    'despachos' => ['Despachos', 'admin/despachos/tarifas/index.php', 'bi-box-arrow-up-right'],
    'clientes' => ['Clientes', 'admin/clientes/index.php', 'bi-people'],
    'categorias' => ['Categorías', 'admin/categorias/index.php', 'bi-tags'],
    'marcas' => ['Marcas', 'admin/marcas/index.php', 'bi-award'],
    'proveedores' => ['Proveedores', 'admin/proveedores/index.php', 'bi-truck'],
    'configuracion' => ['Configuración', 'admin/configuracion/index.php', 'bi-sliders'],
    'importaciones' => ['Importaciones', 'admin/importaciones/index.php', 'bi-file-earmark-spreadsheet'],
    'blog' => ['Blog', 'admin/blog/index.php', 'bi-journal-richtext'],
    'usuarios' => ['Usuarios', 'admin/usuarios/index.php', 'bi-person-gear'],
];

if ($currentAdminRole === 'Blog') {
    $navigationItems = ['blog' => $navigationItems['blog']];
} elseif ($currentAdminRole !== 'administrador') {
    unset($navigationItems['usuarios']);
}
?>
<nav class="admin-sidebar" id="admin-sidebar" aria-label="Navegación administrativa">
    <button class="admin-sidebar__close" type="button" data-menu-close
        aria-label="Cerrar menú administrativo">×</button>

    <a class="admin-sidebar__brand" href="<?= escape($adminHomeUrl) ?>">
        <img src="<?= escape($adminLogoUrl) ?>" alt="Coratto Pet" width="136" height="50">
        <span>Panel administrativo</span>
    </a>
    <?php
    $currentAdminPath = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    $activeShippingPage = match (true) {
        str_ends_with(
            $currentAdminPath,
            '/admin/despachos/categorias/index.php'
        ) => 'categorias',

        str_ends_with(
            $currentAdminPath,
            '/admin/despachos/asignaciones/index.php'
        ) => 'asignaciones',

        default => 'tarifas',
    };
    ?>
<ul class="admin-nav">
    <?php foreach ($navigationItems as $section => [$label, $path, $icon]): ?>
        <li>
            <?php if ($section === 'despachos'): ?>
                <details
                    class="admin-nav-submenu"
                    <?= $activeSection === 'despachos' ? 'open' : '' ?>
                >
                    <summary
                        class="admin-nav__link<?= $activeSection === 'despachos' ? ' is-active' : '' ?>"
                    >
                        <i
                            class="bi <?= escape($icon) ?>"
                            aria-hidden="true"
                        ></i>

                        <span><?= escape($label) ?></span>

                        <i
                            class="bi bi-chevron-down admin-nav-submenu__arrow"
                            aria-hidden="true"
                        ></i>
                    </summary>

                    <ul class="admin-nav-submenu__list">
                        <li>
                            <a
                                class="admin-nav-submenu__link<?= $activeSection === 'despachos' && $activeShippingPage === 'tarifas' ? ' is-active' : '' ?>"
                                href="<?= escape(appUrl('admin/despachos/tarifas/index.php')) ?>"
                                <?= $activeSection === 'despachos' && $activeShippingPage === 'tarifas' ? 'aria-current="page"' : '' ?>
                            >
                                <i class="bi bi-cash-coin" aria-hidden="true"></i>
                                <span>Tarifas</span>
                            </a>
                        </li>

                        <li>
                            <a
                                class="admin-nav-submenu__link<?= $activeSection === 'despachos' && $activeShippingPage === 'categorias' ? ' is-active' : '' ?>"
                                href="<?= escape(appUrl('admin/despachos/categorias/index.php')) ?>"
                                <?= $activeSection === 'despachos' && $activeShippingPage === 'categorias' ? 'aria-current="page"' : '' ?>
                            >
                                <i class="bi bi-box-seam" aria-hidden="true"></i>
                                <span>Categorías</span>
                            </a>
                        </li>

                        <li>
                            <a
                                class="admin-nav-submenu__link<?= $activeSection === 'despachos' && $activeShippingPage === 'asignaciones' ? ' is-active' : '' ?>"
                                href="<?= escape(appUrl('admin/despachos/asignaciones/index.php')) ?>"
                                <?= $activeSection === 'despachos' && $activeShippingPage === 'asignaciones' ? 'aria-current="page"' : '' ?>
                            >
                                <i class="bi bi-ui-checks-grid" aria-hidden="true"></i>
                                <span>Asignar productos</span>
                            </a>
                        </li>
                    </ul>
                </details>
            <?php else: ?>
                <a
                    class="admin-nav__link<?= $activeSection === $section ? ' is-active' : '' ?>"
                    href="<?= escape(appUrl($path)) ?>"
                    <?= $activeSection === $section ? 'aria-current="page"' : '' ?>
                >
                    <i
                        class="bi <?= escape($icon) ?>"
                        aria-hidden="true"
                    ></i>

                    <span><?= escape($label) ?></span>
                </a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
</nav>