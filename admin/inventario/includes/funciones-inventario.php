<?php

declare(strict_types=1);

function validarPagina(mixed $value): int
{
    $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $page === false ? 1 : $page;
}

function validarCantidadPorPagina(mixed $value): int
{
    $quantity = filter_var($value, FILTER_VALIDATE_INT);

    return in_array($quantity, [8, 16, 24], true) ? $quantity : 8;
}

function normalizarParametrosInventario(array $source): array
{
    $petTypes = ['perro', 'gato', 'ambos', 'otro'];
    $stockStatuses = ['en_stock', 'stock_bajo', 'sin_stock'];
    $search = trim((string) ($source['buscar'] ?? ''));

    return [
        'buscar' => substr($search, 0, 100),
        'id_categoria' => normalizarIdFiltro($source['id_categoria'] ?? null),
        'id_marca' => normalizarIdFiltro($source['id_marca'] ?? null),
        'tipo_mascota' => in_array($source['tipo_mascota'] ?? '', $petTypes, true)
            ? (string) $source['tipo_mascota']
            : '',
        'estado_stock' => in_array($source['estado_stock'] ?? '', $stockStatuses, true)
            ? (string) $source['estado_stock']
            : '',
        'pagina' => validarPagina($source['pagina'] ?? 1),
        'por_pagina' => validarCantidadPorPagina($source['por_pagina'] ?? 8),
    ];
}

function normalizarIdFiltro(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function construirUrlInventario(array $parameters, array $changes = []): string
{
    $query = array_merge($parameters, $changes);

    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || ($key === 'pagina' && $value === 1)) {
            unset($query[$key]);
        }
    }

    $queryString = http_build_query($query);

    return appUrl('admin/inventario/index.php') . ($queryString === '' ? '' : '?' . $queryString);
}

function hayFiltrosInventarioActivos(array $parameters): bool
{
    return $parameters['buscar'] !== ''
        || $parameters['id_categoria'] !== null
        || $parameters['id_marca'] !== null
        || $parameters['tipo_mascota'] !== ''
        || $parameters['estado_stock'] !== '';
}

function formatearPrecioClp(mixed $price): string
{
    return '$' . number_format((float) $price, 0, ',', '.');
}

function formatearFechaInventario(mixed $date): string
{
    if (!is_string($date) || $date === '') {
        return 'Sin fecha';
    }

    try {
        return (new DateTimeImmutable($date))->format('d-m-Y H:i');
    } catch (Throwable) {
        return 'Sin fecha';
    }
}

function textoTipoMascota(mixed $type): string
{
    return [
        'perro' => 'Perro',
        'gato' => 'Gato',
        'ambos' => 'Perro y gato',
        'otro' => 'Otro',
    ][(string) $type] ?? 'Sin especificar';
}

function textoEstadoStock(mixed $status): string
{
    return [
        'en_stock' => 'En stock',
        'En stock' => 'En stock',
        'stock_bajo' => 'Stock bajo',
        'Stock bajo' => 'Stock bajo',
        'sin_stock' => 'Sin stock',
        'Sin stock' => 'Sin stock',
    ][(string) $status] ?? 'Sin stock';
}

function urlImagenInventario(mixed $path): ?string
{
    if (!is_string($path) || trim($path) === '') {
        return null;
    }

    $path = trim($path);
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($path, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $path : null;
    }

    $relativePath = ltrim($path, '/');
    if (str_starts_with($relativePath, 'uploads/')) {
        $relativePath = 'public/' . $relativePath;
    }

    return appUrl($relativePath);
}
