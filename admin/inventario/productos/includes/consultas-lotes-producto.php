<?php
declare(strict_types=1);

function obtenerProductoDetalleLotes(PDO $pdo, int $productId): ?array
{
    $st=$pdo->prepare('SELECT p.id_producto,p.nombre,p.sku,c.nombre AS categoria,m.nombre AS marca,
        COALESCE(s.cantidad_actual-s.cantidad_reservada,0) AS stock_vendible
        FROM productos p INNER JOIN categorias c ON c.id_categoria=p.id_categoria
        LEFT JOIN marcas m ON m.id_marca=p.id_marca LEFT JOIN stock s ON s.id_producto=p.id_producto
        WHERE p.id_producto=:id');
    $st->execute(['id'=>$productId]);$row=$st->fetch();return is_array($row)?$row:null;
}

function obtenerLotesDetalleProducto(PDO $pdo, int $productId): array
{
    $st=$pdo->prepare("SELECT sl.*,pr.nombre AS proveedor,
        COALESCE((SELECT json_agg(json_build_object('nombre',pp.nombre,'unidades',slp.unidades_disponibles,'gramos',slp.gramos_por_unidad) ORDER BY pp.cantidad_gramos,pp.nombre)
          FROM stock_lote_presentaciones slp INNER JOIN producto_presentaciones pp ON pp.id_presentacion=slp.id_presentacion
          WHERE slp.id_lote=sl.id_lote AND slp.activo=TRUE),'[]'::json) AS presentaciones
        FROM stock_lotes sl LEFT JOIN proveedores pr ON pr.id_proveedor=sl.id_proveedor
        WHERE sl.id_producto=:id AND sl.activo=TRUE ORDER BY sl.fecha_vencimiento ASC,sl.id_lote ASC");
    $st->execute(['id'=>$productId]);return $st->fetchAll();
}
