<?php

declare(strict_types=1);

function valoresInicialesMarca(): array
{
    return ['nombre' => '', 'activo' => true];
}

function obtenerMarca(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT id_marca, nombre, activo FROM marcas WHERE id_marca = :id');
    $statement->execute(['id' => $id]);
    $brand = $statement->fetch();

    return is_array($brand) ? $brand : null;
}

function existeNombreMarca(PDO $connection, string $name, ?int $excludedId = null): bool
{
    $sql = 'SELECT EXISTS(SELECT 1 FROM marcas WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre))';
    $parameters = ['nombre' => $name];
    if ($excludedId !== null) {
        $sql .= ' AND id_marca <> :excluded_id';
        $parameters['excluded_id'] = $excludedId;
    }
    $statement = $connection->prepare($sql . ')');
    $statement->execute($parameters);

    return booleanoPostgresMantenedor($statement->fetchColumn());
}

function idPositivoMarca(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}
