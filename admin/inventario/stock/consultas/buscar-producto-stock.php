<?php

declare(strict_types=1);

function buscarProductoStock(PDO $connection, int $productId): ?array
{
    $statement = $connection->prepare(
        'SELECT
            p.id_producto,
            p.nombre,
            p.sku,
            c.nombre AS categoria,
            m.nombre AS marca,
            s.cantidad_actual,
            s.stock_minimo
        FROM productos p
        INNER JOIN stock s ON s.id_producto = p.id_producto
        INNER JOIN categorias c ON c.id_categoria = p.id_categoria
        LEFT JOIN marcas m ON m.id_marca = p.id_marca
        WHERE p.id_producto = :id_producto
        LIMIT 1'
    );
    $statement->execute(['id_producto' => $productId]);
    $product = $statement->fetch();

    return is_array($product) ? $product : null;
}
