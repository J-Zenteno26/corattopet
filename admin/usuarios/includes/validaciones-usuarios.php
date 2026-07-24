<?php

declare(strict_types=1);

/** @return array{0: array<string, mixed>, 1: array<string, string>} */
function validarDatosUsuario(array $input, bool $includePassword): array
{
    $values = [
        'nombre' => trim((string) ($input['nombre'] ?? '')),
        'email' => strtolower(trim((string) ($input['email'] ?? ''))),
        'rol' => trim((string) ($input['rol'] ?? '')),
        'activo' => isset($input['activo']) && usuarioBooleano($input['activo']),
    ];
    $errors = [];

    if ($values['nombre'] === '') {
        $errors['nombre'] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($values['nombre']) > 120) {
        $errors['nombre'] = 'El nombre no puede superar 120 caracteres.';
    }
    if ($values['email'] === '') {
        $errors['email'] = 'El email es obligatorio.';
    } elseif (mb_strlen($values['email']) > 160 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Ingresa un email válido de hasta 160 caracteres.';
    }
    if (!array_key_exists($values['rol'], rolesUsuarios())) {
        $errors['rol'] = 'Selecciona un rol permitido.';
    }

    if ($includePassword) {
        $values['password'] = (string) ($input['password'] ?? '');
        $values['password_confirmacion'] = (string) ($input['password_confirmacion'] ?? '');
        if (strlen($values['password']) < 8) {
            $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($values['password'] !== $values['password_confirmacion']) {
            $errors['password_confirmacion'] = 'Las contraseñas no coinciden.';
        }
    }

    return [$values, $errors];
}

/** @return array{0: array<string, string>, 1: array<string, string>} */
function validarPasswordUsuario(array $input): array
{
    $values = ['password' => (string) ($input['password'] ?? ''), 'password_confirmacion' => (string) ($input['password_confirmacion'] ?? '')];
    $errors = [];
    if (strlen($values['password']) < 8) {
        $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($values['password'] !== $values['password_confirmacion']) {
        $errors['password_confirmacion'] = 'Las contraseñas no coinciden.';
    }
    return [$values, $errors];
}

