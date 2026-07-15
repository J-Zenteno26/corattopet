<?php

declare(strict_types=1);

function buscarProductoParaEditar(PDO $connection, int $productId): ?array
{
    $statement = $connection->prepare(
        'SELECT
            p.id_producto,
            p.nombre,
            p.slug,
            p.id_categoria,
            p.id_marca,
            p.tipo_mascota,
            p.sku,
            p.codigo_barras,
            p.precio_venta,
            p.detalles_opcionales,
            p.estado,
            s.stock_minimo,
            s.cantidad_actual
        FROM productos p
        INNER JOIN stock s ON s.id_producto = p.id_producto
        WHERE p.id_producto = :id_producto'
    );
    $statement->execute(['id_producto' => $productId]);
    $product = $statement->fetch();

    return is_array($product) ? $product : null;
}
