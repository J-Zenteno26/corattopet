<?php

declare(strict_types=1);

function sanitizarContenidoBlog(mixed $value): string
{
    $content = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], (string) $value);
    $content = trim($content);
    if ($content === '') {
        return '';
    }

    $allowedPattern = '(?:p|h2|h3|strong|em|ul|ol|li|blockquote|a|hr|br)';
    if (preg_match('/<\s*' . $allowedPattern . '\b/iu', $content) !== 1) {
        return convertirTextoPlanoAHtmlBlog($content);
    }

    $blockedTags = 'script|style|iframe|object|embed|svg|math|template|form|input|button|textarea|select|option|link|meta|base';
    do {
        $content = preg_replace(
            '/<\s*(' . $blockedTags . ')\b[^>]*>.*?<\/\s*\1\s*>/isu',
            '',
            $content,
            -1,
            $removedBlocks
        ) ?? '';
    } while ($removedBlocks > 0);

    $content = preg_replace('/<!--.*?-->/su', '', $content) ?? '';
    $content = preg_replace('/<!DOCTYPE[^>]*>|<\?.*?\?>/isu', '', $content) ?? '';
    $content = strip_tags(
        $content,
        '<p><h2><h3><strong><em><ul><ol><li><blockquote><a><hr><br>'
    );

    $content = preg_replace_callback(
        '/<\s*(\/?)\s*(p|h2|h3|strong|em|ul|ol|li|blockquote|a|hr|br)\b([^>]*)>/iu',
        static function (array $matches): string {
            $isClosing = $matches[1] === '/';
            $tag = strtolower($matches[2]);
            $attributes = $matches[3] ?? '';

            if ($isClosing) {
                return in_array($tag, ['hr', 'br'], true) ? '' : '</' . $tag . '>';
            }

            if (in_array($tag, ['hr', 'br'], true)) {
                return '<' . $tag . '>';
            }

            if ($tag !== 'a') {
                return '<' . $tag . '>';
            }

            $href = extraerHrefContenidoBlog($attributes);
            if ($href === null) {
                return '<a>';
            }

            $escapedHref = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            if (preg_match('/^https?:\/\//i', $href) === 1) {
                return '<a href="' . $escapedHref . '" target="_blank" rel="noopener noreferrer">';
            }

            return '<a href="' . $escapedHref . '">';
        },
        $content
    ) ?? '';

    $content = preg_replace('/[ \t]+$/mu', '', $content) ?? '';
    $content = preg_replace('/(?:<br>\s*){3,}/iu', '<br><br>', $content) ?? '';

    return trim($content);
}

function convertirTextoPlanoAHtmlBlog(string $content): string
{
    $content = preg_replace('/[ \t]+$/mu', '', $content) ?? '';
    $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? '';
    $paragraphs = preg_split('/\n{2,}/', trim($content)) ?: [];
    $html = [];

    foreach ($paragraphs as $paragraph) {
        $escaped = htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        if ($escaped === '') {
            continue;
        }
        $html[] = '<p>' . nl2br($escaped, false) . '</p>';
    }

    return implode("\n", $html);
}

function extraerHrefContenidoBlog(string $attributes): ?string
{
    $href = null;
    if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/isu', $attributes, $match) === 1) {
        $href = $match[2];
    } elseif (preg_match('/\bhref\s*=\s*([^\s"\'=<>`]+)/iu', $attributes, $match) === 1) {
        $href = $match[1];
    }

    if (!is_string($href)) {
        return null;
    }

    $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $href = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $href) ?? '');
    if ($href === '') {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $href) === 1) {
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        return filter_var($href, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true)
            ? $href
            : null;
    }

    if (preg_match('/^mailto:[^@\s]+@[^@\s]+\.[^@\s]+$/iu', $href) === 1) {
        return $href;
    }

    if (preg_match('#^/(?!/)#', $href) === 1) {
        return $href;
    }

    return null;
}

function contenidoBlogTieneTexto(string $content): bool
{
    $plainText = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plainText = preg_replace('/\x{00A0}/u', ' ', $plainText) ?? '';

    return trim($plainText) !== '';
}

function urlPortadaBlog(mixed $path): ?string
{
    if (!is_string($path) || trim($path) === '') {
        return null;
    }

    $path = trim($path);
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return null;
    }

    $relativePath = ltrim(str_replace('\\', '/', $path), '/');
    if (str_starts_with($relativePath, 'uploads/')) {
        $relativePath = 'public/' . $relativePath;
    }

    return appUrl($relativePath);
}

function urlVideoBlog(mixed $value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $url = trim($value);
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = parse_url($url, PHP_URL_HOST);

    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && in_array($scheme, ['http', 'https'], true)
        && is_string($host)
        && $host !== ''
            ? $url
            : null;
}
