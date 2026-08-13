<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/funciones-contenido-blog.php';

const PORTADA_BLOG_MAX_BYTES = 2097152;
const PORTADA_BLOG_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

final class PortadaBlogException extends RuntimeException
{
}

function valoresInicialesArticuloBlog(): array
{
    return [
        'titulo' => '',
        'slug' => '',
        'id_categoria_blog' => null,
        'extracto' => '',
        'video_url' => null,
        'contenido_html' => '',
        'autor_publico' => 'Equipo Coratto',
    ];
}

function normalizarTextoLineaBlog(mixed $value): string
{
    return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
}

function normalizarContenidoBlog(mixed $value): string
{
    return sanitizarContenidoBlog($value);
}

function normalizarSlugBlog(string $value): string
{
    $value = strtr(trim($value), [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $slug = strtolower($transliterated === false ? $value : $transliterated);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim(preg_replace('/-+/', '-', $slug) ?? '', '-');
    $slug = rtrim(mb_substr($slug, 0, 210), '-');

    return $slug !== '' ? $slug : 'articulo';
}

function construirSlugDisponibleBlog(string $base, array $existingSlugs): string
{
    $existing = array_fill_keys(array_map('strtolower', $existingSlugs), true);
    if (!isset($existing[$base])) {
        return $base;
    }

    for ($suffix = 2; ; $suffix++) {
        $ending = '-' . $suffix;
        $candidate = rtrim(mb_substr($base, 0, 210 - strlen($ending)), '-') . $ending;
        if (!isset($existing[$candidate])) {
            return $candidate;
        }
    }
}

function normalizarParametrosBlog(array $source): array
{
    $search = trim((string) ($source['buscar'] ?? ''));
    $allowedStates = ['borrador', 'publicado', 'archivado'];
    $state = (string) ($source['estado'] ?? '');

    return [
        'buscar' => mb_substr($search, 0, 120),
        'estado' => in_array($state, $allowedStates, true) ? $state : '',
        'id_categoria_blog' => normalizarIdBlog($source['id_categoria_blog'] ?? null),
        'pagina' => validarPaginaBlog($source['pagina'] ?? 1),
        'por_pagina' => validarCantidadPorPaginaBlog($source['por_pagina'] ?? 10),
    ];
}

function normalizarIdBlog(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function validarPaginaBlog(mixed $value): int
{
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $page === false ? 1 : $page;
}

function validarCantidadPorPaginaBlog(mixed $value): int
{
    $quantity = filter_var($value, FILTER_VALIDATE_INT);

    return in_array($quantity, [10, 20, 30], true) ? $quantity : 10;
}

function hayFiltrosBlogActivos(array $parameters): bool
{
    return $parameters['buscar'] !== ''
        || $parameters['estado'] !== ''
        || $parameters['id_categoria_blog'] !== null;
}

function construirUrlBlog(array $parameters, array $changes = []): string
{
    $query = array_merge($parameters, $changes);

    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || ($key === 'pagina' && $value === 1)) {
            unset($query[$key]);
        }
    }

    $queryString = http_build_query($query);

    return appUrl('admin/blog/index.php') . ($queryString === '' ? '' : '?' . $queryString);
}

function etiquetaEstadoBlog(string $state): string
{
    return match ($state) {
        'publicado' => 'Publicado',
        'archivado' => 'Archivado',
        default => 'Borrador',
    };
}

function claseEstadoBlog(string $state): string
{
    return match ($state) {
        'publicado' => 'is-active',
        'archivado' => 'is-archived',
        default => 'is-draft',
    };
}

function formatearFechaBlog(mixed $date): string
{
    if (!is_string($date) || $date === '') {
        return 'Sin publicar';
    }

    try {
        return (new DateTimeImmutable($date))->format('d-m-Y H:i');
    } catch (Throwable) {
        return 'Sin publicar';
    }
}

/** @return array{temporal:string,extension:string}|null */
function validarPortadaBlog(array $file): ?array
{
    return validarImagenBlog($file, 'La portada');
}

/** @return array{temporal:string,extension:string}|null */
function validarImagenComplementariaBlog(array $file): ?array
{
    return validarImagenBlog($file, 'La imagen complementaria');
}

/** @return array{temporal:string,extension:string}|null */
function validarImagenBlog(array $file, string $label): ?array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label . ' supera el tamaño máximo permitido de 2 MB.',
            default => $label . ' no pudo recibirse correctamente. Intenta nuevamente.',
        };
        throw new PortadaBlogException($message);
    }

    $temporary = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
    $original = basename(is_string($file['name'] ?? null) ? trim($file['name']) : '');
    $size = (int) ($file['size'] ?? 0);
    if ($temporary === '' || $original === '' || $size <= 0 || !is_file($temporary)) {
        throw new PortadaBlogException('El archivo seleccionado no es válido.');
    }
    if ($size > PORTADA_BLOG_MAX_BYTES) {
        throw new PortadaBlogException($label . ' supera el tamaño máximo permitido de 2 MB.');
    }
    if (preg_match('/\.(php\d*|phtml|phar|cgi|pl|py|sh|exe|com|bat|cmd)(\.|$)/i', $original) === 1) {
        throw new PortadaBlogException('El nombre del archivo contiene una extensión no permitida.');
    }

    $extension = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new PortadaBlogException('Usa una imagen JPG, PNG o WEBP.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    if (!is_string($mime) || !isset(PORTADA_BLOG_MIMES[$mime])) {
        throw new PortadaBlogException('El contenido del archivo no corresponde a una imagen JPG, PNG o WEBP.');
    }
    if (($extension === 'jpg' || $extension === 'jpeg') !== ($mime === 'image/jpeg')) {
        throw new PortadaBlogException('La extensión del archivo no coincide con su contenido.');
    }
    if (in_array($extension, ['png', 'webp'], true) && PORTADA_BLOG_MIMES[$mime] !== $extension) {
        throw new PortadaBlogException('La extensión del archivo no coincide con su contenido.');
    }
    $imageInfo = @getimagesize($temporary);
    if (!is_array($imageInfo) || ($imageInfo[0] ?? 0) < 1 || ($imageInfo[1] ?? 0) < 1 || ($imageInfo['mime'] ?? '') !== $mime) {
        throw new PortadaBlogException('No fue posible validar la estructura de ' . mb_strtolower($label) . '.');
    }

    return ['temporal' => $temporary, 'extension' => PORTADA_BLOG_MIMES[$mime]];
}

