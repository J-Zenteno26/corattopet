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
            c.slug AS categoria_slug,
            m.nombre AS marca,
            p.detalles_opcionales,
            s.cantidad_actual,
            s.cantidad_reservada,
            GREATEST(s.cantidad_actual - s.cantidad_reservada, 0) AS cantidad_disponible,
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

function listarLotesActivosStock(PDO $connection, int $productId): array
{
    $statement = $connection->prepare(
        "SELECT
            sl.id_lote,
            sl.codigo_lote,
            sl.fecha_elaboracion,
            sl.fecha_vencimiento,
            sl.cantidad_inicial_g,
            sl.cantidad_disponible_g,
            sl.saldo_no_asignado_g,
            pr.nombre AS proveedor
        FROM stock_lotes sl
        LEFT JOIN proveedores pr ON pr.id_proveedor = sl.id_proveedor
        WHERE sl.id_producto = :id_producto
          AND sl.activo = TRUE
        ORDER BY
            CASE WHEN sl.fecha_vencimiento < CURRENT_DATE THEN 1 ELSE 0 END ASC,
            sl.fecha_vencimiento ASC,
            sl.id_lote ASC"
    );
    $statement->execute(['id_producto' => $productId]);

    return $statement->fetchAll();
}
