<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';

requireAuthentication();
$csrfToken = csrfToken();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel administrativo | Coratto Pet</title>
</head>
<body>
    <main>
        <h1>Acceso correcto</h1>
        <p>Usuario: <?= escape((string) $_SESSION['nombre']) ?></p>
        <p>Rol: <?= escape((string) $_SESSION['rol']) ?></p>
        <form method="post" action="<?= escape(appUrl('admin/auth/cerrar-sesion.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <button type="submit">Cerrar sesión</button>
        </form>
    </main>
</body>
</html>
