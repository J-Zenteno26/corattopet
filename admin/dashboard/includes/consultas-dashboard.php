<?php

declare(strict_types=1);

function obtenerResumenDashboard(PDO $pdo): array
{
    $statement = $pdo->query("SELECT
        (SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado_pago='pagado' AND creado_en>=date_trunc('month',CURRENT_DATE)) AS ventas_mes,
        (SELECT COUNT(*) FROM pedidos WHERE creado_en>=date_trunc('month',CURRENT_DATE)) AS pedidos_mes,
        (SELECT COUNT(*) FROM pedidos WHERE estado IN ('recibido','en_preparacion','listo_para_retiro','enviado')) AS pedidos_pendientes,
        (SELECT COUNT(*) FROM pedidos WHERE estado_pago='pendiente') AS pagos_pendientes,
        (SELECT COUNT(*) FROM clientes) AS clientes_registrados,
        (SELECT COUNT(*) FROM productos WHERE estado<>'descontinuado') AS productos_activos,
        (SELECT COUNT(*) FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN stock s ON s.id_producto=p.id_producto
            WHERE p.estado<>'descontinuado' AND (s.cantidad_actual-s.cantidad_reservada)>0
            AND ((c.maneja_fraccionamiento=TRUE AND (s.cantidad_actual-s.cantidad_reservada)<s.stock_minimo)
              OR (c.maneja_fraccionamiento=FALSE AND (s.cantidad_actual-s.cantidad_reservada)<=s.stock_minimo))) AS stock_bajo,
        (SELECT COUNT(*) FROM productos p JOIN stock s ON s.id_producto=p.id_producto WHERE p.estado<>'descontinuado' AND (s.cantidad_actual-s.cantidad_reservada)<=0) AS sin_stock");
    return $statement->fetch() ?: [];
}

function obtenerPedidosRecientesDashboard(PDO $pdo, int $limit = 5): array
{
    $statement=$pdo->prepare('SELECT p.id_pedido,p.codigo_pedido,p.estado,p.estado_pago,p.total,p.creado_en,c.nombre AS cliente
        FROM pedidos p LEFT JOIN clientes c ON c.id_cliente=p.id_cliente ORDER BY p.creado_en DESC,p.id_pedido DESC LIMIT :limit');
    $statement->bindValue(':limit',$limit,PDO::PARAM_INT);$statement->execute();return $statement->fetchAll();
}

function obtenerAlertasStockDashboard(PDO $pdo, int $limit = 8): array
{
    $statement=$pdo->prepare("SELECT p.id_producto,p.nombre,p.sku,c.nombre AS categoria,c.maneja_fraccionamiento,
        (s.cantidad_actual-s.cantidad_reservada) AS stock_actual,s.stock_minimo,
        CASE WHEN (s.cantidad_actual-s.cantidad_reservada)<=0 THEN 'sin_stock' ELSE 'stock_bajo' END AS estado_stock
        FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN stock s ON s.id_producto=p.id_producto
        WHERE p.estado<>'descontinuado' AND ((s.cantidad_actual-s.cantidad_reservada)<=0 OR
          ((s.cantidad_actual-s.cantidad_reservada)>0 AND ((c.maneja_fraccionamiento=TRUE AND (s.cantidad_actual-s.cantidad_reservada)<s.stock_minimo)
          OR (c.maneja_fraccionamiento=FALSE AND (s.cantidad_actual-s.cantidad_reservada)<=s.stock_minimo))))
        ORDER BY CASE WHEN (s.cantidad_actual-s.cantidad_reservada)<=0 THEN 0 ELSE 1 END,(s.cantidad_actual-s.cantidad_reservada),p.nombre LIMIT :limit");
    $statement->bindValue(':limit',$limit,PDO::PARAM_INT);$statement->execute();return $statement->fetchAll();
}

function obtenerClientesRecientesDashboard(PDO $pdo, int $limit = 5): array
{
    $statement=$pdo->prepare('SELECT c.id_cliente,c.nombre,c.email,c.telefono,c.comuna,COUNT(p.id_pedido) AS pedidos,MAX(p.creado_en) AS ultima_compra
        FROM clientes c LEFT JOIN pedidos p ON p.id_cliente=c.id_cliente GROUP BY c.id_cliente
        ORDER BY COALESCE(MAX(p.creado_en),c.creado_en) DESC,c.id_cliente DESC LIMIT :limit');
    $statement->bindValue(':limit',$limit,PDO::PARAM_INT);$statement->execute();return $statement->fetchAll();
}

function obtenerPendientesCatalogoDashboard(PDO $pdo): array
{
    $statement=$pdo->query("SELECT
        COUNT(*) FILTER (WHERE NOT EXISTS (SELECT 1 FROM imagenes_producto ip WHERE ip.id_producto=p.id_producto AND ip.activo=TRUE)) AS sin_imagen,
        COUNT(*) FILTER (WHERE (p.sku IS NULL OR TRIM(p.sku)='')) AS sin_sku,
        COUNT(*) FILTER (WHERE c.maneja_fraccionamiento=TRUE AND NOT EXISTS (SELECT 1 FROM producto_presentaciones pp WHERE pp.id_producto=p.id_producto AND pp.activo=TRUE)) AS sin_presentaciones,
        COUNT(*) FILTER (WHERE (s.cantidad_actual-s.cantidad_reservada)<=0) AS sin_stock
        FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN stock s ON s.id_producto=p.id_producto WHERE p.estado<>'descontinuado'");
    return $statement->fetch() ?: [];
}

function obtenerConfiguracionDashboard(PDO $pdo): array
{
    $statement=$pdo->query('SELECT nombre_tienda,descripcion_breve,email_contacto,whatsapp_principal,moneda,modo_tienda,
        permite_despacho,permite_retiro,permitir_venta_sin_stock,mostrar_stock FROM configuracion_tienda WHERE id_configuracion=1');
    return $statement->fetch() ?: [];
}
