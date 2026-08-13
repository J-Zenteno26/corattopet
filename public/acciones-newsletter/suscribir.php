<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'mensaje' => 'Método no permitido.',
    ]);

    exit;
}

try {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new InvalidArgumentException(
            'La sesión expiró. Recarga la página e inténtalo nuevamente.'
        );
    }

    $email = is_scalar($_POST['email'] ?? null)
        ? mb_strtolower(trim((string) $_POST['email']))
        : '';

    if (
        $email === ''
        || mb_strlen($email) > 160
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        throw new InvalidArgumentException(
            'Ingresa un correo electrónico válido.'
        );
    }

    $pdo = database();

    $statement = $pdo->prepare(
        "INSERT INTO newsletter_suscriptores (
            email,
            activo
        ) VALUES (
            :email,
            TRUE
        )
        ON CONFLICT (email)
        DO UPDATE SET
            activo = TRUE,
            actualizado_en = NOW()"
    );

    $statement->execute([
        'email' => $email,
    ]);

    echo json_encode([
        'ok' => true,
        'mensaje' => '¡Gracias por suscribirte! Ya eres parte de Coratto Pet.',
    ]);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'mensaje' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log(
        'Error al registrar newsletter: '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'mensaje' => 'No pudimos registrar tu suscripción en este momento.',
    ]);
}