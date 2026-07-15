<?php

declare(strict_types=1);

function generarSlugBaseMantenedor(string $name): string
{
    $name = strtr($name, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $slug = strtolower($transliterated === false ? $name : $transliterated);
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '', '-');

    return $slug !== '' ? $slug : 'registro';
}

function generarSlugUnicoMantenedor(
    PDO $connection,
    string $table,
    string $idColumn,
    string $name,
    ?int $excludedId = null
): string {
    $allowed = ['categorias' => 'id_categoria', 'marcas' => 'id_marca'];
    if (($allowed[$table] ?? null) !== $idColumn) {
        throw new InvalidArgumentException('Invalid catalog table.');
    }

    $base = generarSlugBaseMantenedor($name);
    $sql = "SELECT slug FROM {$table} WHERE (LOWER(slug) = :base OR LOWER(slug) LIKE :pattern)";
    $parameters = ['base' => $base, 'pattern' => $base . '-%'];
    if ($excludedId !== null) {
        $sql .= " AND {$idColumn} <> :excluded_id";
        $parameters['excluded_id'] = $excludedId;
    }

    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $existing = array_fill_keys(array_map('strtolower', $statement->fetchAll(PDO::FETCH_COLUMN)), true);

    if (!isset($existing[$base])) {
        return $base;
    }

    for ($suffix = 2; ; $suffix++) {
        $candidate = $base . '-' . $suffix;
        if (!isset($existing[$candidate])) {
            return $candidate;
        }
    }
}

function guardarEstadoMantenedor(string $key, array $values, array $errors, ?string $generalError = null): void
{
    $_SESSION['mantenedor_' . $key] = [
        'valores' => $values,
        'errores' => $errors,
        'error_general' => $generalError,
    ];
}

function consumirEstadoMantenedor(string $key): array
{
    $sessionKey = 'mantenedor_' . $key;
    $state = $_SESSION[$sessionKey] ?? [];
    unset($_SESSION[$sessionKey]);

    return is_array($state) ? $state : [];
}

function booleanoPostgresMantenedor(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 't', 'true'], true);
}

function formatearFechaMantenedor(mixed $date): string
{
    try {
        return is_string($date) ? (new DateTimeImmutable($date))->format('d-m-Y H:i') : 'Sin fecha';
    } catch (Throwable) {
        return 'Sin fecha';
    }
}
