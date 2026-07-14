<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!isAuthenticated() || !validateCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}

destroySession();
setcookie(CSRF_COOKIE_NAME, '', [
    'expires' => time() - 42000,
    'path' => '/',
    'secure' => (bool) (require dirname(__DIR__, 2) . '/config/session.php')['cookie_secure'],
    'httponly' => true,
    'samesite' => (string) (require dirname(__DIR__, 2) . '/config/session.php')['cookie_samesite'],
]);

header('Location: ' . appUrl('admin/auth/login.php'), true, 303);
exit;
