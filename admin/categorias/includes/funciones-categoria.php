<?php

declare(strict_types=1);

function valoresInicialesCategoria(): array
{
    return ['nombre' => '', 'descripcion' => '', 'orden' => '0', 'activo' => true];
}

function obtenerCategoria(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare(
        'SELECT id_categoria, nombre, descripcion, orden, activo FROM categorias WHERE id_categoria = :id'
    );
    $statement->execute(['id' => $id]);
    $category = $statement->fetch();

    return is_array($category) ? $category : null;
}

function existeNombreCategoria(PDO $connection, string $name, ?int $excludedId = null): bool
{
    $sql = 'SELECT EXISTS(SELECT 1 FROM categorias WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre))';
    $parameters = ['nombre' => $name];
    if ($excludedId !== null) {
        $sql .= ' AND id_categoria <> :excluded_id';
        $parameters['excluded_id'] = $excludedId;
    }
    $sql .= ')';

    $statement = $connection->prepare($sql);
    $statement->execute($parameters);

    return booleanoPostgresMantenedor($statement->fetchColumn());
}

function idPositivoCategoria(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}
