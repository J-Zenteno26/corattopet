<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/includes/funciones-usuarios.php';
require_once __DIR__ . '/includes/validaciones-usuarios.php';
require_once __DIR__ . '/includes/consultas-usuarios.php';

requerirAdministradorUsuarios();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
$url = appUrl('admin/usuarios/crear.php');
[$values, $errors] = validarDatosUsuario($_POST, true);
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoUsuario('crear', $values, [], 'La solicitud no es válida. Recarga la página.');
    header('Location: ' . $url, true, 303); exit;
}
if ($errors !== []) {
    guardarEstadoUsuario('crear', $values, $errors);
    header('Location: ' . $url, true, 303); exit;
}
try {
    $connection = database();
    if (existeEmailUsuario($connection, (string) $values['email'])) {
        guardarEstadoUsuario('crear', $values, ['email' => 'Ya existe un usuario con este email.']);
        header('Location: ' . $url, true, 303); exit;
    }
    $statement = $connection->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol, activo, actualizado_en) VALUES (:nombre, :email, :password_hash, :rol, :activo, CURRENT_TIMESTAMP)');
    $statement->bindValue(':nombre', $values['nombre']);
    $statement->bindValue(':email', $values['email']);
    $statement->bindValue(':password_hash', password_hash((string) $values['password'], PASSWORD_DEFAULT));
    $statement->bindValue(':rol', $values['rol']);
    $statement->bindValue(':activo', $values['activo'], PDO::PARAM_BOOL);
    $statement->execute();
    guardarModalAdmin('success', 'Usuario creado', 'El usuario fue registrado correctamente.');
    header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit;
} catch (Throwable $exception) {
    $duplicate = $exception->getCode() === '23505';
    $reference = referenciaErrorUsuario('User creation error', $exception);
    guardarEstadoUsuario('crear', $values, $duplicate ? ['email' => 'Ya existe un usuario con este email.'] : [], $duplicate ? null : 'No se pudo completar la acción.', $duplicate ? null : $reference);
    header('Location: ' . $url, true, 303); exit;
}

