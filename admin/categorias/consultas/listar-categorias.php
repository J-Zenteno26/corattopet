<?php

declare(strict_types=1);

function listarCategorias(PDO $connection): array
{
    $statement = $connection->prepare(
        'SELECT id_categoria, nombre, slug, descripcion, orden, activo, actualizado_en
        FROM categorias
        ORDER BY orden ASC, nombre ASC'
    );
    $statement->execute();

    return $statement->fetchAll();
}
