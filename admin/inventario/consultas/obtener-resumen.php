<?php

declare(strict_types=1);

function obtenerResumenInventario(PDO $connection): array
{
    $statement = $connection->prepare(
        "SELECT
            COUNT(p.id_producto) FILTER (WHERE p.estado <> 'descontinuado') AS productos_totales,
            COUNT(p.id_producto) FILTER (
                WHERE p.estado <> 'descontinuado'
                AND UPPER(p.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO'
            ) AS alimentos_fraccionables,
            COUNT(p.id_producto) FILTER (
                WHERE p.estado = 'activo'
                AND UPPER(p.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO'
                AND NOT EXISTS (
                    SELECT 1 FROM producto_presentaciones pp
                    WHERE pp.id_producto = p.id_producto AND pp.activo = TRUE
                )
            ) AS sin_presentaciones,
            COUNT(p.id_producto) FILTER (
                WHERE p.estado <> 'descontinuado'
                AND COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) = 0
            ) AS sin_stock,
            (SELECT COUNT(*) FROM stock_lotes sl WHERE sl.activo=TRUE AND sl.fecha_vencimiento<CURRENT_DATE) AS lotes_vencidos,
            (SELECT COUNT(*) FROM stock_lotes sl WHERE sl.activo=TRUE AND sl.fecha_vencimiento>=CURRENT_DATE AND sl.fecha_vencimiento<CURRENT_DATE+INTERVAL '2 months') AS lotes_criticos,
            (SELECT COUNT(*) FROM stock_lotes sl WHERE sl.activo=TRUE AND sl.fecha_vencimiento>=CURRENT_DATE+INTERVAL '2 months' AND sl.fecha_vencimiento<=CURRENT_DATE+INTERVAL '6 months') AS lotes_proximos
        FROM productos p
        INNER JOIN categorias c ON c.id_categoria = p.id_categoria
        LEFT JOIN stock s ON s.id_producto = p.id_producto"
    );
    $statement->execute();
    $summary = $statement->fetch();

    return is_array($summary) ? $summary : [
        'productos_totales' => 0,
        'alimentos_fraccionables' => 0,
        'sin_presentaciones' => 0,
        'sin_stock' => 0,
        'lotes_vencidos' => 0,
        'lotes_criticos' => 0,
        'lotes_proximos' => 0,
    ];
}

function obtenerAlertasLotesInventario(PDO $connection): array
{
    $statement = $connection->prepare(
        "SELECT sl.id_lote, sl.id_producto, p.nombre AS producto, sl.codigo_lote,
            sl.cantidad_disponible_g, sl.fecha_vencimiento,
            CASE WHEN sl.fecha_vencimiento < CURRENT_DATE THEN 'vencidos'
                 WHEN sl.fecha_vencimiento < CURRENT_DATE + INTERVAL '2 months' THEN 'criticos'
                 ELSE 'proximos' END AS categoria
         FROM stock_lotes sl
         INNER JOIN productos p ON p.id_producto = sl.id_producto
         WHERE sl.activo = TRUE
           AND sl.fecha_vencimiento <= CURRENT_DATE + INTERVAL '6 months'
         ORDER BY sl.fecha_vencimiento ASC, p.nombre ASC, sl.id_lote ASC"
    );
    $statement->execute();
    $alerts = ['vencidos' => [], 'criticos' => [], 'proximos' => []];
    foreach ($statement->fetchAll() as $lot) {
        $category = (string) ($lot['categoria'] ?? '');
        if (isset($alerts[$category])) $alerts[$category][] = $lot;
    }
    return $alerts;
}
