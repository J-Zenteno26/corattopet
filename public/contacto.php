<?php
declare(strict_types=1);
require __DIR__ . '/includes/public-page-bootstrap.php';
require __DIR__ . '/includes/contact-service.php';

$errors = [];
$values = ['nombre' => '', 'correo' => '', 'telefono' => '', 'asunto' => '', 'mensaje' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) $errors[] = 'La sesión del formulario expiró. Inténtalo nuevamente.';
    if ($values['nombre'] === '' || mb_strlen($values['nombre']) > 120) $errors[] = 'Ingresa un nombre válido.';
    if (!filter_var($values['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['correo']) > 160) $errors[] = 'Ingresa un correo electrónico válido.';
    if (mb_strlen($values['telefono']) > 40) $errors[] = 'El teléfono es demasiado largo.';
    if ($values['asunto'] === '' || mb_strlen($values['asunto']) > 160) $errors[] = 'Ingresa un asunto válido.';
    if ($values['mensaje'] === '' || mb_strlen($values['mensaje']) > 5000) $errors[] = 'Ingresa un mensaje de hasta 5.000 caracteres.';
    if (!verifyRecaptcha(is_string($_POST['g-recaptcha-response'] ?? null) ? $_POST['g-recaptcha-response'] : '')) $errors[] = 'No pudimos validar reCAPTCHA.';
    if ($errors === []) {
        try {
            sendContactEmail($values);
            $_SESSION['contact_success'] = true;
            header('Location: ' . appUrl('public/contacto.php?enviado=1'), true, 303);
            exit;
        } catch (Throwable $exception) {
            error_log('Contact form delivery failed: ' . $exception->getMessage());
            $errors[] = 'No pudimos enviar tu mensaje en este momento. Inténtalo nuevamente más tarde.';
        }
    }
}
$success = isset($_GET['enviado'], $_SESSION['contact_success']);
unset($_SESSION['contact_success']);
renderPublicPageStart('Contacto | Coratto Pet', 'Contacta al equipo de Coratto Pet.', 'contacto');
?>
<main id="contenido"><div class="public-shell"><section class="public-form-card"><span class="public-eyebrow">HABLEMOS</span><h1>¿Cómo podemos ayudarte?</h1>
<?php if ($success): ?><p class="form-success">Tu mensaje fue enviado correctamente. Te responderemos lo antes posible.</p><?php endif; ?>
<?php if ($errors): ?><div class="form-errors" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="public-form" method="post" action="<?= e(appUrl('public/contacto.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><label>Nombre<input name="nombre" maxlength="120" required value="<?= e($values['nombre']) ?>"></label><label>Correo electrónico<input type="email" name="correo" maxlength="160" required value="<?= e($values['correo']) ?>"></label><label>Teléfono <span class="field-note">Opcional</span><input type="tel" name="telefono" maxlength="40" value="<?= e($values['telefono']) ?>"></label><label>Asunto<input name="asunto" maxlength="160" required value="<?= e($values['asunto']) ?>"></label><label class="full">Mensaje<textarea name="mensaje" maxlength="5000" required><?= e($values['mensaje']) ?></textarea></label><div class="full g-recaptcha" data-sitekey="<?= e((string) env('RECAPTCHA_SITE_KEY', '')) ?>"></div><div class="full"><button class="button" type="submit">Enviar mensaje</button></div></form></section></div></main>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php renderPublicPageEnd(); ?>
