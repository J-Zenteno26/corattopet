<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);

    return $value === false ? '' : trim($value);
}

$name = prompt('Nombre: ');
$email = strtolower(prompt('Email: '));
$password = prompt('Contraseña: ');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Datos inválidos. Usa un email válido y una contraseña de al menos 12 caracteres.\n");
    exit(1);
}

try {
    $statement = database()->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES (:nombre, :email, :password_hash, :rol, TRUE)'
    );
    $statement->execute([
        'nombre' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'rol' => 'administrador',
    ]);

    fwrite(STDOUT, "Usuario administrador creado correctamente.\n");
    exit(0);
} catch (Throwable $exception) {
    error_log('Administrator seed error: ' . $exception->getMessage());
    fwrite(STDERR, "No fue posible crear el usuario administrador.\n");
    exit(1);
}
