<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionConfig = require dirname(__DIR__) . '/config/session.php';

    ini_set('session.use_strict_mode', $sessionConfig['use_strict_mode'] ? '1' : '0');
    ini_set('session.use_only_cookies', '1');
    session_name((string) $sessionConfig['name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (bool) $sessionConfig['cookie_secure'],
        'httponly' => (bool) $sessionConfig['cookie_httponly'],
        'samesite' => (string) $sessionConfig['cookie_samesite'],
    ]);

    session_start();
}
