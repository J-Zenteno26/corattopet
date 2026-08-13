<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once __DIR__ . '/includes/funciones-clientes-publicos.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

try {
    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        throw new InvalidArgumentException('La sesión del formulario expiró.');
    }

    $pdo = database();
    $cliente = exigirClientePublico($pdo, 'public/clientes/seguridad.php');

    $actual = is_string($_POST['actual'] ?? null) ? $_POST['actual'] : '';
    $nueva = is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '';
    $confirmacion = is_string($_POST['confirmacion'] ?? null)
        ? $_POST['confirmacion']
        : '';

    if (
        !is_string($cliente['password_hash'] ?? null)
        || !password_verify($actual, (string) $cliente['password_hash'])
    ) {
        throw new InvalidArgumentException('La contraseña actual no es correcta.');
    }

    if (strlen($nueva) < 10 || strlen($nueva) > 200) {
        throw new InvalidArgumentException('La nueva contraseña debe tener al menos 10 caracteres.');
    }

    if ($nueva !== $confirmacion) {
        throw new InvalidArgumentException('Las nuevas contraseñas no coinciden.');
    }

    $statement = $pdo->prepare(
        "UPDATE clientes
         SET password_hash = :password_hash,
             actualizado_en = CURRENT_TIMESTAMP
         WHERE id_cliente = :id_cliente"
    );
    $statement->execute([
        'password_hash' => password_hash($nueva, PASSWORD_DEFAULT),
        'id_cliente' => (int) $cliente['id_cliente'],
    ]);

    session_regenerate_id(true);

    $_SESSION['cliente_seguridad_estado'] = [
        'tipo' => 'ok',
        'mensaje' => 'Tu contraseña fue actualizada correctamente.',
    ];
} catch (InvalidArgumentException $exception) {
    $_SESSION['cliente_seguridad_estado'] = [
        'tipo' => 'error',
        'mensaje' => $exception->getMessage(),
    ];
} catch (Throwable $exception) {
    error_log('Customer public password update error: ' . $exception->getMessage());
    $_SESSION['cliente_seguridad_estado'] = [
        'tipo' => 'error',
        'mensaje' => 'No pudimos actualizar tu contraseña en este momento.',
    ];
}

header('Location: ' . appUrl('public/clientes/seguridad.php'), true, 303);
exit;
