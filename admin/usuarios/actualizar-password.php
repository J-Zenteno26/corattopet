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
$id = idUsuarioValido($_POST['id_usuario'] ?? null);
if ($id === null) { guardarModalAdmin('error', 'No fue posible actualizar la contraseña', 'El usuario indicado no es válido.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
$url = appUrl('admin/usuarios/cambiar-password.php?id_usuario=' . $id);
[$values, $errors] = validarPasswordUsuario($_POST);
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) { guardarEstadoUsuario('password_' . $id, [], [], 'La solicitud no es válida. Recarga la página.'); header('Location: ' . $url, true, 303); exit; }
if ($errors !== []) { guardarEstadoUsuario('password_' . $id, [], $errors); header('Location: ' . $url, true, 303); exit; }
try {
    $connection = database();
    if (obtenerUsuarioPorId($connection, $id) === null) { guardarModalAdmin('error', 'No fue posible actualizar la contraseña', 'El usuario indicado no existe.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
    $statement = $connection->prepare('UPDATE usuarios SET password_hash = :password_hash, actualizado_en = CURRENT_TIMESTAMP WHERE id_usuario = :id_usuario');
    $statement->bindValue(':password_hash', password_hash($values['password'], PASSWORD_DEFAULT)); $statement->bindValue(':id_usuario', $id, PDO::PARAM_INT); $statement->execute();
    guardarModalAdmin('success', 'Contraseña actualizada', 'La contraseña fue modificada correctamente.');
    header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit;
} catch (Throwable $exception) {
    $reference = referenciaErrorUsuario('User password update error', $exception); guardarEstadoUsuario('password_' . $id, [], [], 'No se pudo completar la acción.', $reference); header('Location: ' . $url, true, 303); exit;
}

