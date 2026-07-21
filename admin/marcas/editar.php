<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once __DIR__ . '/includes/funciones-marca.php';
requireAuthentication();
$id = idPositivoMarca($_GET['id'] ?? null);
header('Location: ' . appUrl('admin/marcas/index.php' . ($id !== null ? '?editar=' . $id : '')), true, 302);
exit;
