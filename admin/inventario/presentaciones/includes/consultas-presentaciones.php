<?php

declare(strict_types=1);

function buscarProductoFraccionable(PDO $connection, int $productId): ?array
{
    $statement = $connection->prepare(
        'SELECT p.id_producto, p.nombre, p.sku
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria
         WHERE p.id_producto = :id_producto AND c.maneja_fraccionamiento = TRUE'
    );
    $statement->execute(['id_producto' => $productId]);
    $product = $statement->fetch();

    return is_array($product) ? $product : null;
}

function listarPresentaciones(PDO $connection, int $productId): array
{
    $statement = $connection->prepare(
        'SELECT id_presentacion, id_producto, nombre, cantidad_gramos, precio_venta, sku, activo, orden, actualizado_en
         FROM producto_presentaciones
         WHERE id_producto = :id_producto
         ORDER BY orden ASC, nombre ASC'
    );
    $statement->execute(['id_producto' => $productId]);

    return $statement->fetchAll();
}

function buscarPresentacion(PDO $connection, int $presentationId): ?array
{
    $statement = $connection->prepare(
        'SELECT id_presentacion, id_producto, nombre, cantidad_gramos, precio_venta, sku, activo, orden
         FROM producto_presentaciones WHERE id_presentacion = :id_presentacion'
    );
    $statement->execute(['id_presentacion' => $presentationId]);
    $presentation = $statement->fetch();

    return is_array($presentation) ? $presentation : null;
}

function existeSkuPresentacion(PDO $connection, string $sku, ?int $excludedId = null): bool
{
    $sql = 'SELECT EXISTS(SELECT 1 FROM producto_presentaciones WHERE LOWER(TRIM(sku)) = LOWER(TRIM(:sku))';
    $parameters = ['sku' => $sku];
    if ($excludedId !== null) {
        $sql .= ' AND id_presentacion <> :excluded_id';
        $parameters['excluded_id'] = $excludedId;
    }
    $statement = $connection->prepare($sql . ')');
    $statement->execute($parameters);

    return in_array($statement->fetchColumn(), [true, 1, '1', 't', 'true'], true);
}
