<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';

if (isAuthenticated()) {
    header('Location: ' . appUrl('admin/dashboard/index.php'), true, 302);
    exit;
}

$hasError = isset($_GET['error']);
$csrfToken = csrfToken();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso administrativo | Coratto Pet</title>
    <link rel="stylesheet" href="<?= escape(appUrl('admin/auth/login.css')) ?>">
</head>
<body>
    <main class="login-container">
        <section class="login-card" aria-labelledby="login-title">
            <h1 id="login-title">Acceso administrativo</h1>
            <?php if ($hasError): ?>
                <p class="error-message" role="alert">No fue posible iniciar sesión con los datos ingresados.</p>
            <?php endif; ?>
            <form method="post" action="<?= escape(appUrl('admin/auth/procesar-login.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" autocomplete="username" required>

                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>

                <button type="submit">Ingresar</button>
            </form>
        </section>
    </main>
</body>
</html>
