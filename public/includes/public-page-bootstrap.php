<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once __DIR__ . '/consultas-publicas.php';

$config = $config ?? [];
try {
    $pdo = $pdo ?? database();
    $config = obtenerConfiguracionPublica($pdo);
} catch (Throwable) {
    $pdo = null;
}
$whatsappUrl = obtenerWhatsappPublico($config);

function renderPublicPageStart(string $title, string $description, string $currentPage, ?string $canonicalUrl = null): void
{
    $GLOBALS['currentPage'] = $currentPage;
    $canonicalUrl ??= appUrl('public/' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php')));
    $blogStylesheet = __DIR__ . '/../assets/css/blog.css';
    $customerStylesheet = __DIR__ . '/../assets/css/clientes.css';
    $historyStylesheet = __DIR__ . '/../assets/css/historia.css';
    $legalStylesheet = __DIR__ . '/../assets/css/legal.css';
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?= e($description) ?>"><title><?= e($title) ?></title><link rel="canonical" href="<?= e($canonicalUrl) ?>"><link rel="stylesheet" href="<?= e(appUrl('public/assets/css/home.css')) ?>"><link rel="stylesheet" href="<?= e(appUrl('public/assets/css/public-pages.css?v=' . filemtime(__DIR__ . '/../assets/css/public-pages.css'))) ?>"><?php if ($currentPage === 'blog' && is_file($blogStylesheet)): ?><link rel="stylesheet" href="<?= e(appUrl('public/assets/css/blog.css?v=' . filemtime($blogStylesheet))) ?>"><?php endif; ?><?php if ($currentPage === 'cuenta' && is_file($customerStylesheet)): ?><link rel="stylesheet" href="<?= e(appUrl('public/assets/css/clientes.css?v=' . filemtime($customerStylesheet))) ?>"><?php endif; ?><?php if ($currentPage === 'nosotros' && is_file($historyStylesheet)): ?><link rel="stylesheet" href="<?= e(appUrl('public/assets/css/historia.css?v=' . filemtime($historyStylesheet))) ?>"><?php endif; ?><?php if ($currentPage === 'legal' && is_file($legalStylesheet)): ?><link rel="stylesheet" href="<?= e(appUrl('public/assets/css/legal.css?v=' . filemtime($legalStylesheet))) ?>"><?php endif; ?></head><body class="public-page"><?php
    require __DIR__ . '/public-header.php';
}

function renderPublicPageEnd(): void
{
    require __DIR__ . '/public-footer.php';
    ?><script src="<?= e(appUrl('public/assets/js/public-navigation.js?v=' . filemtime(__DIR__ . '/../assets/js/public-navigation.js'))) ?>" defer></script></body></html><?php
}
