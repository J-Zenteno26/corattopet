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
    $stockTypes = ['fraccionable', 'unidad'];
    $subcategories = ['alimento_seco', 'alimento_humedo', 'snacks', 'higiene_bienestar'];
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
        'tipo_stock' => in_array($source['tipo_stock'] ?? '', $stockTypes, true)
            ? (string) $source['tipo_stock']
            : '',
        'subcategoria' => in_array($source['subcategoria'] ?? '', $subcategories, true)
            ? (string) $source['subcategoria']
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
        || $parameters['estado_stock'] !== ''
        || $parameters['tipo_stock'] !== ''
        || $parameters['subcategoria'] !== '';
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

function estadoFechaLoteInventario(string $date, ?DateTimeImmutable $today = null): array
{
    $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
    $expiration = (new DateTimeImmutable($date))->setTime(0, 0);
    if ($expiration < $today) return ['key'=>'expired','label'=>'Vencido','priority'=>1];
    if ($expiration < $today->modify('+2 months')) return ['key'=>'critical','label'=>'Crítico','priority'=>2];
    if ($expiration <= $today->modify('+6 months')) return ['key'=>'upcoming','label'=>'Próximo','priority'=>3];
    return ['key'=>'current','label'=>'Vigente','priority'=>4];
}

function estadoLotesPorPrioridadInventario(mixed $priority): array
{
    return match ((int) $priority) {
        1 => ['key'=>'expired','label'=>'Vencido'],
        2 => ['key'=>'critical','label'=>'Crítico'],
        3 => ['key'=>'upcoming','label'=>'Próximo'],
        4 => ['key'=>'current','label'=>'Vigente'],
        default => ['key'=>'none','label'=>'Sin lotes'],
    };
}

function formatearPesoLoteInventario(mixed $grams): string
{
    $value = (float) $grams;
    $unitValue = $value >= 1000 ? $value / 1000 : $value;
    $formatted = number_format($unitValue, 3, ',', '.');
    $formatted = rtrim(rtrim($formatted, '0'), ',');
    return $formatted . ($value >= 1000 ? ' kg' : ' g');
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

function textoEstadoStockInventario(array $product): string
{
    $available = (int) ($product['cantidad_disponible'] ?? 0);
    if ($available === 0) {
        return 'Sin stock';
    }

    $minimum = (int) ($product['stock_minimo'] ?? 0);
    if (esProductoFraccionable($product)) {
        return $available < $minimum ? 'Stock bajo' : 'En stock';
    }

    return $available <= $minimum ? 'Stock bajo' : 'En stock';
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
