<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

/**
 * Devuelve una única conexión PDO reutilizable durante la petición.
 */
function database(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
    $values = [];

    foreach ($required as $variable) {
        $value = env($variable);
        if ($value === null || $value === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }
        $values[$variable] = $value;
    }

    $sslMode = env('DB_SSLMODE', 'require');
    if ($sslMode !== 'require') {
        throw new RuntimeException('SSL is required for the database connection.');
    }

    $endpointId = env('DB_ENDPOINT_ID');

    if ($endpointId === null || trim($endpointId) === '') {
        throw new RuntimeException('Database endpoint ID is missing.');
    }

    $databaseName = sprintf(
        '%s options=endpoint=%s',
        $values['DB_NAME'],
        trim($endpointId)
    );

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        $values['DB_HOST'],
        $values['DB_PORT'],
        $databaseName
    );

    $connection = new PDO(
        $dsn,
        $values['DB_USER'],
        $values['DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $connection;
}
