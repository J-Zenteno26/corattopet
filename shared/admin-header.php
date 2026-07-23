<?php

declare(strict_types=1);

$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'Panel administrativo';
$csrfToken = isset($csrfToken) ? (string) $csrfToken : csrfToken();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape($pageTitle) ?> | Coratto Pet</title>
        <?php
        $adminCssPath = dirname(__DIR__) . '/public/css/admin.css';
        $adminCssVersion = is_file($adminCssPath)
            ? (string) filemtime($adminCssPath)
            : '1';
        ?>

        <link
            rel="stylesheet"
            href="<?= escape(appUrl('public/css/admin.css') . '?v=' . $adminCssVersion) ?>"
        >
        <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        >
</head>
<body class="admin-body">
    <a class="admin-skip-link" href="#contenido-principal">Saltar al contenido</a>
    <header class="admin-header">
        <div class="admin-header__brand">
            <button
                class="admin-menu-toggle"
                type="button"
                aria-label="Abrir menú administrativo"
                aria-controls="admin-sidebar"
                aria-expanded="false"
            >☰</button>
            <a href="<?= escape(appUrl('admin/dashboard/index.php')) ?>">Coratto Pet</a>
            <span>Administración</span>
        </div>
        <div class="admin-header__account">
            <div class="admin-user">
                <strong><?= escape((string) $_SESSION['nombre']) ?></strong>
                <span><?= escape((string) $_SESSION['rol']) ?></span>
            </div>
            <form method="post" action="<?= escape(appUrl('admin/auth/cerrar-sesion.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                <button class="admin-logout" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </header>
    <div class="admin-overlay" data-menu-close hidden></div>
    <div class="admin-layout">
