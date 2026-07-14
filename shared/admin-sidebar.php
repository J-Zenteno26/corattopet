<?php

declare(strict_types=1);

$activeSection = isset($activeSection) ? (string) $activeSection : '';
$navigationItems = [
    'dashboard' => ['Dashboard', 'admin/dashboard/index.php'],
    'inventario' => ['Inventario', 'admin/inventario/index.php'],
    'categorias' => ['Categorías', 'admin/categorias/index.php'],
    'marcas' => ['Marcas', 'admin/marcas/index.php'],
    'importaciones' => ['Importaciones', 'admin/inventario/importacion/index.php'],
    'usuarios' => ['Usuarios', 'admin/usuarios/index.php'],
];
?>
<nav class="admin-sidebar" id="admin-sidebar" aria-label="Navegación administrativa">
    <ul class="admin-nav">
        <?php foreach ($navigationItems as $section => [$label, $path]): ?>
            <li>
                <a
                    class="admin-nav__link<?= $activeSection === $section ? ' is-active' : '' ?>"
                    href="<?= escape(appUrl($path)) ?>"
                    <?= $activeSection === $section ? 'aria-current="page"' : '' ?>
                ><?= escape($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
