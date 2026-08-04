<?php

declare(strict_types=1);

/**
 * Carga un archivo .env sencillo sin sobrescribir variables del entorno.
 */
function loadEnvironment(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);

        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        $value = trim($value);

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (
                ($first === '"' && $last === '"')
                || ($first === "'" && $last === "'")
            ) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

/**
 * Obtiene una variable de entorno o un valor predeterminado.
 */
function env(string $name, ?string $default = null): ?string
{
    $value = getenv($name);

    return $value === false ? $default : $value;
}

/**
 * Obtiene la URL base de la aplicación.
 *
 * Prioridad:
 * 1. APP_URL definido en .env
 * 2. Headers enviados por el reverse proxy
 * 3. Host recibido directamente
 * 4. localhost como último fallback
 */
function appBaseUrl(): string
{
    $configuredUrl = env('APP_URL');

    if ($configuredUrl !== null && trim($configuredUrl) !== '') {
        return rtrim($configuredUrl, '/');
    }

    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
    $forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? null;

    $scheme = $forwardedProto !== null
        ? trim(explode(',', $forwardedProto)[0])
        : null;

    if ($scheme !== 'http' && $scheme !== 'https') {
        $isHttps = isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';

        $scheme = $isHttps ? 'https' : 'http';
    }

    $host = $forwardedHost !== null
        ? trim(explode(',', $forwardedHost)[0])
        : ($_SERVER['HTTP_HOST'] ?? 'localhost:8000');

    // Evita caracteres inválidos o inyección en headers.
    if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
        $host = 'localhost:8000';
    }

    return $scheme . '://' . $host;
}

/**
 * Construye una URL absoluta.
 */
function appUrl(string $path = ''): string
{
    $baseUrl = appBaseUrl();

    if ($path === '') {
        return $baseUrl;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

loadEnvironment(dirname(__DIR__) . '/.env');

$timezone = env('APP_TIMEZONE', 'America/Santiago');

if ($timezone !== null) {
    date_default_timezone_set($timezone);
}

return [
    'environment' => env('APP_ENV', 'production'),
    'debug' => filter_var(
        env('APP_DEBUG', 'false'),
        FILTER_VALIDATE_BOOL
    ),
    'timezone' => $timezone,
    'url' => appBaseUrl(),
];