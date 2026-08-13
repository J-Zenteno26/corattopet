<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

$returnRoute = rutaRetornoCliente(
    $_POST['return'] ?? $_GET['return'] ?? null
);

if (isset($_SESSION['id_cliente'])) {
    header('Location: ' . appUrl($returnRoute), true, 302);
    exit;
}

$error = '';
$email = '';
$passwordReset = (($_GET['clave_actualizada'] ?? null) === '1');

$csrfToken = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null)
        ? trim($_POST['email'])
        : '';
    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($password) > 200
    ) {
        $error = 'El correo o la contraseña no son correctos.';
    } elseif ($pdo instanceof PDO) {
        $statement = $pdo->prepare(
            "SELECT
                id_cliente,
                nombre,
                email,
                password_hash,
                activo
             FROM clientes
             WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
             LIMIT 1"
        );
        $statement->execute(['email' => $email]);
        $cliente = $statement->fetch();

        if (
            !is_array($cliente)
            || !valorBooleanoPublico($cliente['activo'])
            || !is_string($cliente['password_hash'])
            || $cliente['password_hash'] === ''
            || !password_verify($password, $cliente['password_hash'])
        ) {
            $error = 'El correo o la contraseña no son correctos.';
        } else {
            session_regenerate_id(true);
            sincronizarSesionClientePublico($cliente);

            header('Location: ' . appUrl($returnRoute), true, 303);
            exit;
        }
    } else {
        $error = 'El acceso no está disponible temporalmente.';
    }
}

renderPublicPageStart(
    'Iniciar sesión | Coratto Pet',
    'Accede a tu cuenta de cliente Coratto Pet.',
    'cuenta'
);
?>
<main id="contenido" class="customer-auth-page">
    <section class="customer-auth-shell">
        <div class="customer-auth-intro">
            <span>Tu espacio Coratto</span>
            <h1>Todo lo que necesitas para seguir cuidándolos</h1>
            <p>
                Tu espacio Coratto reúne tus compras, el seguimiento de tus pedidos
                y tus datos para que volver por sus favoritos sea mucho más simple.
            </p>
            <div class="customer-auth-benefits">
                <span>Tus compras siempre contigo</span>
                <span>Sigue cada pedido fácilmente</span>
                <span>Vuelve a encontrar sus favoritos</span>
                <span>Una experiencia pensada para ti y tu mascota</span>
            </div>
        </div>

        <section class="customer-auth-card" aria-labelledby="login-title">
            <span class="customer-kicker">INICIAR SESIÓN</span>
            <h2 id="login-title">Accede a tu cuenta</h2>
            <p>Usa el correo con el que creaste tu cuenta.</p>

            <?php if ($error !== ''): ?>
                <div class="customer-feedback customer-feedback--error" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
            <?php if ($passwordReset): ?>
                <div class="customer-feedback customer-feedback--success" role="status">
                    Tu contraseña fue actualizada. Ya puedes iniciar sesión.
                </div>
            <?php endif; ?>

            <form class="customer-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="return" value="<?= e($returnRoute) ?>">

                <label class="customer-field">
                    <span>Correo electrónico</span>
                    <input
                        type="email"
                        name="email"
                        maxlength="160"
                        required
                        value="<?= e($email) ?>"
                        autocomplete="email"
                    >
                </label>

                <label class="customer-field">
                    <span>Contraseña</span>
                    <input
                        type="password"
                        name="password"
                        maxlength="200"
                        required
                        autocomplete="current-password"
                    >
                </label>

                <a class="customer-password-recovery-link" href="<?= e(appUrl('public/clientes/recuperar-clave.php')) ?>">
                    ¿Olvidaste tu contraseña?
                </a>

                <button class="button customer-primary-action" type="submit">
                    Ingresar
                </button>
            </form>

            <div class="customer-auth-links">
                <span>¿Todavía no tienes cuenta?</span>
                <a href="<?= e(appUrl('public/clientes/registro.php?return=' . rawurlencode($returnRoute))) ?>">
                    Crear mi cuenta
                </a>
            </div>
        </section>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
