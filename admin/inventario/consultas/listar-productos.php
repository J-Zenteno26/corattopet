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
            (SELECT c.slug
             FROM productos p
             INNER JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE p.id_producto = vi.id_producto) AS categoria_slug,
            (SELECT p.detalles_opcionales FROM productos p
             WHERE p.id_producto = vi.id_producto) AS detalles_opcionales,
            (SELECT COUNT(pp.id_presentacion)
             FROM producto_presentaciones pp
             WHERE pp.id_producto = vi.id_producto AND pp.activo = TRUE) AS presentaciones_activas,
            (SELECT COUNT(*) FROM stock_lotes sl
             WHERE sl.id_producto=vi.id_producto AND sl.activo=TRUE) AS lotes_activos,
            (SELECT MIN(CASE
                WHEN sl.fecha_vencimiento<CURRENT_DATE THEN 1
                WHEN sl.fecha_vencimiento<CURRENT_DATE+INTERVAL \'2 months\' THEN 2
                WHEN sl.fecha_vencimiento<=CURRENT_DATE+INTERVAL \'6 months\' THEN 3
                ELSE 4 END)
             FROM stock_lotes sl WHERE sl.id_producto=vi.id_producto AND sl.activo=TRUE) AS lote_prioridad
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
        $fractionableFilter = "EXISTS (SELECT 1 FROM productos fp WHERE fp.id_producto = vi.id_producto AND UPPER(fp.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO')";
        $where[] = $filters['tipo_stock'] === 'fraccionable' ? $fractionableFilter : 'NOT ' . $fractionableFilter;
    }

    $subcategoryNames = [
        'alimento_seco' => 'ALIMENTO SECO',
        'alimento_humedo' => 'ALIMENTO HÚMEDO',
        'snacks' => 'SNACKS',
        'higiene_bienestar' => 'HIGIENE / BIENESTAR',
    ];
    if (isset($subcategoryNames[$filters['subcategoria']])) {
        $where[] = "EXISTS (SELECT 1 FROM productos sf WHERE sf.id_producto=vi.id_producto AND UPPER(sf.detalles_opcionales->>'subcategoria') = :subcategoria)";
        $bindings['subcategoria'] = $subcategoryNames[$filters['subcategoria']];
    }

    $fractionableCondition = "EXISTS (SELECT 1 FROM productos sp WHERE sp.id_producto = vi.id_producto AND UPPER(sp.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO')";
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
            (SELECT c.slug
             FROM productos p
             INNER JOIN categorias c ON c.id_categoria = p.id_categoria
             WHERE p.id_producto = vi.id_producto) AS categoria_slug,
            (SELECT p.detalles_opcionales FROM productos p
             WHERE p.id_producto = vi.id_producto) AS detalles_opcionales,
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
