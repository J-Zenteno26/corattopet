<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$token = is_string($_POST['token'] ?? $_GET['token'] ?? null) ? trim((string) ($_POST['token'] ?? $_GET['token'])) : '';
$tokenValidFormat = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$tokenHash = $tokenValidFormat ? hash('sha256', $token) : '';
$errors = [];
$tokenRecord = null;

if ($tokenValidFormat && $pdo instanceof PDO) {
    try {
        $lookup = $pdo->prepare('SELECT id_token,id_cliente FROM cliente_tokens_recuperacion
            WHERE token_hash=:hash AND usado_en IS NULL AND expira_en>CURRENT_TIMESTAMP LIMIT 1');
        $lookup->execute(['hash' => $tokenHash]);
        $tokenRecord = $lookup->fetch();
    } catch (Throwable $exception) {
        error_log('Customer password reset token lookup error: ' . $exception->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($tokenRecord)) {
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) $errors['general'] = 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.';
    if (strlen($password) < 10) $errors['password'] = 'La contraseña debe tener al menos 10 caracteres.';
    elseif (strlen($password) > 200) $errors['password'] = 'La contraseña es demasiado extensa.';
    if ($password !== $confirmation) $errors['password_confirmation'] = 'Las contraseñas no coinciden.';

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id_token,id_cliente FROM cliente_tokens_recuperacion
                WHERE token_hash=:hash AND usado_en IS NULL AND expira_en>CURRENT_TIMESTAMP FOR UPDATE');
            $lock->execute(['hash' => $tokenHash]);
            $lockedToken = $lock->fetch();
            if (!is_array($lockedToken)) {
                $pdo->rollBack();
                $tokenRecord = null;
            } else {
                $updateClient = $pdo->prepare('UPDATE clientes SET password_hash=:password_hash,actualizado_en=CURRENT_TIMESTAMP
                    WHERE id_cliente=:cliente AND activo=TRUE');
                $updateClient->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'cliente' => (int) $lockedToken['id_cliente']]);
                if ($updateClient->rowCount() !== 1) throw new RuntimeException('Active customer not available for password reset.');
                $consume = $pdo->prepare('UPDATE cliente_tokens_recuperacion SET usado_en=CURRENT_TIMESTAMP WHERE id_token=:id');
                $consume->execute(['id' => (int) $lockedToken['id_token']]);
                $pdo->commit();
                header('Location: ' . appUrl('public/clientes/login.php?clave_actualizada=1'), true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Customer password reset error: ' . $exception->getMessage());
            $errors['general'] = 'No fue posible actualizar la contraseña. Inténtalo nuevamente.';
        }
    }
}

$formCsrfToken = is_array($tokenRecord) ? csrfToken() : '';

renderPublicPageStart('Nueva contraseña | Coratto Pet', 'Define una nueva contraseña para tu cuenta Coratto Pet.', 'cuenta');
?>
<main id="contenido" class="customer-auth-page customer-recovery-page">
    <section class="customer-recovery-shell"><section class="customer-auth-card" aria-labelledby="reset-title">
        <a class="customer-recovery-back" href="<?= e(appUrl('public/clientes/login.php')) ?>">← Volver a iniciar sesión</a>
        <span class="customer-kicker">SEGURIDAD DE TU CUENTA</span><h1 id="reset-title">Define una nueva contraseña</h1>
        <?php if (!is_array($tokenRecord)): ?>
            <div class="customer-feedback customer-feedback--error" role="alert">Este enlace no es válido, ya fue utilizado o expiró.</div>
            <a class="button customer-primary-action" href="<?= e(appUrl('public/clientes/recuperar-clave.php')) ?>">Solicitar un nuevo enlace</a>
        <?php else: ?>
            <p>Usa al menos 10 caracteres para proteger tu cuenta.</p>
            <?php if (isset($errors['general'])): ?><div class="customer-feedback customer-feedback--error" role="alert"><?= e($errors['general']) ?></div><?php endif; ?>
            <form class="customer-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($formCsrfToken) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
                <label class="customer-field"><span>Nueva contraseña</span><input type="password" name="password" minlength="10" maxlength="200" required autocomplete="new-password"><?php if (isset($errors['password'])): ?><small class="customer-field-error"><?= e($errors['password']) ?></small><?php endif; ?></label>
                <label class="customer-field"><span>Confirmar contraseña</span><input type="password" name="password_confirmation" minlength="10" maxlength="200" required autocomplete="new-password"><?php if (isset($errors['password_confirmation'])): ?><small class="customer-field-error"><?= e($errors['password_confirmation']) ?></small><?php endif; ?></label>
                <button class="button customer-primary-action" type="submit">Guardar nueva contraseña</button>
            </form>
        <?php endif; ?>
    </section></section>
</main>
<?php renderPublicPageEnd(); ?>