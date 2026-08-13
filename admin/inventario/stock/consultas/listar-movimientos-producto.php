<?php

declare(strict_types=1);

function listarMovimientosProducto(PDO $connection, int $productId): array
{
    $statement = $connection->prepare(
        'SELECT
            ms.creado_en,
            ms.tipo_movimiento,
            ms.cantidad,
            ms.stock_anterior,
            ms.stock_final,
            ms.origen,
            ms.motivo,
            ms.referencia,
            ms.id_lote,
            sl.codigo_lote,
            u.nombre AS usuario
        FROM movimientos_stock ms
        LEFT JOIN usuarios u ON u.id_usuario = ms.id_usuario
        LEFT JOIN stock_lotes sl ON sl.id_lote = ms.id_lote
        WHERE ms.id_producto = :id_producto
        ORDER BY ms.creado_en DESC, ms.id_movimiento DESC
        LIMIT 20'
    );
    $statement->execute(['id_producto' => $productId]);

    return $statement->fetchAll();
}
