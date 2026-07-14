<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=UTF-8');

try {
    database()->query('SELECT 1');
    echo 'Conexión exitosa.';
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Ocurrió un error de conexión.';
}
