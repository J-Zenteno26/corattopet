<?php

declare(strict_types=1);

/** @return array<string, string> */
function rolesUsuarios(): array
{
    return ['administrador' => 'Administrador', 'operador' => 'Operador'];
}

function idUsuarioValido(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : (int) $id;
}

function usuarioBooleano(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 't', 'true', 'on'], true);
}

function usuarioActualId(): int
{
    return (int) ($_SESSION['id_usuario'] ?? 0);
}

function requerirAdministradorUsuarios(): void
{
    requireAuthentication();
    if (($_SESSION['rol'] ?? '') !== 'administrador') {
        http_response_code(403);
        guardarModalAdmin('error', 'Acceso restringido', 'Solo un administrador puede gestionar usuarios internos.');
        header('Location: ' . appUrl('admin/dashboard/index.php'), true, 303);
        exit;
    }
}

/** @return array<string, mixed> */
function valoresInicialesUsuario(): array
{
    return ['nombre' => '', 'email' => '', 'rol' => 'operador', 'activo' => true, 'password' => '', 'password_confirmacion' => ''];
}

function formatearFechaUsuario(mixed $value): string
{
    if (!is_string($value) || trim($value) === '') {
        return 'Sin registro';
    }
    try {
        return (new DateTimeImmutable($value))->format('d-m-Y H:i');
    } catch (Throwable) {
        return 'Sin registro';
    }
}

/** @param array<string, mixed> $values @param array<string, string> $errors */
function guardarEstadoUsuario(string $key, array $values, array $errors = [], ?string $generalError = null, ?string $reference = null): void
{
    unset($values['password'], $values['password_confirmacion']);
    $_SESSION['usuario_form_' . $key] = compact('values', 'errors', 'generalError', 'reference');
}

/** @return array<string, mixed> */
function consumirEstadoUsuario(string $key): array
{
    $sessionKey = 'usuario_form_' . $key;
    $state = $_SESSION[$sessionKey] ?? [];
    unset($_SESSION[$sessionKey]);
    return is_array($state) ? $state : [];
}

function referenciaErrorUsuario(string $context, Throwable $exception): string
{
    $reference = strtoupper(bin2hex(random_bytes(4)));
    error_log(sprintf('[%s] %s: %s in %s:%d', $reference, $context, $exception->getMessage(), $exception->getFile(), $exception->getLine()));
    return $reference;
}

