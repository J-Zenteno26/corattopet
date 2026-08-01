<?php

declare(strict_types=1);

function listarProveedores(PDO $pdo, string $buscar, string $estado): array
{
    $where = [];
    $params = [];
    if ($buscar !== '') {
        $where[] = "(p.nombre ILIKE :buscar OR p.razon_social ILIKE :buscar OR p.rut ILIKE :buscar OR p.contacto_principal ILIKE :buscar OR p.email ILIKE :buscar)";
        $params['buscar'] = '%' . $buscar . '%';
    }
    if (in_array($estado, ['activo', 'inactivo'], true)) {
        $where[] = 'p.activo=:activo';
        $params['activo'] = $estado === 'activo';
    }
    $sql = 'SELECT p.*, COUNT(pp.id_proveedor_producto) FILTER (WHERE pp.activo=TRUE) AS productos_activos FROM proveedores p LEFT JOIN proveedor_productos pp ON pp.id_proveedor=p.id_proveedor' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' GROUP BY p.id_proveedor ORDER BY p.nombre';
    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v)
        $st->bindValue(':' . $k, $v, is_bool($v) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
    $st->execute();
    return $st->fetchAll();
}

function obtenerProveedor(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM proveedores WHERE id_proveedor=:id');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    return is_array($row) ? $row : null;
}

function productosProveedor(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT pp.*,p.nombre,p.sku FROM proveedor_productos pp INNER JOIN productos p ON p.id_producto=pp.id_producto WHERE pp.id_proveedor=:id ORDER BY pp.activo DESC,p.nombre');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}

function productosDisponiblesProveedor(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT p.id_producto,p.nombre,p.sku FROM productos p WHERE NOT EXISTS (SELECT 1 FROM proveedor_productos pp WHERE pp.id_producto=p.id_producto AND pp.id_proveedor=:id) ORDER BY p.nombre');
    $st->execute(['id' => $id]);
    return $st->fetchAll();
}

function proveedoresActivosProducto(PDO $pdo, int $productoId): array
{
    $st = $pdo->prepare('SELECT p.id_proveedor,p.nombre,pp.sku_proveedor FROM proveedores p INNER JOIN proveedor_productos pp ON pp.id_proveedor=p.id_proveedor WHERE pp.id_producto=:producto AND p.activo=TRUE AND pp.activo=TRUE ORDER BY p.nombre');
    $st->execute(['producto' => $productoId]);
    return $st->fetchAll();
}

function todosProveedoresActivos(PDO $pdo): array
{
    $st = $pdo->query('SELECT id_proveedor,nombre,rut FROM proveedores WHERE activo=TRUE ORDER BY nombre');
    return $st->fetchAll();
}
