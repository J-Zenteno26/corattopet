<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-proveedores.php';
require_once __DIR__ . '/includes/consultas-proveedores.php';
requireAuthentication();
$providerId = idProveedorValido($_GET['id_proveedor'] ?? null);
if ($providerId === null) {
    guardarModalAdmin('error', 'Proveedor inválido', 'El proveedor indicado no es válido.');
    header('Location: ' . appUrl('admin/proveedores/index.php'));
    exit;
}
try {
    $provider = obtenerProveedor(database(), $providerId);
} catch (Throwable $e) {
    $reference = registrarExcepcionAdmin('Supplier edit error', $e);
    guardarModalAdmin('error', 'No fue posible abrir el proveedor', 'Intenta nuevamente.', ['reference' => $reference]);
    header('Location: ' . appUrl('admin/proveedores/index.php'));
    exit;
}
if ($provider === null) {
    guardarModalAdmin('error', 'Proveedor no encontrado', 'El proveedor indicado no existe.');
    header('Location: ' . appUrl('admin/proveedores/index.php'));
    exit;
}
$state = consumirEstadoMantenedor('proveedor_editar_' . $providerId);
$values = array_merge(valoresProveedorIniciales(), $provider, is_array($state['valores'] ?? null) ? $state['valores'] : []);
$values['activo'] = booleanoPostgresMantenedor($values['activo']);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$csrfToken = csrfToken();
$pageTitle = 'Editar proveedor';
$activeSection = 'proveedores';
$formAction = appUrl('admin/proveedores/actualizar.php');
$submitLabel = 'Actualizar proveedor';
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php'; ?>
<main class="admin-main">
    <header class="admin-provider-hero">
        <div><a class="admin-back-link" href="<?= escape(appUrl('admin/proveedores/ver.php?id_proveedor=' . $providerId)) ?>">← Volver a la ficha</a><span class="admin-provider-hero__eyebrow">Directorio comercial</span><h1><i class="bi bi-pencil-square" aria-hidden="true"></i> Editar proveedor</h1><p>Actualiza la información comercial y de contacto de <?= escape((string) $provider['nombre']) ?>.</p></div>
        <span class="admin-provider-hero__mark"><i class="bi bi-building-gear" aria-hidden="true"></i></span>
    </header><?php require __DIR__ . '/includes/formulario-proveedor.php'; ?>
</main><?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>
