<?php

declare(strict_types=1);

function obtenerResumenInventario(PDO $connection): array
{
    $statement = $connection->prepare(
        "SELECT
            COUNT(p.id_producto) FILTER (WHERE p.estado <> 'descontinuado') AS productos_totales,
            COUNT(p.id_producto) FILTER (
                WHERE p.estado <> 'descontinuado' AND c.maneja_fraccionamiento = TRUE
            ) AS alimentos_fraccionables,
            COUNT(p.id_producto) FILTER (
                WHERE p.estado = 'activo'
                AND c.maneja_fraccionamiento = TRUE
                AND NOT EXISTS (
                    SELECT 1 FROM producto_presentaciones pp
                    WHERE pp.id_producto = p.id_producto AND pp.activo = TRUE
                )
            ) AS sin_presentaciones,
            COUNT(p.id_producto) FILTER (
                WHERE p.estado <> 'descontinuado'
                AND (s.cantidad_actual - s.cantidad_reservada) = 0
            ) AS sin_stock
        FROM productos p
        INNER JOIN categorias c ON c.id_categoria = p.id_categoria
        INNER JOIN stock s ON s.id_producto = p.id_producto"
    );
    $statement->execute();
    $summary = $statement->fetch();

    return is_array($summary) ? $summary : [
        'productos_totales' => 0,
        'alimentos_fraccionables' => 0,
        'sin_presentaciones' => 0,
        'sin_stock' => 0,
    ];
}
