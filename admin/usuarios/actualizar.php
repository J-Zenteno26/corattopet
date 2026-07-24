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
if ($id === null) { guardarModalAdmin('error', 'No fue posible actualizar el usuario', 'El usuario indicado no es válido.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
$url = appUrl('admin/usuarios/editar.php?id_usuario=' . $id);
[$values, $errors] = validarDatosUsuario($_POST, false);
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) { guardarEstadoUsuario('editar_' . $id, $values, [], 'La solicitud no es válida. Recarga la página.'); header('Location: ' . $url, true, 303); exit; }
if ($errors !== []) { guardarEstadoUsuario('editar_' . $id, $values, $errors); header('Location: ' . $url, true, 303); exit; }
try {
    $connection = database();
    $connection->beginTransaction();
    $statement = $connection->prepare('SELECT id_usuario, rol, activo FROM usuarios WHERE id_usuario = :id_usuario FOR UPDATE');
    $statement->bindValue(':id_usuario', $id, PDO::PARAM_INT); $statement->execute();
    $current = $statement->fetch();
    if (!is_array($current)) { $connection->rollBack(); guardarModalAdmin('error', 'No fue posible actualizar el usuario', 'El usuario indicado no existe.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
    if (existeEmailUsuario($connection, (string) $values['email'], $id)) { $connection->rollBack(); guardarEstadoUsuario('editar_' . $id, $values, ['email' => 'Ya existe otro usuario con este email.']); header('Location: ' . $url, true, 303); exit; }
    $isSelf = $id === usuarioActualId();
    if ($isSelf && !$values['activo']) { $connection->rollBack(); guardarEstadoUsuario('editar_' . $id, $values, ['activo' => 'No puedes desactivar tu propio usuario.']); header('Location: ' . $url, true, 303); exit; }
    if ($isSelf && $values['rol'] !== 'administrador') { $connection->rollBack(); guardarEstadoUsuario('editar_' . $id, $values, ['rol' => 'No puedes quitarte el rol administrador durante tu sesión.']); header('Location: ' . $url, true, 303); exit; }
    if (usuarioBooleano($current['activo']) && !$values['activo'] && contarUsuariosActivos($connection) <= 1) { $connection->rollBack(); guardarEstadoUsuario('editar_' . $id, $values, ['activo' => 'Debe permanecer al menos un usuario activo.']); header('Location: ' . $url, true, 303); exit; }
    $update = $connection->prepare('UPDATE usuarios SET nombre = :nombre, email = :email, rol = :rol, activo = :activo, actualizado_en = CURRENT_TIMESTAMP WHERE id_usuario = :id_usuario');
    $update->bindValue(':nombre', $values['nombre']); $update->bindValue(':email', $values['email']); $update->bindValue(':rol', $values['rol']); $update->bindValue(':activo', $values['activo'], PDO::PARAM_BOOL); $update->bindValue(':id_usuario', $id, PDO::PARAM_INT); $update->execute();
    $connection->commit();
    if ($isSelf) { $_SESSION['nombre'] = $values['nombre']; $_SESSION['email'] = $values['email']; }
    guardarModalAdmin('success', 'Usuario actualizado', 'Los datos del usuario fueron actualizados correctamente.');
    header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit;
} catch (Throwable $exception) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) { $connection->rollBack(); }
    $duplicate = $exception->getCode() === '23505'; $reference = referenciaErrorUsuario('User update error', $exception);
    guardarEstadoUsuario('editar_' . $id, $values, $duplicate ? ['email' => 'Ya existe otro usuario con este email.'] : [], $duplicate ? null : 'No se pudo completar la acción.', $duplicate ? null : $reference);
    header('Location: ' . $url, true, 303); exit;
}

