<?php

declare(strict_types=1);

require_once __DIR__ . '/inicializar-sesion.php';

const CSRF_COOKIE_NAME = 'coratto_pet_csrf';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isAuthenticated(): bool
{
    return isset($_SESSION['id_usuario'], $_SESSION['nombre'], $_SESSION['email'], $_SESSION['rol'])
        && is_numeric($_SESSION['id_usuario'])
        && is_string($_SESSION['nombre'])
        && is_string($_SESSION['email'])
        && is_string($_SESSION['rol']);
}

function requireAuthentication(): void
{
    if (!isAuthenticated()) {
        header('Location: ' . appUrl('admin/auth/login.php'), true, 302);
        exit;
    }
}

function csrfToken(): string
{
    $token = $_COOKIE[CSRF_COOKIE_NAME] ?? '';

    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $sessionConfig = require dirname(__DIR__) . '/config/session.php';

        setcookie(CSRF_COOKIE_NAME, $token, [
            'expires' => 0,
            'path' => '/',
            'secure' => (bool) $sessionConfig['cookie_secure'],
            'httponly' => true,
            'samesite' => (string) $sessionConfig['cookie_samesite'],
        ]);
        $_COOKIE[CSRF_COOKIE_NAME] = $token;
    }

    return $token;
}

function validateCsrfToken(mixed $submittedToken): bool
{
    $cookieToken = $_COOKIE[CSRF_COOKIE_NAME] ?? null;

    return is_string($submittedToken)
        && is_string($cookieToken)
        && preg_match('/^[a-f0-9]{64}$/', $cookieToken) === 1
        && hash_equals($cookieToken, $submittedToken);
}

function destroySession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parameters['path'],
            'domain' => $parameters['domain'],
            'secure' => $parameters['secure'],
            'httponly' => $parameters['httponly'],
            'samesite' => $parameters['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}
