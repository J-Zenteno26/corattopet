<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$loginUrl = appUrl('admin/auth/login.php?error=1');

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $loginUrl, true, 303);
    exit;
}

$now = time();
$windowSeconds = 300;
$maximumAttempts = 5;
$attempts = $_SESSION['login_attempts'] ?? [];
$attempts = is_array($attempts)
    ? array_values(array_filter($attempts, static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - $windowSeconds))
    : [];

if (count($attempts) >= $maximumAttempts) {
    $_SESSION['login_attempts'] = $attempts;
    header('Location: ' . $loginUrl, true, 303);
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');

try {
    $statement = database()->prepare(
        'SELECT id_usuario, nombre, email, password_hash, rol, activo FROM usuarios WHERE LOWER(email) = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    $passwordHash = is_array($user)
        ? (string) ($user['password_hash'] ?? '')
        : password_hash('', PASSWORD_DEFAULT);
    $passwordMatches = password_verify($password, $passwordHash);
    $active = is_array($user)
        && in_array($user['activo'] ?? false, [true, 1, '1', 't', 'true'], true);
    $validUser = is_array($user) && $active && $passwordMatches;

    if (!$validUser) {
        $_SESSION['login_attempts'] = [...$attempts, $now];
        header('Location: ' . $loginUrl, true, 303);
        exit;
    }

    database()->prepare('UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id_usuario = :id_usuario')
        ->execute(['id_usuario' => $user['id_usuario']]);

    session_regenerate_id(true);
    $_SESSION = [
        'id_usuario' => (int) $user['id_usuario'],
        'nombre' => (string) $user['nombre'],
        'email' => (string) $user['email'],
        'rol' => (string) $user['rol'],
    ];

    header('Location: ' . appUrl('admin/dashboard/index.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    error_log('Authentication error: ' . $exception->getMessage());
    header('Location: ' . $loginUrl, true, 303);
    exit;
}