function directorioPortadaBlog(int $articleId): string
{
    return dirname(__DIR__, 3) . '/public/uploads/blog/' . $articleId;
}

/** @param array{temporal:string,extension:string} $validated */
function guardarPortadaBlogValidada(int $articleId, array $validated): array
{
    return guardarImagenBlogValidada($articleId, $validated, 'portada');
}

/** @param array{temporal:string,extension:string} $validated */
function guardarImagenComplementariaBlogValidada(int $articleId, array $validated): array
{
    return guardarImagenBlogValidada($articleId, $validated, 'complementaria');
}

/** @param array{temporal:string,extension:string} $validated */
function guardarImagenBlogValidada(int $articleId, array $validated, string $prefix): array
{
    $directory = directorioPortadaBlog($articleId);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new PortadaBlogException('No fue posible preparar el almacenamiento de la portada.');
    }

    $filename = sprintf(
        '%s_%d_%s_%s.%s',
        $prefix,
        $articleId,
        gmdate('Ymd_His'),
        bin2hex(random_bytes(6)),
        $validated['extension']
    );
    $absolutePath = $directory . '/' . $filename;
    if (!move_uploaded_file($validated['temporal'], $absolutePath)) {
        throw new PortadaBlogException('No fue posible guardar la portada seleccionada.');
    }

    return [
        'absoluta' => $absolutePath,
        'relativa' => 'uploads/blog/' . $articleId . '/' . $filename,
    ];
}

function eliminarPortadaBlog(mixed $relativePath): void
{
    if (!is_string($relativePath)) {
        return;
    }
    $relativePath = str_replace('\\', '/', trim($relativePath));
    if (preg_match('#^uploads/blog/[1-9][0-9]*/[^/]+\.(?:jpg|png|webp)$#i', $relativePath) !== 1) {
        return;
    }

    $absolutePath = dirname(__DIR__, 3) . '/public/' . $relativePath;
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}
