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
            imagen_principal,
            categoria,
            marca,
            tipo_mascota,
            precio_venta,
            cantidad_disponible,
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

    $stockConditions = [
        'en_stock' => 'cantidad_disponible > stock_minimo',
        'stock_bajo' => 'cantidad_disponible > 0 AND cantidad_disponible <= stock_minimo',
        'sin_stock' => 'cantidad_disponible = 0',
    ];
    if (isset($stockConditions[$filters['estado_stock']])) {
        $where[] = '(' . $stockConditions[$filters['estado_stock']] . ')';
    }

    return [$where, $bindings];
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
