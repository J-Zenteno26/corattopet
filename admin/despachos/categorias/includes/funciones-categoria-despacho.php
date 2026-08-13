<?php

declare(strict_types=1);

function valoresInicialesCategoriaDespacho(): array
{
    return [
        'nombre' => '',
        'peso_estimado_gramos' => '',
        'tamano' => '',
        'activo' => true,
    ];
}

function idPositivoCategoriaDespacho(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $id === false ? null : $id;
}

function obtenerCategoriaDespacho(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare(
        'SELECT
            cd.id_categoria_despacho,
            cd.nombre,
            cd.peso_estimado_gramos,
            cd.tamano,
            cd.activo,
            cd.creado_en,
            cd.actualizado_en,
            COUNT(pcd.id_producto) AS productos_asignados
        FROM categorias_despacho cd
        LEFT JOIN productos_categorias_despacho pcd
            ON pcd.id_categoria_despacho = cd.id_categoria_despacho
        WHERE cd.id_categoria_despacho = :id
        GROUP BY cd.id_categoria_despacho'
    );
    $statement->execute(['id' => $id]);
    $category = $statement->fetch();

    return is_array($category) ? $category : null;
}

function listarCategoriasDespacho(PDO $connection): array
{
    $statement = $connection->query(
        'SELECT
            cd.id_categoria_despacho,
            cd.nombre,
            cd.peso_estimado_gramos,
            cd.tamano,
            cd.activo,
            cd.actualizado_en,
            COUNT(pcd.id_producto) AS productos_asignados
        FROM categorias_despacho cd
        LEFT JOIN productos_categorias_despacho pcd
            ON pcd.id_categoria_despacho = cd.id_categoria_despacho
        GROUP BY cd.id_categoria_despacho
        ORDER BY cd.peso_estimado_gramos ASC, cd.nombre ASC'
    );

    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function existeNombreCategoriaDespacho(PDO $connection, string $name, ?int $excludedId = null): bool
{
    $sql = 'SELECT EXISTS(
        SELECT 1
        FROM categorias_despacho
        WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(:nombre))';

    $parameters = ['nombre' => $name];

    if ($excludedId !== null) {
        $sql .= ' AND id_categoria_despacho <> :excluded_id';
        $parameters['excluded_id'] = $excludedId;
    }

    $sql .= ')';

    $statement = $connection->prepare($sql);
    $statement->execute($parameters);

    return booleanoPostgresMantenedor($statement->fetchColumn());
}

function nombreTamanoDespacho(string $size): string
{
    return match ($size) {
        'pequeno' => 'Pequeño',
        'mediano' => 'Mediano',
        'grande' => 'Grande',
        default => 'Sin definir',
    };
}

function formatearPesoDespacho(int $grams): string
{
    if ($grams >= 1000) {
        $kilograms = $grams / 1000;
        $decimals = floor($kilograms) === $kilograms ? 0 : 2;

        return number_format($kilograms, $decimals, ',', '.') . ' kg';
    }

    return number_format($grams, 0, ',', '.') . ' g';
}
