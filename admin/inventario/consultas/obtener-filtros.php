<?php

declare(strict_types=1);

function obtenerFiltrosInventario(PDO $connection): array
{
    $categoriesStatement = $connection->prepare(
        'SELECT id_categoria, nombre
        FROM categorias
        WHERE activo = TRUE
        ORDER BY orden ASC, nombre ASC'
    );
    $categoriesStatement->execute();

    $brandsStatement = $connection->prepare(
        'SELECT id_marca, nombre
        FROM marcas
        WHERE activo = TRUE
        ORDER BY nombre ASC'
    );
    $brandsStatement->execute();

    return [
        'categorias' => $categoriesStatement->fetchAll(),
        'marcas' => $brandsStatement->fetchAll(),
    ];
}
