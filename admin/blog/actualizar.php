<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-blog.php';
require_once __DIR__ . '/includes/validaciones-blog.php';
require_once __DIR__ . '/includes/consultas-blog.php';

requireRoles(['administrador', 'Blog']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$articleId = normalizarIdBlog($_POST['id'] ?? null);
if ($articleId === null) {
    http_response_code(404);
    exit;
}

[$values, $errors] = validarArticuloBlog($_POST);
$stateKey = 'blog_editar_' . $articleId;
$editUrl = appUrl('admin/blog/editar.php?id=' . $articleId);

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMantenedor($stateKey, $values, [], 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $editUrl, true, 303);
    exit;
}

$validatedCover = null;
try {
    $validatedCover = validarPortadaBlog(
        is_array($_FILES['imagen_portada'] ?? null) ? $_FILES['imagen_portada'] : []
    );
} catch (PortadaBlogException $exception) {
    $errors['imagen_portada'] = $exception->getMessage();
}

if ($errors !== []) {
    guardarEstadoMantenedor($stateKey, $values, $errors);
    header('Location: ' . $editUrl, true, 303);
    exit;
}

$connection = null;
$newCover = null;
$previousCover = null;
try {
    $connection = database();
    $article = obtenerArticuloEdicionBlog($connection, $articleId);
    if ($article === null) {
        http_response_code(404);
        exit;
    }

    if (!categoriaDisponibleEdicionBlog(
        $connection,
        (int) $values['id_categoria_blog'],
        (int) $article['id_categoria_blog']
    )) {
        guardarEstadoMantenedor($stateKey, $values, [
            'id_categoria_blog' => 'La categoría seleccionada no existe o está inactiva.',
        ]);
        header('Location: ' . $editUrl, true, 303);
        exit;
    }

    $requestedSlug = $values['slug'];
    $slug = $requestedSlug === (string) $article['slug']
        ? (string) $article['slug']
        : normalizarSlugBlog($requestedSlug !== '' ? $requestedSlug : $values['titulo']);

    if (slugBlogExiste($connection, $slug, $articleId)) {
        $values['slug'] = $slug;
        guardarEstadoMantenedor($stateKey, $values, [
            'slug' => 'El slug ya está siendo utilizado por otro artículo.',
        ]);
        header('Location: ' . $editUrl, true, 303);
        exit;
    }

    $previousCover = is_string($article['imagen_portada'] ?? null) ? $article['imagen_portada'] : null;
    $connection->beginTransaction();
    if (is_array($validatedCover)) {
        $newCover = guardarPortadaBlogValidada($articleId, $validatedCover);
    }
    actualizarArticuloBlog($connection, $articleId, $values, $slug);
    if (is_array($newCover)) {
        actualizarPortadaArticuloBlog($connection, $articleId, $newCover['relativa']);
    }
    $connection->commit();
    if (is_array($newCover)) {
        eliminarPortadaBlog($previousCover);
    }
    guardarModalAdmin('success', 'Artículo actualizado', 'Los cambios del artículo fueron guardados correctamente.');
    header('Location: ' . appUrl('admin/blog/index.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    if (is_array($newCover) && is_string($newCover['absoluta'] ?? null) && is_file($newCover['absoluta'])) {
        @unlink($newCover['absoluta']);
    }
    $reference = registrarExcepcionAdmin('Blog article update error', $exception);
    guardarEstadoMantenedor(
        $stateKey,
        $values,
        [],
        'No fue posible actualizar el artículo. Intenta nuevamente.',
        $reference
    );
    header('Location: ' . $editUrl, true, 303);
    exit;
}
