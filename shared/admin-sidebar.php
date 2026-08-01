<?php

declare(strict_types=1);

$activeSection = isset($activeSection) ? (string) $activeSection : '';
$adminLogoUrl = appUrl('public/img/logo-coratto-pet.png');

$navigationItems = [
    'dashboard' => ['Dashboard', 'admin/dashboard/index.php', 'bi-speedometer2'],
    'inventario' => ['Inventario', 'admin/inventario/index.php', 'bi-box-seam'],
    'pedidos' => ['Pedidos', 'admin/pedidos/index.php', 'bi-receipt'],
    'clientes' => ['Clientes', 'admin/clientes/index.php', 'bi-people'],
    'categorias' => ['Categorías', 'admin/categorias/index.php', 'bi-tags'],
    'marcas' => ['Marcas', 'admin/marcas/index.php', 'bi-award'],
    'proveedores' => ['Proveedores', 'admin/proveedores/index.php', 'bi-truck'],
    'configuracion' => ['Configuración', 'admin/configuracion/index.php', 'bi-sliders'],
    'importaciones' => ['Importaciones', 'admin/importaciones/index.php', 'bi-file-earmark-spreadsheet'],
    'usuarios' => ['Usuarios', 'admin/usuarios/index.php', 'bi-person-gear'],
];

if (($_SESSION['rol'] ?? '') !== 'administrador') {
    unset($navigationItems['usuarios']);
}
?>
<nav class="admin-sidebar" id="admin-sidebar" aria-label="Navegación administrativa">
    <button class="admin-sidebar__close" type="button" data-menu-close aria-label="Cerrar menú administrativo">×</button>

    <a class="admin-sidebar__brand" href="<?= escape(appUrl('admin/dashboard/index.php')) ?>">
        <img
            src="<?= escape($adminLogoUrl) ?>"
            alt="Coratto Pet"
            width="136"
            height="50"
        >
        <span>Panel administrativo</span>
    </a>

    <ul class="admin-nav">
        <?php foreach ($navigationItems as $section => [$label, $path, $icon]): ?>
            <li>
                <a
                    class="admin-nav__link<?= $activeSection === $section ? ' is-active' : '' ?>"
                    href="<?= escape(appUrl($path)) ?>"
                    <?= $activeSection === $section ? 'aria-current="page"' : '' ?>
                >
                    <i class="bi <?= escape($icon) ?>" aria-hidden="true"></i>
                    <span><?= escape($label) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
