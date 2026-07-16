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
            ms.motivo,
            ms.referencia,
            u.nombre AS usuario
        FROM movimientos_stock ms
        LEFT JOIN usuarios u ON u.id_usuario = ms.id_usuario
        WHERE ms.id_producto = :id_producto
        ORDER BY ms.creado_en DESC
        LIMIT 10'
    );
    $statement->execute(['id_producto' => $productId]);

    return $statement->fetchAll();
}
