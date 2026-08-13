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

[$values, $errors] = validarArticuloBlog($_POST);
$formUrl = appUrl('admin/blog/crear.php');

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoMantenedor('blog_crear', $values, [], 'La solicitud no es válida. Recarga la página e intenta nuevamente.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}

$validatedCover = null;
$validatedComplementary = null;
try {
    $validatedCover = validarPortadaBlog(
        is_array($_FILES['imagen_portada'] ?? null) ? $_FILES['imagen_portada'] : []
    );
} catch (PortadaBlogException $exception) {
    $errors['imagen_portada'] = $exception->getMessage();
}
try {
    $validatedComplementary = validarImagenComplementariaBlog(
        is_array($_FILES['imagen_complementaria'] ?? null) ? $_FILES['imagen_complementaria'] : []
    );
} catch (PortadaBlogException $exception) {
    $errors['imagen_complementaria'] = $exception->getMessage();
}

if ($errors !== []) {
    guardarEstadoMantenedor('blog_crear', $values, $errors);
    header('Location: ' . $formUrl, true, 303);
    exit;
}

$connection = null;
$newCover = null;
$newComplementary = null;
try {
    $connection = database();
    if (!categoriaActivaBlogExiste($connection, (int) $values['id_categoria_blog'])) {
        guardarEstadoMantenedor('blog_crear', $values, [
            'id_categoria_blog' => 'La categoría seleccionada no existe o está inactiva.',
        ]);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $slugSource = $values['slug'] !== '' ? $values['slug'] : $values['titulo'];
    $slug = generarSlugUnicoBlog($connection, $slugSource);
    $connection->beginTransaction();
    $articleId = insertarBorradorBlog($connection, $values, (int) $_SESSION['id_usuario'], $slug);
    if (is_array($validatedCover)) {
        $newCover = guardarPortadaBlogValidada($articleId, $validatedCover);
        actualizarPortadaArticuloBlog($connection, $articleId, $newCover['relativa']);
    }
    if (is_array($validatedComplementary)) {
        $newComplementary = guardarImagenComplementariaBlogValidada($articleId, $validatedComplementary);
        actualizarImagenComplementariaArticuloBlog($connection, $articleId, $newComplementary['relativa']);
    }
    $connection->commit();

    guardarModalAdmin('success', 'Borrador guardado', 'El artículo fue guardado correctamente como borrador.');
    header('Location: ' . appUrl('admin/blog/index.php'), true, 303);
    exit;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    if (is_array($newCover) && is_string($newCover['absoluta'] ?? null) && is_file($newCover['absoluta'])) {
        @unlink($newCover['absoluta']);
    }
    if (is_array($newComplementary) && is_string($newComplementary['absoluta'] ?? null) && is_file($newComplementary['absoluta'])) {
        @unlink($newComplementary['absoluta']);
    }
    $reference = registrarExcepcionAdmin('Blog draft creation error', $exception);
    guardarEstadoMantenedor(
        'blog_crear',
        $values,
        [],
        'No fue posible guardar el borrador. Intenta nuevamente.',
        $reference
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}
