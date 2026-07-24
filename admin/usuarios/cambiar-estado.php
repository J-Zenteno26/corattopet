<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/includes/funciones-usuarios.php';
require_once __DIR__ . '/includes/consultas-usuarios.php';

requerirAdministradorUsuarios();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) { guardarModalAdmin('error', 'No fue posible actualizar el usuario', 'La solicitud no es válida. Recarga la página.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
$id = idUsuarioValido($_POST['id_usuario'] ?? null);
if ($id === null) { guardarModalAdmin('error', 'No fue posible actualizar el usuario', 'El usuario indicado no es válido.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
try {
    $connection = database(); $connection->beginTransaction();
    $statement = $connection->prepare('SELECT id_usuario, activo FROM usuarios WHERE id_usuario = :id_usuario FOR UPDATE'); $statement->bindValue(':id_usuario', $id, PDO::PARAM_INT); $statement->execute(); $user = $statement->fetch();
    if (!is_array($user)) { $connection->rollBack(); guardarModalAdmin('error', 'No fue posible actualizar el usuario', 'El usuario indicado no existe.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
    $newStatus = !usuarioBooleano($user['activo']);
    if (!$newStatus && $id === usuarioActualId()) { $connection->rollBack(); guardarModalAdmin('error', 'Acción bloqueada', 'No puedes desactivar tu propio usuario.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
    if (!$newStatus && contarUsuariosActivos($connection) <= 1) { $connection->rollBack(); guardarModalAdmin('error', 'Acción bloqueada', 'Debe permanecer al menos un usuario activo.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit; }
    $update = $connection->prepare('UPDATE usuarios SET activo = :activo, actualizado_en = CURRENT_TIMESTAMP WHERE id_usuario = :id_usuario'); $update->bindValue(':activo', $newStatus, PDO::PARAM_BOOL); $update->bindValue(':id_usuario', $id, PDO::PARAM_INT); $update->execute(); $connection->commit();
    guardarModalAdmin('success', 'Estado actualizado', 'El usuario fue actualizado correctamente.'); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit;
} catch (Throwable $exception) {
    if (isset($connection) && $connection instanceof PDO && $connection->inTransaction()) { $connection->rollBack(); }
    $reference = referenciaErrorUsuario('User status update error', $exception); guardarModalAdmin('error', 'No fue posible actualizar el usuario', 'No se pudo completar la acción.', ['reference' => $reference]); header('Location: ' . appUrl('admin/usuarios/index.php'), true, 303); exit;
}

