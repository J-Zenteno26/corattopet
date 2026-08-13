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

$errors = [];
$values = [
    'nombre' => '',
    'apellido' => '',
    'email' => '',
    'telefono' => '',
];

$csrfToken = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = is_string($_POST[$key] ?? null)
            ? trim($_POST[$key])
            : '';
    }

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';
    $confirm = is_string($_POST['password_confirm'] ?? null)
        ? $_POST['password_confirm']
        : '';

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La sesión del formulario expiró.';
    }

    if (
        $values['nombre'] === ''
        || mb_strlen($values['nombre']) > 100
    ) {
        $errors[] = 'Ingresa un nombre válido.';
    }

    if (
        $values['apellido'] === ''
        || mb_strlen($values['apellido']) > 100
    ) {
        $errors[] = 'Ingresa un apellido válido.';
    }

    if (
        !filter_var($values['email'], FILTER_VALIDATE_EMAIL)
        || mb_strlen($values['email']) > 160
    ) {
        $errors[] = 'Ingresa un correo válido.';
    }

    if (mb_strlen($values['telefono']) > 40) {
        $errors[] = 'El teléfono es demasiado largo.';
    }

    if (strlen($password) < 10 || strlen($password) > 200) {
        $errors[] = 'La contraseña debe tener al menos 10 caracteres.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Las contraseñas no coinciden.';
    }

    if (($_POST['acepta'] ?? '') !== '1') {
        $errors[] = 'Debes aceptar los términos y la política de privacidad.';
    }

    if ($errors === [] && $pdo instanceof PDO) {
        $emailNormalizado = mb_strtolower($values['email']);

        $check = $pdo->prepare(
            "SELECT
                id_cliente,
                password_hash
             FROM clientes
             WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
             ORDER BY id_cliente ASC
             LIMIT 1"
        );
        $check->execute(['email' => $emailNormalizado]);
        $existente = $check->fetch();

        if (
            is_array($existente)
            && is_string($existente['password_hash'] ?? null)
            && trim((string) $existente['password_hash']) !== ''
        ) {
            $errors[] = 'Ya existe una cuenta asociada a ese correo.';
        } elseif (is_array($existente)) {
            // Una compra como invitado ya pudo crear este cliente.
            // Convertimos ese mismo registro en cuenta para conservar sus pedidos.
            $statement = $pdo->prepare(
                "UPDATE clientes
                 SET
                    nombre = :nombre,
                    apellido = :apellido,
                    telefono = COALESCE(NULLIF(:telefono, ''), telefono),
                    password_hash = :password_hash,
                    activo = TRUE,
                    actualizado_en = CURRENT_TIMESTAMP
                 WHERE id_cliente = :id_cliente"
            );
            $statement->execute([
                'nombre' => $values['nombre'],
                'apellido' => $values['apellido'],
                'telefono' => $values['telefono'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id_cliente' => (int) $existente['id_cliente'],
            ]);

            session_regenerate_id(true);
            $_SESSION['id_cliente'] = (int) $existente['id_cliente'];
            $_SESSION['cliente_nombre'] = $values['nombre'];
            $_SESSION['cliente_email'] = $emailNormalizado;

            header('Location: ' . appUrl($returnRoute), true, 303);
            exit;
        } else {
            $statement = $pdo->prepare(
                "INSERT INTO clientes (
                    nombre,
                    apellido,
                    email,
                    telefono,
                    password_hash,
                    activo,
                    email_verificado,
                    creado_en,
                    actualizado_en
                 ) VALUES (
                    :nombre,
                    :apellido,
                    :email,
                    :telefono,
                    :password_hash,
                    TRUE,
                    FALSE,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                 )
                 RETURNING id_cliente"
            );
            $statement->execute([
                'nombre' => $values['nombre'],
                'apellido' => $values['apellido'],
                'email' => $emailNormalizado,
                'telefono' => $values['telefono'] !== ''
                    ? $values['telefono']
                    : null,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            session_regenerate_id(true);
            $_SESSION['id_cliente'] = (int) $statement->fetchColumn();
            $_SESSION['cliente_nombre'] = $values['nombre'];
            $_SESSION['cliente_email'] = $emailNormalizado;

            header('Location: ' . appUrl($returnRoute), true, 303);
            exit;
        }
    }

    if (!($pdo instanceof PDO) && $errors === []) {
        $errors[] = 'El registro no está disponible temporalmente.';
    }
}

renderPublicPageStart(
    'Crear cuenta | Coratto Pet',
    'Crea tu cuenta de cliente en Coratto Pet.',
    'cuenta'
);
?>
<main id="contenido" class="customer-auth-page">
    <section class="customer-auth-shell">
        <div class="customer-auth-intro">
            <span>Bienvenido a Coratto</span>
            <h1>Tu cuenta, tus pedidos, todo más simple</h1>
            <p>
                Crea tu cuenta para mantener tus compras asociadas y revisar
                fácilmente el estado de tus pedidos.
            </p>
            <div class="customer-auth-benefits">
                <span>Conserva tu carrito actual</span>
                <span>Accede a compras anteriores</span>
                <span>Administra tus datos</span>
            </div>
        </div>

        <section class="customer-auth-card" aria-labelledby="register-title">
            <span class="customer-kicker">CREAR CUENTA</span>
            <h2 id="register-title">Empecemos</h2>
            <p>Completa tus datos principales.</p>

            <?php if ($errors !== []): ?>
                <div class="customer-feedback customer-feedback--error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="customer-form customer-form--two-columns" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="return" value="<?= e($returnRoute) ?>">

                <label class="customer-field">
                    <span>Nombre</span>
                    <input
                        name="nombre"
                        maxlength="100"
                        required
                        value="<?= e($values['nombre']) ?>"
                        autocomplete="given-name"
                    >
                </label>

                <label class="customer-field">
                    <span>Apellido</span>
                    <input
                        name="apellido"
                        maxlength="100"
                        required
                        value="<?= e($values['apellido']) ?>"
                        autocomplete="family-name"
                    >
                </label>

                <label class="customer-field customer-field--full">
                    <span>Correo electrónico</span>
                    <input
                        type="email"
                        name="email"
                        maxlength="160"
                        required
                        value="<?= e($values['email']) ?>"
                        autocomplete="email"
                    >
                </label>

                <label class="customer-field customer-field--full">
                    <span>Teléfono <small>Opcional</small></span>
                    <input
                        name="telefono"
                        maxlength="40"
                        value="<?= e($values['telefono']) ?>"
                        autocomplete="tel"
                    >
                </label>

                <label class="customer-field">
                    <span>Contraseña</span>
                    <input
                        type="password"
                        name="password"
                        minlength="10"
                        maxlength="200"
                        required
                        autocomplete="new-password"
                    >
                </label>

                <label class="customer-field">
                    <span>Confirmar contraseña</span>
                    <input
                        type="password"
                        name="password_confirm"
                        minlength="10"
                        maxlength="200"
                        required
                        autocomplete="new-password"
                    >
                </label>

                <label class="customer-check customer-field--full">
                    <input type="checkbox" name="acepta" value="1" required>
                    <span>Acepto los <a href="<?= e(appUrl('public/terminos-condiciones.php')) ?>">términos</a> y la <a href="<?= e(appUrl('public/politica-privacidad.php')) ?>">política de privacidad</a>.</span>
                </label>

                <div class="customer-field--full">
                    <button class="button customer-primary-action" type="submit">
                        Crear mi cuenta
                    </button>
                </div>
            </form>

            <div class="customer-auth-links">
                <span>¿Ya tienes una cuenta?</span>
                <a href="<?= e(appUrl('public/clientes/login.php?return=' . rawurlencode($returnRoute))) ?>">
                    Iniciar sesión
                </a>
            </div>
        </section>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
