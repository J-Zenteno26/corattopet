<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';

if (isAuthenticated()) {
    header('Location: ' . appUrl('admin/dashboard/index.php'), true, 302);
    exit;
}

$hasError = isset($_GET['error']);
$csrfToken = csrfToken();

$loginCssPath = __DIR__ . '/login.css';
$loginCssVersion = is_file($loginCssPath) ? (string) filemtime($loginCssPath) : '1';

$logoCorattoUrl = appUrl('public/img/logo-coratto-pet.png');
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso administrativo | Coratto Pet</title>
    <link rel="stylesheet" href="<?= escape(appUrl('admin/auth/login.css') . '?v=' . $loginCssVersion) ?>">
</head>

<body class="admin-login-page">
    <main class="admin-login-shell">
        <section class="admin-login-brand" aria-labelledby="brand-title">
            <div class="admin-login-brand__content">
                <figure class="admin-login-logo-wrap" aria-label="Coratto Pet">
                    <img class="admin-login-logo" src="<?= escape($logoCorattoUrl) ?>" alt="Coratto Pet" width="148"
                        height="148">
                </figure>
                <p class="admin-login-kicker">Coratto Pet</p>
                <h1 id="brand-title">Panel administrativo</h1>
                <p class="admin-login-brand__lead">Gestión interna de tienda con una mirada clara de toda la operación.
                </p>
                <ul class="admin-login-features" aria-label="Herramientas disponibles">
                    <li><span aria-hidden="true"></span>Inventario y catálogo</li>
                    <li><span aria-hidden="true"></span>Pedidos y clientes</li>
                    <li><span aria-hidden="true"></span>Configuración y accesos</li>
                </ul>
            </div>
            <p class="admin-login-brand__foot">Un solo lugar para cuidar cada detalle de Coratto Pet.</p>
        </section>

        <section class="admin-login-access" aria-labelledby="login-title">
            <div class="admin-login-card">
                <div class="admin-login-card__header">
                    <span class="admin-login-security" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M12 2 4.5 5v5.8c0 4.8 3.1 9.2 7.5 10.7 4.4-1.5 7.5-5.9 7.5-10.7V5L12 2Zm0 2.2 5.4 2.1v4.5c0 3.7-2.2 7.2-5.4 8.5-3.2-1.3-5.4-4.8-5.4-8.5V6.3L12 4.2Zm0 3.2a2.7 2.7 0 0 0-2.7 2.7v1H8.2v5.2h7.6v-5.2h-1.1v-1A2.7 2.7 0 0 0 12 7.4Zm0 1.7c.6 0 1 .4 1 1v1h-2v-1c0-.6.4-1 1-1Z" />
                        </svg>
                    </span>
                    <p>Acceso protegido</p>
                    <h2 id="login-title">Iniciar sesión</h2>
                    <span>Accede al panel administrativo de Coratto Pet.</span>
                </div>
                <?php if ($hasError): ?>
                    <div class="admin-login-alert" role="alert">
                        <strong>No fue posible iniciar sesión</strong>
                        <span>No fue posible iniciar sesión con los datos ingresados.</span>
                    </div>
                <?php endif; ?>
                <form class="admin-login-form" method="post"
                    action="<?= escape(appUrl('admin/auth/procesar-login.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                    <div class="admin-login-field">
                        <label for="email">Correo electrónico</label>
                        <div class="admin-login-input">
                            <span aria-hidden="true">@</span>
                            <input id="email" name="email" type="email" autocomplete="username"
                                placeholder="usuario@coratto.cl" required autofocus>
                        </div>
                    </div>
                    <div class="admin-login-field">
                        <label for="password">Contraseña</label>

                        <div class="admin-login-input admin-login-input--password">
                            <span class="admin-login-lock" aria-hidden="true"></span>

                            <input id="password" name="password" type="password" autocomplete="current-password"
                                placeholder="Ingresa tu contraseña" required>

                            <button class="admin-login-password-toggle" type="button" aria-label="Mostrar contraseña"
                                aria-pressed="false" data-password-toggle>
                                <svg class="admin-login-eye admin-login-eye--visible" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path
                                        d="M12 5c-5.5 0-9.5 5.1-9.7 5.3a1 1 0 0 0 0 1.4C2.5 11.9 6.5 17 12 17s9.5-5.1 9.7-5.3a1 1 0 0 0 0-1.4C21.5 10.1 17.5 5 12 5Zm0 10c-3.7 0-6.8-2.9-7.6-4 .8-1.1 3.9-4 7.6-4s6.8 2.9 7.6 4c-.8 1.1-3.9 4-7.6 4Zm0-6.5A2.5 2.5 0 1 0 12 13a2.5 2.5 0 0 0 0-5Z" />
                                </svg>

                                <svg class="admin-login-eye admin-login-eye--hidden" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path
                                        d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A10.5 10.5 0 0 1 12 20c-5.5 0-9.5-5.1-9.7-5.3a1 1 0 0 1 0-1.4 18.3 18.3 0 0 1 3.2-3.4L2 6.3 3.3 5l1.8 1.8Zm4 7.3a14.7 14.7 0 0 0-2.9 4c.8 1.1 3.9 4 7.6 4 1.4 0 2.7-.4 3.8-1l-1.7-1.7A4 4 0 0 1 9.4 9.9L7.3 7.8Zm4.7-4.3c5.5 0 9.5 5.1 9.7 5.3a1 1 0 0 1 0 1.4 18 18 0 0 1-2.5 2.8l-1.4-1.4a15.4 15.4 0 0 0 1.8-2.1c-.8-1.1-3.9-4-7.6-4-.6 0-1.2.1-1.8.2L8.6 5.6c1.1-.4 2.2-.6 3.4-.6Z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button class="admin-login-button" type="submit"><span>Entrar al panel</span><span
                            aria-hidden="true">›</span></button>
                </form>
                <p class="admin-login-help"><span aria-hidden="true"></span>Acceso exclusivo para usuarios internos
                    autorizados.</p>
            </div>
        </section>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.querySelector('[data-password-toggle]');

        if (passwordInput && passwordToggle) {
            passwordToggle.addEventListener('click', () => {
                const passwordIsVisible = passwordInput.type === 'text';

                passwordInput.type = passwordIsVisible ? 'password' : 'text';
                passwordToggle.setAttribute(
                    'aria-pressed',
                    passwordIsVisible ? 'false' : 'true'
                );
                passwordToggle.setAttribute(
                    'aria-label',
                    passwordIsVisible ? 'Mostrar contraseña' : 'Ocultar contraseña'
                );

                passwordInput.focus();
            });
        }
    </script>
</body>

</html>