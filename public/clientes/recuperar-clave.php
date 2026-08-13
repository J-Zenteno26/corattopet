<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
require_once dirname(__DIR__, 2) . '/shared/servicio-correo.php';

header('X-Robots-Tag: noindex, nofollow, noarchive');

$genericMessage = 'Si existe una cuenta asociada a ese correo, te enviaremos instrucciones.';
$submitted = false;
$csrfError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $csrfError = true;
    } else {
        $submitted = true;
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

        if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 160 && $pdo instanceof PDO) {
            try {
                $statement = $pdo->prepare("SELECT id_cliente,email FROM clientes
                    WHERE LOWER(TRIM(email))=LOWER(TRIM(:email)) AND activo=TRUE
                      AND password_hash IS NOT NULL AND TRIM(password_hash)<>'' LIMIT 1");
                $statement->execute(['email' => $email]);
                $client = $statement->fetch();

                if (is_array($client)) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $pdo->beginTransaction();
                    $invalidate = $pdo->prepare('UPDATE cliente_tokens_recuperacion SET usado_en=CURRENT_TIMESTAMP
                        WHERE id_cliente=:cliente AND usado_en IS NULL');
                    $invalidate->execute(['cliente' => (int) $client['id_cliente']]);
                    $insert = $pdo->prepare("INSERT INTO cliente_tokens_recuperacion
                        (id_cliente,token_hash,expira_en) VALUES (:cliente,:hash,CURRENT_TIMESTAMP + INTERVAL '30 minutes')");
                    $insert->execute(['cliente' => (int) $client['id_cliente'], 'hash' => $tokenHash]);
                    $pdo->commit();

                    $resetUrl = appUrl(
                        'public/clientes/restablecer-clave.php?token=' . rawurlencode($token)
                    );

                    $body = "Hola,\n\n"
                        . "Recibimos una solicitud para cambiar la contraseña de tu cuenta Coratto Pet.\n\n"
                        . "Crea una nueva contraseña desde este enlace. Estará disponible durante 30 minutos:\n"
                        . $resetUrl
                        . "\n\nSi no solicitaste este cambio, puedes ignorar este mensaje.\n\n"
                        . "Coratto Pet";

                    $resetUrlHtml = htmlspecialchars(
                        $resetUrl,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $bodyHtml = construirCorreoHtmlCoratto(
                        'Recupera tu contraseña',
                        '<p style="margin:0 0 15px;font-size:16px;line-height:1.6;">Hola,</p>'
                        . '<p style="margin:0 0 18px;font-size:16px;line-height:1.6;">Recibimos una solicitud para cambiar la contraseña de tu cuenta Coratto Pet.</p>'
                        . '<p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#6f5d52;">El enlace estará disponible durante <strong style="color:#4a3025;">30 minutos</strong> y solo puede utilizarse una vez.</p>'
                        . '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;"><tr><td align="center" bgcolor="#c98a32" style="border-radius:999px;">'
                        . '<a href="' . $resetUrlHtml . '" style="display:inline-block;padding:14px 26px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">Crear nueva contraseña</a>'
                        . '</td></tr></table>'
                        . '<div style="padding:16px 18px;border-radius:12px;background:#f3e5d2;color:#655146;font-size:13px;line-height:1.55;">'
                        . 'Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña actual seguirá funcionando.'
                        . '</div>'
                    );

                    try {
                        enviarCorreoTransaccional(
                            (string) $client['email'],
                            'Recupera tu contraseña de Coratto Pet',
                            $body,
                            null,
                            $bodyHtml
                        );
                    } catch (Throwable $mailException) {
                        error_log('Customer password recovery mail error: ' . $mailException->getMessage());
                        $disable = $pdo->prepare('UPDATE cliente_tokens_recuperacion SET usado_en=CURRENT_TIMESTAMP WHERE token_hash=:hash AND usado_en IS NULL');
                        $disable->execute(['hash' => $tokenHash]);
                    }
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Customer password recovery request error: ' . $exception->getMessage());
            }
        }
    }
}

renderPublicPageStart('Recuperar contraseña | Coratto Pet', 'Recupera el acceso a tu cuenta Coratto Pet.', 'cuenta');
?>
<main id="contenido" class="customer-auth-page customer-recovery-page">
    <section class="customer-recovery-shell">
        <section class="customer-auth-card" aria-labelledby="recovery-title">
            <a class="customer-recovery-back" href="<?= e(appUrl('public/clientes/login.php')) ?>">← Volver a iniciar sesión</a>
            <span class="customer-kicker">RECUPERAR ACCESO</span>
            <h1 id="recovery-title">¿Olvidaste tu contraseña?</h1>
            <p>Ingresa tu correo y te enviaremos un enlace para definir una nueva contraseña.</p>
            <?php if ($csrfError): ?><div class="customer-feedback customer-feedback--error" role="alert">La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.</div><?php endif; ?>
            <?php if ($submitted): ?><div class="customer-feedback customer-feedback--success" role="status"><?= e($genericMessage) ?></div><?php endif; ?>
            <form class="customer-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <label class="customer-field"><span>Correo electrónico</span><input type="email" name="email" maxlength="160" required autocomplete="email"></label>
                <button class="button customer-primary-action" type="submit">Enviar instrucciones</button>
            </form>
        </section>
    </section>
</main>
<?php renderPublicPageEnd(); ?>
