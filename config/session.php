<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

return [
    'name' => env('SESSION_NAME', 'coratto_pet_session'),
    'cookie_secure' => filter_var(env('SESSION_SECURE_COOKIE', 'true'), FILTER_VALIDATE_BOOL),
    'cookie_httponly' => true,
    'cookie_samesite' => env('SESSION_SAME_SITE', 'Lax'),
    'use_strict_mode' => true,
];
