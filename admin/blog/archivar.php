<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-blog.php';
require_once __DIR__ . '/includes/consultas-blog.php';

requireRoles(['administrador', 'Blog']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$listUrl = appUrl('admin/blog/index.php');
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarModalAdmin('error', 'No fue posible archivar el artículo', 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $listUrl, true, 303);
    exit;
}

$articleId = normalizarIdBlog($_POST['id'] ?? null);
if ($articleId === null) {
    guardarModalAdmin('error', 'No fue posible archivar el artículo', 'El artículo indicado no es válido.');
    header('Location: ' . $listUrl, true, 303);
    exit;
}

try {
    $connection = database();
    $state = obtenerEstadoArticuloBlog($connection, $articleId);
    if ($state === null) {
        guardarModalAdmin('error', 'No fue posible archivar el artículo', 'El artículo indicado no existe.');
    } elseif ($state === 'archivado') {
        guardarModalAdmin('info', 'Artículo ya archivado', 'El artículo ya se encontraba archivado.');
    } elseif (archivarArticuloBlog($connection, $articleId)) {
        guardarModalAdmin('success', 'Artículo archivado', 'El artículo fue archivado correctamente.');
    } else {
        guardarModalAdmin('warning', 'No fue posible archivar el artículo', 'El estado del artículo cambió antes de completar la acción.');
    }
} catch (Throwable $exception) {
    $reference = strtoupper(bin2hex(random_bytes(4)));
    error_log(sprintf('[%s] Blog article archive error: %s', $reference, $exception->getMessage()));
    guardarModalAdmin('error', 'No fue posible archivar el artículo', 'Intenta nuevamente.', ['reference' => $reference]);
}

header('Location: ' . $listUrl, true, 303);
exit;
