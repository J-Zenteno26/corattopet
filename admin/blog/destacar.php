<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
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
    guardarModalAdmin('error', 'No fue posible cambiar el destacado', 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $listUrl, true, 303);
    exit;
}

$articleId = normalizarIdBlog($_POST['id'] ?? null);
if ($articleId === null) {
    guardarModalAdmin('error', 'No fue posible cambiar el destacado', 'El artículo indicado no es válido.');
    header('Location: ' . $listUrl, true, 303);
    exit;
}

$connection = null;
try {
    $connection = database();
    $connection->beginTransaction();
    bloquearDestacadosBlog($connection);
    $article = obtenerArticuloDestacadoBlog($connection, $articleId);
    if ($article === null) {
        $connection->rollBack();
        guardarModalAdmin('error', 'No fue posible cambiar el destacado', 'El artículo indicado no existe.');
    } elseif ((string) $article['estado'] !== 'publicado') {
        $connection->rollBack();
        guardarModalAdmin('warning', 'No fue posible destacar el artículo', 'Solo los artículos publicados pueden estar destacados.');
    } elseif (booleanoPostgresMantenedor($article['destacado'])) {
        quitarDestacadoBlog($connection);
        $connection->commit();
        guardarModalAdmin('success', 'Destacado retirado', 'El artículo dejó de estar destacado.');
    } else {
        destacarArticuloBlog($connection, $articleId);
        $connection->commit();
        guardarModalAdmin('success', 'Artículo destacado', 'El artículo fue marcado como destacado.');
    }
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    $reference = registrarExcepcionAdmin('Blog featured article update error', $exception);
    guardarModalAdmin('error', 'No fue posible cambiar el destacado', 'Intenta nuevamente.', ['reference' => $reference]);
}

header('Location: ' . $listUrl, true, 303);
exit;
