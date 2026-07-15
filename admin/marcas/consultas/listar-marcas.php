<?php

declare(strict_types=1);

function listarMarcas(PDO $connection): array
{
    $statement = $connection->prepare('SELECT id_marca, nombre, slug, activo, actualizado_en FROM marcas ORDER BY nombre ASC');
    $statement->execute();

    return $statement->fetchAll();
}
