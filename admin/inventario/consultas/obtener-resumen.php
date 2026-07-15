<?php

declare(strict_types=1);

function obtenerResumenInventario(PDO $connection): array
{
    $statement = $connection->prepare(
        "SELECT
            COUNT(id_producto) FILTER (WHERE estado <> 'descontinuado') AS productos_totales,
            COALESCE(SUM(cantidad_disponible), 0) AS stock_total,
            COUNT(id_producto) FILTER (
                WHERE cantidad_disponible > 0
                AND cantidad_disponible <= stock_minimo
            ) AS stock_bajo,
            COUNT(id_producto) FILTER (WHERE cantidad_disponible = 0) AS sin_stock
        FROM vista_inventario"
    );
    $statement->execute();
    $summary = $statement->fetch();

    return is_array($summary) ? $summary : [
        'productos_totales' => 0,
        'stock_total' => 0,
        'stock_bajo' => 0,
        'sin_stock' => 0,
    ];
}
