<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-blog.php';
require_once __DIR__ . '/includes/consultas-blog.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderVistaPreviaBlog(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

if (!isAuthenticated() || !in_array((string) ($_SESSION['rol'] ?? ''), ['administrador', 'Blog'], true)) {
    responderVistaPreviaBlog(403, ['error' => 'Acceso denegado.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responderVistaPreviaBlog(405, ['error' => 'Método no permitido.']);
}

$articleId = normalizarIdBlog($_GET['id'] ?? null);
if ($articleId === null) {
    responderVistaPreviaBlog(404, ['error' => 'El artículo no existe.']);
}

try {
    $article = obtenerArticuloVistaPreviaBlog(database(), $articleId);
} catch (Throwable $exception) {
    registrarExcepcionAdmin('Blog article preview endpoint error', $exception);
    responderVistaPreviaBlog(500, ['error' => 'No fue posible cargar la vista previa.']);
}

if ($article === null) {
    responderVistaPreviaBlog(404, ['error' => 'El artículo no existe.']);
}

responderVistaPreviaBlog(200, [
    'estado' => (string) $article['estado'],
    'estado_etiqueta' => etiquetaEstadoBlog((string) $article['estado']),
    'categoria' => (string) $article['categoria'],
    'titulo' => (string) $article['titulo'],
    'extracto' => (string) $article['extracto'],
    'autor_publico' => (string) $article['autor_publico'],
    'responsable' => (string) $article['autor'],
    'contenido' => sanitizarContenidoBlog($article['contenido_html'] ?? ''),
    'portada_url' => urlPortadaBlog($article['imagen_portada'] ?? null),
    'video_url' => urlVideoBlog($article['video_url'] ?? null),
]);
