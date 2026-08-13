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

    $subcategoriesStatement = $connection->prepare(
        'SELECT s.id_subcategoria, s.id_categoria, s.nombre, s.slug
        FROM subcategorias s
        INNER JOIN categorias c ON c.id_categoria = s.id_categoria
        WHERE s.activo = TRUE AND c.activo = TRUE
        ORDER BY s.id_categoria ASC, s.orden ASC, s.nombre ASC'
    );
    $subcategoriesStatement->execute();

    return [
        'categorias' => $categoriesStatement->fetchAll(),
        'marcas' => $brandsStatement->fetchAll(),
        'subcategorias' => $subcategoriesStatement->fetchAll(),
    ];
}

function validarSubcategoriaInventario(PDO $connection, array &$parameters): void
{
    if ($parameters['id_categoria'] === null || $parameters['subcategoria'] === '') {
        $parameters['subcategoria'] = '';
        return;
    }

    $statement = $connection->prepare(
        'SELECT s.nombre, s.slug
        FROM subcategorias s
        INNER JOIN categorias c ON c.id_categoria = s.id_categoria
        WHERE s.id_categoria = :id_categoria
          AND s.slug = :subcategoria
          AND s.activo = TRUE
          AND c.activo = TRUE
        LIMIT 1'
    );
    $statement->execute([
        'id_categoria' => $parameters['id_categoria'],
        'subcategoria' => $parameters['subcategoria'],
    ]);
    $subcategory = $statement->fetch();
    if (!is_array($subcategory)) {
        $parameters['subcategoria'] = '';
        return;
    }

    $parameters['subcategoria'] = (string) $subcategory['slug'];
}
