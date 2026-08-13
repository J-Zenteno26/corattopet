<?php

declare(strict_types=1);

function obtenerResumenDashboard(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            (
                SELECT COALESCE(SUM(p.total), 0)
                FROM pedidos p
                WHERE p.estado_pago = 'pagado'
                  AND EXISTS (
                      SELECT 1
                      FROM pagos_webpay pw
                      WHERE pw.id_pedido = p.id_pedido
                        AND pw.estado = 'autorizado'
                        AND pw.confirmado_en >= date_trunc('month', CURRENT_DATE)
                  )
            ) AS ventas_mes,
            (
                SELECT COUNT(*)
                FROM pedidos
                WHERE creado_en >= date_trunc('month', CURRENT_DATE)
            ) AS pedidos_mes,
            (
                SELECT COUNT(*)
                FROM pedidos
                WHERE estado IN ('recibido', 'en_preparacion', 'listo_para_retiro', 'enviado')
            ) AS pedidos_pendientes,
            (
                SELECT COUNT(*)
                FROM pedidos
                WHERE estado_pago = 'pendiente'
                  AND estado <> 'cancelado'
            ) AS pagos_pendientes,
            (
                SELECT COUNT(*)
                FROM clientes
                WHERE activo = TRUE
                  AND password_hash IS NOT NULL
                  AND TRIM(password_hash) <> ''
            ) AS clientes_registrados,
            (
                SELECT COUNT(*)
                FROM productos
                WHERE estado = 'activo'
            ) AS productos_activos,
            (
                SELECT COUNT(*)
                FROM productos p
                INNER JOIN categorias c
                    ON c.id_categoria = p.id_categoria
                LEFT JOIN stock s
                    ON s.id_producto = p.id_producto
                WHERE p.estado = 'activo'
                  AND (
                      COALESCE(s.cantidad_actual, 0)
                      - COALESCE(s.cantidad_reservada, 0)
                  ) > 0
                  AND (
                      (
                          COALESCE(c.maneja_fraccionamiento, FALSE) = TRUE
                          AND (
                              COALESCE(s.cantidad_actual, 0)
                              - COALESCE(s.cantidad_reservada, 0)
                          ) < COALESCE(s.stock_minimo, 0)
                      )
                      OR
                      (
                          COALESCE(c.maneja_fraccionamiento, FALSE) = FALSE
                          AND (
                              COALESCE(s.cantidad_actual, 0)
                              - COALESCE(s.cantidad_reservada, 0)
                          ) <= COALESCE(s.stock_minimo, 0)
                      )
                  )
            ) AS stock_bajo,
            (
                SELECT COUNT(*)
                FROM productos p
                LEFT JOIN stock s
                    ON s.id_producto = p.id_producto
                WHERE p.estado = 'activo'
                  AND (
                      COALESCE(s.cantidad_actual, 0)
                      - COALESCE(s.cantidad_reservada, 0)
                  ) <= 0
            ) AS sin_stock"
    );

    return $statement->fetch() ?: [];
}

