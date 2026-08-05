<?php

declare(strict_types=1);

function validarArticuloBlog(array $source): array
{
    $categoryId = normalizarIdBlog($source['id_categoria_blog'] ?? null);
    $videoUrl = trim((string) ($source['video_url'] ?? ''));
    $values = [
        'titulo' => normalizarTextoLineaBlog($source['titulo'] ?? ''),
        'slug' => normalizarTextoLineaBlog($source['slug'] ?? ''),
        'id_categoria_blog' => $categoryId,
        'extracto' => normalizarTextoLineaBlog($source['extracto'] ?? ''),
        'video_url' => $videoUrl === '' ? null : $videoUrl,
        'contenido_html' => normalizarContenidoBlog($source['contenido_html'] ?? ''),
        'autor_publico' => normalizarTextoLineaBlog($source['autor_publico'] ?? ''),
    ];
    $errors = [];

    if ($values['titulo'] === '') {
        $errors['titulo'] = 'Ingresa el título del artículo.';
    } elseif (mb_strlen($values['titulo']) > 180) {
        $errors['titulo'] = 'El título no puede superar los 180 caracteres.';
    }

    if ($values['slug'] !== '' && mb_strlen($values['slug']) > 210) {
        $errors['slug'] = 'El slug no puede superar los 210 caracteres.';
    }

    if ($categoryId === null) {
        $errors['id_categoria_blog'] = 'Selecciona una categoría válida.';
    }

    if ($values['extracto'] === '') {
        $errors['extracto'] = 'Ingresa un extracto para el artículo.';
    } elseif (mb_strlen($values['extracto']) > 360) {
        $errors['extracto'] = 'El extracto no puede superar los 360 caracteres.';
    }

    if ($values['video_url'] !== null) {
        if (mb_strlen($values['video_url']) > 500) {
            $errors['video_url'] = 'El enlace de video no puede superar los 500 caracteres.';
        } else {
            $scheme = strtolower((string) parse_url($values['video_url'], PHP_URL_SCHEME));
            $host = parse_url($values['video_url'], PHP_URL_HOST);
            if (
                filter_var($values['video_url'], FILTER_VALIDATE_URL) === false
                || !in_array($scheme, ['http', 'https'], true)
                || !is_string($host)
                || $host === ''
            ) {
                $errors['video_url'] = 'Ingresa una URL absoluta válida con esquema http o https.';
            }
        }
    }

    if (!contenidoBlogTieneTexto($values['contenido_html'])) {
        $errors['contenido_html'] = 'Ingresa el contenido del artículo.';
    } elseif (mb_strlen($values['contenido_html']) > 100000) {
        $errors['contenido_html'] = 'El contenido no puede superar los 100.000 caracteres.';
    }

    if ($values['autor_publico'] === '') {
        $errors['autor_publico'] = 'Ingresa el nombre público del autor.';
    } elseif (mb_strlen($values['autor_publico']) > 120) {
        $errors['autor_publico'] = 'El autor público no puede superar los 120 caracteres.';
    }

    return [$values, $errors];
}
