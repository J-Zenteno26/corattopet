<?php

declare(strict_types=1);

const CATEGORIA_ALIMENTOS_SLUG = 'alimentos';
const SUBCATEGORIA_ALIMENTO_SECO_CODIGO = 'alimento-seco';

function codigoSubcategoriaProducto(mixed $value): string
{
    $text = mb_strtolower(trim(is_scalar($value) ? (string) $value : ''));
    if ($text === '') {
        return '';
    }

    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $code = preg_replace('/[^a-z0-9]+/', '-', $transliterated === false ? $text : $transliterated) ?? '';

    return trim($code, '-');
}

function esCategoriaAlimentos(array $category): bool
{
    return mb_strtolower(trim((string) ($category['categoria_slug'] ?? $category['slug'] ?? '')))
        === CATEGORIA_ALIMENTOS_SLUG;
}

function esProductoFraccionable(array $product): bool
{
    $details = $product['detalles_opcionales'] ?? null;
    if (is_string($details)) {
        $decoded = json_decode($details, true);
        $details = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($details)) {
        $details = [];
    }

    $subcategory = $product['subcategoria'] ?? $details['subcategoria'] ?? '';
    return mb_strtoupper(is_scalar($subcategory) ? (string) $subcategory : '') === 'ALIMENTO SECO';
}

function formatearCantidadStock(int $quantity, bool $fractionable): string
{
    if (!$fractionable) {
        return $quantity . ($quantity === 1 ? ' unidad' : ' unidades');
    }

    $sign = $quantity < 0 ? '-' : '';
    $absolute = abs($quantity);
    if ($absolute < 1000) {
        return $sign . $absolute . ' g';
    }

    $kilograms = intdiv($absolute, 1000);
    $grams = $absolute % 1000;
    if ($grams === 0) {
        return $sign . $kilograms . ' kg';
    }

    $decimal = rtrim(rtrim(str_pad((string) $grams, 3, '0', STR_PAD_LEFT), '0'), '.');

    return $sign . $kilograms . ',' . $decimal . ' kg';
}

function convertirPesoAGramos(string|int|float $quantity, string $unit): int
{
    $normalized = trim(str_replace(',', '.', (string) $quantity));
    if (!preg_match('/^(?:\d+)(?:\.\d{1,3})?$/', $normalized)) {
        throw new InvalidArgumentException('La cantidad debe ser un número válido con hasta 3 decimales.');
    }

    [$whole, $decimals] = array_pad(explode('.', $normalized, 2), 2, '');
    if ($unit === 'g') {
        if ($decimals !== '') {
            throw new InvalidArgumentException('Los gramos deben ingresarse como un número entero.');
        }

        return (int) $whole;
    }
    if ($unit !== 'kg') {
        throw new InvalidArgumentException('Selecciona gramos o kilogramos.');
    }

    return ((int) $whole * 1000) + (int) str_pad($decimals, 3, '0');
}