function obtenerPedidosRecientesDashboard(PDO $pdo, int $limit = 5): array
{
    $statement = $pdo->prepare(
        "SELECT
            p.id_pedido,
            p.codigo_pedido,
            p.estado,
            p.estado_pago,
            p.total,
            p.creado_en,
            c.nombre AS cliente
         FROM pedidos p
         LEFT JOIN clientes c
            ON c.id_cliente = p.id_cliente
         ORDER BY p.creado_en DESC, p.id_pedido DESC
         LIMIT :limit"
    );

    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function obtenerAlertasStockDashboard(PDO $pdo, int $limit = 8): array
{
    $statement = $pdo->prepare(
        "SELECT
            p.id_producto,
            p.nombre,
            p.sku,
            c.nombre AS categoria,
            COALESCE(c.maneja_fraccionamiento, FALSE) AS maneja_fraccionamiento,
            (
                COALESCE(s.cantidad_actual, 0)
                - COALESCE(s.cantidad_reservada, 0)
            ) AS stock_actual,
            COALESCE(s.stock_minimo, 0) AS stock_minimo,
            CASE
                WHEN (
                    COALESCE(s.cantidad_actual, 0)
                    - COALESCE(s.cantidad_reservada, 0)
                ) <= 0
                THEN 'sin_stock'
                ELSE 'stock_bajo'
            END AS estado_stock
         FROM productos p
         INNER JOIN categorias c
            ON c.id_categoria = p.id_categoria
         LEFT JOIN stock s
            ON s.id_producto = p.id_producto
         WHERE p.estado = 'activo'
           AND (
               (
                   COALESCE(s.cantidad_actual, 0)
                   - COALESCE(s.cantidad_reservada, 0)
               ) <= 0
               OR
               (
                   (
                       COALESCE(s.cantidad_actual, 0)
                       - COALESCE(s.cantidad_reservada, 0)
                   ) > 0
                   AND (
                       (
                           COALESCE(c.maneja_fraccionamiento, FALSE) = TRUE
                           AND (
                               COALESCE(s.cantidad_actual, 0)
                               - COALESCE(s.cantidad_reservada, 0)
                           ) < COALESCE(s.stock_minimo, 0)
                       )
                       OR
                       (
                           COALESCE(c.maneja_fraccionamiento, FALSE) = FALSE
                           AND (
                               COALESCE(s.cantidad_actual, 0)
                               - COALESCE(s.cantidad_reservada, 0)
                           ) <= COALESCE(s.stock_minimo, 0)
                       )
                   )
               )
           )
         ORDER BY
            CASE
                WHEN (
                    COALESCE(s.cantidad_actual, 0)
                    - COALESCE(s.cantidad_reservada, 0)
                ) <= 0
                THEN 0
                ELSE 1
            END,
            (
                COALESCE(s.cantidad_actual, 0)
                - COALESCE(s.cantidad_reservada, 0)
            ),
            p.nombre
         LIMIT :limit"
    );

    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function obtenerClientesRecientesDashboard(PDO $pdo, int $limit = 5): array
{
    $statement = $pdo->prepare(
        "SELECT
            c.id_cliente,
            c.nombre,
            c.email,
            c.telefono,
            c.comuna,
            COUNT(p.id_pedido) AS pedidos,
            MAX(p.creado_en) AS ultima_compra
         FROM clientes c
         LEFT JOIN pedidos p
            ON p.id_cliente = c.id_cliente
         GROUP BY c.id_cliente
         ORDER BY COALESCE(MAX(p.creado_en), c.creado_en) DESC, c.id_cliente DESC
         LIMIT :limit"
    );

    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function obtenerPendientesCatalogoDashboard(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            COUNT(*) FILTER (
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM imagenes_producto ip
                    WHERE ip.id_producto = p.id_producto
                      AND ip.activo = TRUE
                )
            ) AS sin_imagen,
            COUNT(*) FILTER (
                WHERE p.sku IS NULL
                   OR TRIM(p.sku) = ''
            ) AS sin_sku,
            COUNT(*) FILTER (
                WHERE COALESCE(c.maneja_fraccionamiento, FALSE) = TRUE
                  AND NOT EXISTS (
                      SELECT 1
                      FROM producto_presentaciones pp
                      WHERE pp.id_producto = p.id_producto
                        AND pp.activo = TRUE
                  )
            ) AS sin_presentaciones,
            COUNT(*) FILTER (
                WHERE (
                    COALESCE(s.cantidad_actual, 0)
                    - COALESCE(s.cantidad_reservada, 0)
                ) <= 0
            ) AS sin_stock
         FROM productos p
         INNER JOIN categorias c
            ON c.id_categoria = p.id_categoria
         LEFT JOIN stock s
            ON s.id_producto = p.id_producto
         WHERE p.estado = 'activo'"
    );

    return $statement->fetch() ?: [];
}

function obtenerConfiguracionDashboard(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            nombre_tienda,
            descripcion_breve,
            email_contacto,
            whatsapp_principal,
            moneda,
            modo_tienda,
            permite_despacho,
            permite_retiro,
            permitir_venta_sin_stock,
            mostrar_stock
         FROM configuracion_tienda
         WHERE id_configuracion = 1"
    );

    return $statement->fetch() ?: [];
}
