<?php
declare(strict_types=1);
require __DIR__ . '/includes/public-page-bootstrap.php';
require __DIR__ . '/includes/contact-service.php';

$errors = [];
$values = ['nombre' => '', 'correo' => '', 'telefono' => '', 'asunto' => '', 'mensaje' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_)
        $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    if (!validateCsrfToken($_POST['csrf_token'] ?? null))
        $errors[] = 'La sesión del formulario expiró. Inténtalo nuevamente.';
    if ($values['nombre'] === '' || mb_strlen($values['nombre']) > 120)
        $errors[] = 'Ingresa un nombre válido.';
    if (!filter_var($values['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['correo']) > 160)
        $errors[] = 'Ingresa un correo electrónico válido.';

    if ($values['telefono'] === '' || mb_strlen($values['telefono']) > 40)
        $errors[] = 'Ingresa un teléfono válido.';

    if ($values['asunto'] === '' || mb_strlen($values['asunto']) > 160)
        $errors[] = 'Ingresa un asunto válido.';
    if ($values['mensaje'] === '' || mb_strlen($values['mensaje']) > 5000)
        $errors[] = 'Ingresa un mensaje de hasta 5.000 caracteres.';
    if (!verifyRecaptcha(is_string($_POST['g-recaptcha-response'] ?? null) ? $_POST['g-recaptcha-response'] : ''))
        $errors[] = 'No pudimos validar reCAPTCHA.';
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
$csrfToken = csrfToken();
renderPublicPageStart(
    'Contacto | Coratto Pet',
    'Contacta al equipo de Coratto Pet. Estamos aquí para orientarte y resolver tus dudas.',
    'contacto'
);
?>

<link
    rel="stylesheet"
    href="<?= e(appUrl('public/assets/css/contacto.css?v=' . filemtime(__DIR__ . '/assets/css/contacto.css'))) ?>"
>

<main id="contenido" class="contact-page">
    <div class="public-shell contact-page__shell">

        <section class="contact-intro">
            <span class="contact-eyebrow">HABLEMOS</span>

            <h1>¿Cómo podemos ayudarte?</h1>

            <p>
                Si tienes dudas sobre productos, alimentación, tu pedido o simplemente
                necesitas orientación, escríbenos. Nuestro equipo estará encantado de ayudarte.
            </p>

            <div class="contact-intro__points">
                <span>Orientación cercana</span>
                <span>Atención personalizada</span>
                <span>Respuesta directa</span>
            </div>
        </section>

        <section class="contact-card">
            <header class="contact-card__header">
                <div>
                    <span>CUÉNTANOS</span>
                    <h2>Envíanos un mensaje</h2>
                </div>

                <p>
                    Completa tus datos y cuéntanos cómo podemos ayudarte.
                    Todos los campos son obligatorios.
                </p>
            </header>

            <?php if ($success): ?>
                <div class="contact-feedback contact-feedback--success" role="status">
                    <strong>Mensaje enviado</strong>
                    <span>
                        Recibimos tu consulta correctamente. Te responderemos lo antes posible.
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="contact-feedback contact-feedback--error" role="alert">
                    <strong>Revisa los datos ingresados</strong>

                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form
                class="contact-form"
                method="post"
                action="<?= e(appUrl('public/contacto.php')) ?>"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <div class="contact-form__grid">
                    <label class="contact-field">
                        <span>Nombre completo</span>

                        <input
                            type="text"
                            name="nombre"
                            maxlength="120"
                            autocomplete="name"
                            placeholder="¿Cómo te llamas?"
                            required
                            value="<?= e($values['nombre']) ?>"
                        >
                    </label>

                    <label class="contact-field">
                        <span>Correo electrónico</span>

                        <input
                            type="email"
                            name="correo"
                            maxlength="160"
                            autocomplete="email"
                            placeholder="nombre@correo.cl"
                            required
                            value="<?= e($values['correo']) ?>"
                        >
                    </label>

                    <label class="contact-field">
                        <span>Teléfono</span>

                        <input
                            type="tel"
                            name="telefono"
                            maxlength="40"
                            autocomplete="tel"
                            placeholder="+56 9 1234 5678"
                            required
                            value="<?= e($values['telefono']) ?>"
                        >
                    </label>

                    <label class="contact-field">
                        <span>Asunto</span>

                        <input
                            type="text"
                            name="asunto"
                            maxlength="160"
                            placeholder="¿En qué podemos ayudarte?"
                            required
                            value="<?= e($values['asunto']) ?>"
                        >
                    </label>

                    <label class="contact-field contact-field--wide">
                        <span>Mensaje</span>

                        <textarea
                            name="mensaje"
                            maxlength="5000"
                            placeholder="Cuéntanos un poco más..."
                            required
                        ><?= e($values['mensaje']) ?></textarea>
                    </label>
                </div>

                <div class="contact-form__bottom">
                    <div class="contact-recaptcha">
                        <span class="contact-recaptcha__label">
                            Verificación de seguridad
                        </span>

                        <div
                            class="g-recaptcha"
                            data-sitekey="<?= e((string) env('RECAPTCHA_SITE_KEY', '')) ?>"
                        ></div>
                    </div>

                    <button class="button contact-submit" type="submit">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 12 20 4l-6 16-3-6-7-2Z" />
                            <path d="m11 14 3-4" />
                        </svg>

                        Enviar mensaje
                    </button>
                </div>
            </form>
        </section>

    </div>
</main>

<script
    src="https://www.google.com/recaptcha/api.js"
    async
    defer
></script>

<?php renderPublicPageEnd(); ?>