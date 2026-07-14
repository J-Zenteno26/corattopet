<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';

requireAuthentication();
$csrfToken = csrfToken();
$pageTitle = 'Dashboard';
$activeSection = 'dashboard';

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <h1>Dashboard</h1>
            <p>Bienvenido al panel administrativo de Coratto Pet.</p>
        </div>
    </header>

    <section class="admin-welcome" aria-labelledby="welcome-title">
        <h2 id="welcome-title">Hola, <?= escape((string) $_SESSION['nombre']) ?></h2>
        <p>Los módulos administrativos se encuentran en construcción.</p>
    </section>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
