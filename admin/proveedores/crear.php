<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-proveedores.php';
requireAuthentication();
$state = consumirEstadoMantenedor('proveedor_crear');
$values = array_merge(valoresProveedorIniciales(), is_array($state['valores'] ?? null) ? $state['valores'] : []);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$csrfToken = csrfToken();
$pageTitle = 'Nuevo proveedor';
$activeSection = 'proveedores';
$formAction = appUrl('admin/proveedores/guardar.php');
$submitLabel = 'Guardar proveedor';
require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php'; ?>
<main class="admin-main">
    <header class="admin-provider-hero">
        <div><a class="admin-back-link" href="<?= escape(appUrl('admin/proveedores/index.php')) ?>">← Volver a
                proveedores</a><span class="admin-provider-hero__eyebrow">Directorio comercial</span>
            <h1><i class="bi bi-building-add" aria-hidden="true"></i> Nuevo proveedor</h1>
            <p>Centraliza sus datos de contacto, condiciones de compra y coordinación comercial.</p>
        </div>
        <span class="admin-provider-hero__mark"><i class="bi bi-truck" aria-hidden="true"></i></span>
    </header><?php require __DIR__ . '/includes/formulario-proveedor.php'; ?>
</main><?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>