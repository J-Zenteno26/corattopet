<?php

declare(strict_types=1);

function listarProductosInventario(PDO $connection, array $filters): array
{
    [$where, $bindings] = construirFiltrosSqlInventario($filters);
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $countStatement = $connection->prepare(
        'SELECT COUNT(vi.id_producto) FROM vista_inventario vi' . $whereSql
    );
    ejecutarConsultaInventario($countStatement, $bindings);
    $totalRecords = (int) $countStatement->fetchColumn();
    $perPage = $filters['por_pagina'];
    $totalPages = max(1, (int) ceil($totalRecords / $perPage));
    $currentPage = min($filters['pagina'], $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    $productsStatement = $connection->prepare(
        'SELECT
            id_producto,
            nombre,
            sku,
            codigo_barras,
            COALESCE(
                (SELECT ip.archivo
                 FROM imagenes_producto ip
                 WHERE ip.id_producto = vi.id_producto AND ip.activo = TRUE
                 ORDER BY ip.es_principal DESC, ip.orden, ip.id_imagen
                 LIMIT 1),
                imagen_principal
            ) AS imagen_principal,
            categoria,
            marca,
            tipo_mascota,
            precio_venta,
            cantidad_disponible,
            stock_minimo,
            estado_stock,
            actualizado_en,
            (SELECT c.maneja_fraccionamiento
             FROM productos p
             INNER JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE p.id_producto = vi.id_producto) AS maneja_fraccionamiento,
            (SELECT COUNT(pp.id_presentacion)
             FROM producto_presentaciones pp
             WHERE pp.id_producto = vi.id_producto AND pp.activo = TRUE) AS presentaciones_activas
        FROM vista_inventario vi'
        . $whereSql
        . ' ORDER BY actualizado_en DESC, id_producto DESC LIMIT :limit OFFSET :offset'
    );
    ejecutarConsultaInventario($productsStatement, $bindings, $perPage, $offset);

    return [
        'registros' => $productsStatement->fetchAll(),
        'total_registros' => $totalRecords,
        'total_paginas' => $totalPages,
        'pagina_actual' => $currentPage,
        'por_pagina' => $perPage,
    ];
}

function construirFiltrosSqlInventario(array $filters): array
{
    $where = ["estado <> 'descontinuado'"];
    $bindings = [];

    if ($filters['buscar'] !== '') {
        $where[] = '(nombre ILIKE :search_name OR sku ILIKE :search_sku OR codigo_barras ILIKE :search_barcode)';
        $searchValue = '%' . $filters['buscar'] . '%';
        $bindings['search_name'] = $searchValue;
        $bindings['search_sku'] = $searchValue;
        $bindings['search_barcode'] = $searchValue;
    }

    foreach (['id_categoria', 'id_marca'] as $field) {
        if ($filters[$field] !== null) {
            $where[] = $field . ' = :' . $field;
            $bindings[$field] = $filters[$field];
        }
    }

    if ($filters['tipo_mascota'] !== '') {
        $where[] = 'tipo_mascota = :tipo_mascota';
        $bindings['tipo_mascota'] = $filters['tipo_mascota'];
    }

    if ($filters['tipo_stock'] !== '') {
        $where[] = $filters['tipo_stock'] === 'fraccionable'
            ? 'EXISTS (SELECT 1 FROM productos fp INNER JOIN categorias fc ON fc.id_categoria = fp.id_categoria WHERE fp.id_producto = vi.id_producto AND fc.maneja_fraccionamiento = TRUE)'
            : 'EXISTS (SELECT 1 FROM productos up INNER JOIN categorias uc ON uc.id_categoria = up.id_categoria WHERE up.id_producto = vi.id_producto AND uc.maneja_fraccionamiento = FALSE)';
    }

    $fractionableCondition = 'EXISTS (SELECT 1 FROM productos sp INNER JOIN categorias sc ON sc.id_categoria = sp.id_categoria WHERE sp.id_producto = vi.id_producto AND sc.maneja_fraccionamiento = TRUE)';
    $stockConditions = [
        'en_stock' => 'cantidad_disponible > 0 AND (('
            . $fractionableCondition . ' AND cantidad_disponible >= stock_minimo) OR (NOT '
            . $fractionableCondition . ' AND cantidad_disponible > stock_minimo))',
        'stock_bajo' => 'cantidad_disponible > 0 AND (('
            . $fractionableCondition . ' AND cantidad_disponible < stock_minimo) OR (NOT '
            . $fractionableCondition . ' AND cantidad_disponible <= stock_minimo))',
        'sin_stock' => 'cantidad_disponible = 0',
    ];
    if (isset($stockConditions[$filters['estado_stock']])) {
        $where[] = '(' . $stockConditions[$filters['estado_stock']] . ')';
    }

    return [$where, $bindings];
}

function listarProductosInventarioExportacion(PDO $connection, array $filters, int $limit = 5001): array
{
    [$where, $bindings] = construirFiltrosSqlInventario($filters);
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $statement = $connection->prepare(
        'SELECT vi.id_producto, vi.nombre, vi.sku, vi.codigo_barras, vi.categoria, vi.marca,
            vi.tipo_mascota, vi.precio_venta, vi.cantidad_disponible, vi.stock_minimo,
            vi.estado_stock, vi.estado, vi.actualizado_en,
            (SELECT c.maneja_fraccionamiento
             FROM productos p
             INNER JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE p.id_producto = vi.id_producto) AS maneja_fraccionamiento,
            (SELECT COUNT(pp.id_presentacion) FROM producto_presentaciones pp
             WHERE pp.id_producto = vi.id_producto AND pp.activo = TRUE) AS presentaciones_activas
        FROM vista_inventario vi'
        . $whereSql
        . ' ORDER BY vi.actualizado_en DESC, vi.id_producto DESC LIMIT :limit'
    );
    ejecutarConsultaInventario($statement, $bindings, $limit);

    return $statement->fetchAll();
}

function listarPresentacionesExportacion(PDO $connection, array $productIds): array
{
    $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0)));
    if ($productIds === []) {
        return [];
    }
    $placeholders = [];
    foreach ($productIds as $index => $id) {
        $placeholders[] = ':product_' . $index;
    }
    $statement = $connection->prepare(
        'SELECT p.id_producto, p.nombre AS producto_base, p.sku AS sku_producto_base,
            pp.nombre, pp.cantidad_gramos, pp.precio_venta, pp.sku, pp.activo, pp.orden
        FROM producto_presentaciones pp
        INNER JOIN productos p ON p.id_producto = pp.id_producto
        WHERE pp.id_producto IN (' . implode(', ', $placeholders) . ')
        ORDER BY p.nombre, pp.orden, pp.nombre'
    );
    foreach ($productIds as $index => $id) {
        $statement->bindValue(':product_' . $index, $id, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetchAll();
}

function ejecutarConsultaInventario(
    PDOStatement $statement,
    array $bindings,
    ?int $limit = null,
    ?int $offset = null
): void {
    foreach ($bindings as $name => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $statement->bindValue(':' . $name, $value, $type);
    }

    if ($limit !== null) {
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    }
    if ($offset !== null) {
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    }

    $statement->execute();
}
