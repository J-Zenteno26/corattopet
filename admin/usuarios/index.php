<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/includes/funciones-usuarios.php';
require_once __DIR__ . '/includes/consultas-usuarios.php';

requerirAdministradorUsuarios();
$search = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['estado'] ?? '', ['activo', 'inactivo'], true) ? (string) $_GET['estado'] : '';
$role = array_key_exists((string) ($_GET['rol'] ?? ''), rolesUsuarios()) ? (string) $_GET['rol'] : '';
$users = [];
$summary = ['total' => 0, 'activos' => 0, 'inactivos' => 0, 'administradores' => 0];
$loadError = false;
try {
    $connection = database();
    $users = listarUsuarios($connection, $search, $status, $role);
    $summary = resumenUsuarios($connection);
} catch (Throwable $exception) {
    $loadError = true;
    $reference = referenciaErrorUsuario('Users list error', $exception);
    $adminModal = ['type' => 'error', 'title' => 'No fue posible cargar los usuarios', 'message' => 'No se pudo completar la acción.', 'reference' => $reference];
}
$csrfToken = csrfToken();
$pageTitle = 'Usuarios';
$activeSection = 'usuarios';
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<main class="admin-main admin-users" id="contenido-principal">
    <header class="admin-page-header admin-users-hero">
        <div><span class="admin-users-eyebrow"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Gestión
                segura</span>
            <h1 class="admin-page-title">Usuarios internos</h1>
            <p>Administra los accesos del equipo al panel de Coratto Pet.</p>
        </div><a class="admin-button admin-button--primary admin-users-primary"
            href="<?= escape(appUrl('admin/usuarios/crear.php')) ?>"><i class="bi bi-person-plus-fill"
                aria-hidden="true"></i><span>Crear usuario</span></a>
    </header>
    <section class="admin-users-metrics" aria-label="Resumen de usuarios">
        <?php foreach ([['total', 'Registrados', 'bi-people-fill'], ['activos', 'Activos', 'bi-check-circle-fill'], ['inactivos', 'Inactivos', 'bi-x-circle-fill'], ['administradores', 'Administradores', 'bi-shield-lock-fill']] as [$key, $label, $icon]): ?>
            <article><i class="bi <?= escape($icon) ?>" aria-hidden="true"></i>
                <div><span><?= escape($label) ?></span><strong><?= escape((string) $summary[$key]) ?></strong></div>
            </article><?php endforeach; ?>
    </section>
    <section class="admin-panel admin-users-panel">
        <div class="admin-users-panel__header">
            <div><span>Directorio interno</span>
                <h2>Usuarios del administrador</h2>
            </div>
            <p>Controla identidad, rol y disponibilidad de cada acceso.</p>
        </div>
        <form class="admin-users-filters" method="get" action="<?= escape(appUrl('admin/usuarios/index.php')) ?>">
            <label><span>Buscar</span><input type="search" name="q" maxlength="160" value="<?= escape($search) ?>"
                    placeholder="Nombre o email"></label>
            <label><span>Estado</span><select name="estado">
                    <option value="">Todos</option>
                    <option value="activo" <?= $status === 'activo' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $status === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                </select></label>
            <label><span>Rol</span><select name="rol">
                    <option value="">Todos</option><?php foreach (rolesUsuarios() as $value => $label): ?>
                        <option value="<?= escape($value) ?>" <?= $role === $value ? 'selected' : '' ?>><?= escape($label) ?>
                        </option><?php endforeach; ?>
                </select></label>
            <button class="admin-button admin-button--primary" type="submit"><i class="bi bi-search"
                    aria-hidden="true"></i>
                Filtrar</button><?php if ($search !== '' || $status !== '' || $role !== ''): ?><a class="admin-button"
                    href="<?= escape(appUrl('admin/usuarios/index.php')) ?>">Limpiar</a><?php endif; ?>
        </form>
        <div class="admin-table-wrap">
            <table class="admin-table admin-users-table">
                <thead>
                    <tr>
                        <th>USUARIO</th>
                        <th>ROL</th>
                        <th>ESTADO</th>
                        <th>CREADO</th>
                        <th>ACTUALIZADO</th>
                        <th>ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $id = (int) $user['id_usuario'];
                        $active = usuarioBooleano($user['activo']);
                        $isSelf = $id === usuarioActualId(); ?>
                        <tr>
                            <td data-label="Usuario">
                                <div class="admin-user-identity">
                                    <span><?= escape(mb_strtoupper(mb_substr((string) $user['nombre'], 0, 1))) ?></span>
                                    <div>
                                        <strong><?= escape((string) $user['nombre']) ?><?= $isSelf ? ' (tú)' : '' ?></strong><small><?= escape((string) $user['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Rol"><span class="admin-user-role"><i
                                        class="bi <?= $user['rol'] === 'administrador' ? 'bi-shield-lock-fill' : 'bi-person-badge-fill' ?>"
                                        aria-hidden="true"></i><?= escape(rolesUsuarios()[(string) $user['rol']] ?? ucfirst((string) $user['rol'])) ?></span>
                            </td>
                            <td data-label="Estado"><span
                                    class="admin-status-badge <?= $active ? 'is-active' : 'is-inactive' ?>"><?= $active ? 'Activo' : 'Inactivo' ?></span>
                            </td>
                            <td data-label="Creado"><?= escape(formatearFechaUsuario($user['creado_en'])) ?></td>
                            <td data-label="Actualizado"><?= escape(formatearFechaUsuario($user['actualizado_en'])) ?></td>
                            <td data-label="Acciones">
                                <div class="admin-user-actions"><a class="admin-user-action"
                                        href="<?= escape(appUrl('admin/usuarios/editar.php?id_usuario=' . $id)) ?>"
                                        title="Editar"><i class="bi bi-pencil-square"
                                            aria-hidden="true"></i><span>Editar</span></a><a class="admin-user-action"
                                        href="<?= escape(appUrl('admin/usuarios/cambiar-password.php?id_usuario=' . $id)) ?>"
                                        title="Cambiar contraseña"><i class="bi bi-key-fill"
                                            aria-hidden="true"></i><span>Contraseña</span></a>
                                    <form id="estado-usuario-<?= $id ?>" method="post"
                                        action="<?= escape(appUrl('admin/usuarios/cambiar-estado.php')) ?>"><input
                                            type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>"><input
                                            type="hidden" name="id_usuario" value="<?= $id ?>"><button
                                            class="admin-user-action <?= $active ? 'is-danger' : 'is-success' ?>"
                                            type="button" <?= $isSelf && $active ? 'disabled title="No puedes desactivar tu propio usuario"' : '' ?> data-admin-confirm-form="estado-usuario-<?= $id ?>"
                                            data-modal-title="<?= $active ? 'Desactivar usuario' : 'Activar usuario' ?>"
                                            data-modal-message="<?= $active ? 'El usuario perderá acceso al administrador hasta que vuelva a activarse.' : 'El usuario podrá ingresar nuevamente al administrador.' ?>"
                                            data-modal-primary="<?= $active ? 'Desactivar' : 'Activar' ?>"
                                            data-modal-destructive="<?= $active ? 'true' : 'false' ?>"
                                            data-modal-secondary="Cancelar"><i
                                                class="bi <?= $active ? 'bi-x-circle-fill' : 'bi-check-circle-fill' ?>"
                                                aria-hidden="true"></i><span><?= $active ? 'Desactivar' : 'Activar' ?></span></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($users === [] && !$loadError): ?>
                        <tr class="admin-empty-state">
                            <td colspan="6"><i class="bi bi-people-fill"
                                    aria-hidden="true"></i><strong><?= $search !== '' || $status !== '' || $role !== '' ? 'No encontramos usuarios' : 'Aún no hay usuarios adicionales' ?></strong><span><?= $search !== '' || $status !== '' || $role !== '' ? 'Prueba con otros criterios de búsqueda.' : 'Crea un usuario para incorporar a otra persona del equipo.' ?></span>
                            </td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>