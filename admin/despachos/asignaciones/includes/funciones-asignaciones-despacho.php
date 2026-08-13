<?php

declare(strict_types=1);

function normalizarIdAsignacionDespacho(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $id === false ? null : $id;
}

function normalizarParametrosAsignacionesDespacho(array $source): array
{
    $search = trim((string) ($source['buscar'] ?? ''));
    $assignmentStatuses = ['pendientes', 'asignados', 'automaticos', 'todos'];
    $perPageOptions = [12, 24, 48];

    $page = filter_var($source['pagina'] ?? 1, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $perPage = filter_var($source['por_pagina'] ?? 12, FILTER_VALIDATE_INT);
    $assignmentStatus = (string) ($source['estado_asignacion'] ?? 'pendientes');

    return [
        'buscar' => mb_substr($search, 0, 100),
        'id_categoria' => normalizarIdAsignacionDespacho($source['id_categoria'] ?? null),
        'id_marca' => normalizarIdAsignacionDespacho($source['id_marca'] ?? null),
        'estado_asignacion' => in_array($assignmentStatus, $assignmentStatuses, true)
            ? $assignmentStatus
            : 'pendientes',
        'pagina' => $page === false ? 1 : $page,
        'por_pagina' => in_array($perPage, $perPageOptions, true) ? $perPage : 12,
    ];
}

function construirUrlAsignacionesDespacho(array $parameters, array $changes = []): string
{
    $query = array_merge($parameters, $changes);

    foreach ($query as $key => $value) {
        if ($value === '' || $value === null || ($key === 'pagina' && $value === 1)) {
            unset($query[$key]);
        }
    }

    $queryString = http_build_query($query);
    $baseUrl = appUrl('admin/despachos/asignaciones/index.php');

    return $queryString === '' ? $baseUrl : $baseUrl . '?' . $queryString;
}

function hayFiltrosAsignacionesDespacho(array $parameters): bool
{
    return $parameters['buscar'] !== ''
        || $parameters['id_categoria'] !== null
        || $parameters['id_marca'] !== null
        || $parameters['estado_asignacion'] !== 'pendientes';
}

function obtenerOpcionesAsignacionesDespacho(PDO $connection): array
{
    $categoriesStatement = $connection->query(
        'SELECT id_categoria, nombre
         FROM categorias
         WHERE activo = TRUE
         ORDER BY nombre'
    );

    $brandsStatement = $connection->query(
        'SELECT id_marca, nombre
         FROM marcas
         WHERE activo = TRUE
         ORDER BY nombre'
    );

    $shippingCategoriesStatement = $connection->query(
        'SELECT id_categoria_despacho, nombre, peso_estimado_gramos, tamano
         FROM categorias_despacho
         WHERE activo = TRUE
         ORDER BY peso_estimado_gramos, nombre'
    );

    return [
        'categorias' => $categoriesStatement->fetchAll(),
        'marcas' => $brandsStatement->fetchAll(),
        'categorias_despacho' => $shippingCategoriesStatement->fetchAll(),
    ];
}

function obtenerResumenAsignacionesDespacho(PDO $connection): array
{
    $statement = $connection->query(
        "SELECT
            COUNT(*) FILTER (
                WHERE pcd.id_producto IS NULL
                AND COALESCE(ppp.presentaciones_con_peso, 0) = 0
            ) AS pendientes,
            COUNT(*) FILTER (WHERE pcd.id_producto IS NOT NULL) AS asignados,
            COUNT(*) FILTER (
                WHERE COALESCE(ppp.presentaciones_con_peso, 0) > 0
            ) AS automaticos
         FROM productos p
         LEFT JOIN productos_categorias_despacho pcd
            ON pcd.id_producto = p.id_producto
         LEFT JOIN (
            SELECT id_producto, COUNT(*) AS presentaciones_con_peso
            FROM producto_presentaciones
            WHERE activo = TRUE
              AND cantidad_gramos IS NOT NULL
              AND cantidad_gramos > 0
            GROUP BY id_producto
         ) ppp ON ppp.id_producto = p.id_producto
         WHERE p.estado = 'activo'"
    );

    $summary = $statement->fetch();

    return is_array($summary)
        ? $summary
        : ['pendientes' => 0, 'asignados' => 0, 'automaticos' => 0];
}

function listarProductosAsignacionesDespacho(PDO $connection, array $parameters): array
{
    $where = ["p.estado = 'activo'"];
    $bindings = [];

    if ($parameters['buscar'] !== '') {
        $where[] = '(p.nombre ILIKE :buscar OR p.sku ILIKE :buscar)';
        $bindings['buscar'] = '%' . $parameters['buscar'] . '%';
    }

    if ($parameters['id_categoria'] !== null) {
        $where[] = 'p.id_categoria = :id_categoria';
        $bindings['id_categoria'] = $parameters['id_categoria'];
    }

    if ($parameters['id_marca'] !== null) {
        $where[] = 'p.id_marca = :id_marca';
        $bindings['id_marca'] = $parameters['id_marca'];
    }

    if ($parameters['estado_asignacion'] === 'pendientes') {
        $where[] = 'pcd.id_producto IS NULL';
        $where[] = 'COALESCE(ppp.presentaciones_con_peso, 0) = 0';
    } elseif ($parameters['estado_asignacion'] === 'asignados') {
        $where[] = 'pcd.id_producto IS NOT NULL';
    } elseif ($parameters['estado_asignacion'] === 'automaticos') {
        $where[] = 'COALESCE(ppp.presentaciones_con_peso, 0) > 0';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $baseFrom = "FROM productos p
        INNER JOIN categorias c ON c.id_categoria = p.id_categoria
        LEFT JOIN marcas m ON m.id_marca = p.id_marca
        LEFT JOIN productos_categorias_despacho pcd
            ON pcd.id_producto = p.id_producto
        LEFT JOIN categorias_despacho cd
            ON cd.id_categoria_despacho = pcd.id_categoria_despacho
        LEFT JOIN (
            SELECT id_producto, COUNT(*) AS presentaciones_con_peso
            FROM producto_presentaciones
            WHERE activo = TRUE
              AND cantidad_gramos IS NOT NULL
              AND cantidad_gramos > 0
            GROUP BY id_producto
        ) ppp ON ppp.id_producto = p.id_producto";

    $countStatement = $connection->prepare(
        'SELECT COUNT(*) ' . $baseFrom . ' ' . $whereSql
    );
    ejecutarConsultaAsignacionesDespacho($countStatement, $bindings);
    $totalRecords = (int) $countStatement->fetchColumn();

    $totalPages = max(1, (int) ceil($totalRecords / $parameters['por_pagina']));
    $currentPage = min($parameters['pagina'], $totalPages);
    $offset = ($currentPage - 1) * $parameters['por_pagina'];

    $statement = $connection->prepare(
        'SELECT
            p.id_producto,
            p.nombre,
            p.sku,
            p.imagen_principal,
            c.nombre AS categoria,
            COALESCE(m.nombre, \'Sin marca\') AS marca,
            cd.id_categoria_despacho,
            cd.nombre AS categoria_despacho,
            cd.peso_estimado_gramos,
            cd.tamano,
            COALESCE(ppp.presentaciones_con_peso, 0) AS presentaciones_con_peso
         ' . $baseFrom . '
         ' . $whereSql . '
         ORDER BY
            CASE WHEN pcd.id_producto IS NULL THEN 0 ELSE 1 END,
            p.nombre,
            p.id_producto
         LIMIT :limit OFFSET :offset'
    );

    ejecutarConsultaAsignacionesDespacho(
        $statement,
        $bindings,
        $parameters['por_pagina'],
        $offset
    );

    return [
        'registros' => $statement->fetchAll(),
        'total_registros' => $totalRecords,
        'total_paginas' => $totalPages,
        'pagina_actual' => $currentPage,
        'por_pagina' => $parameters['por_pagina'],
    ];
}

function ejecutarConsultaAsignacionesDespacho(
    PDOStatement $statement,
    array $bindings,
    ?int $limit = null,
    ?int $offset = null
): void {
    foreach ($bindings as $name => $value) {
        $statement->bindValue(
            ':' . $name,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    if ($limit !== null) {
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    }

    if ($offset !== null) {
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    }

    $statement->execute();
}

function formatearPesoCategoriaDespacho(mixed $grams): string
{
    $value = max(0, (int) $grams);

    if ($value >= 1000) {
        $kilograms = number_format($value / 1000, 2, ',', '.');
        $kilograms = rtrim(rtrim($kilograms, '0'), ',');

        return $kilograms . ' kg';
    }

    return number_format($value, 0, ',', '.') . ' g';
}

function urlImagenAsignacionDespacho(mixed $path): ?string
{
    if (!is_string($path) || trim($path) === '') {
        return null;
    }

    $relativePath = ltrim(str_replace('\\', '/', trim($path)), '/');

    if (str_contains($relativePath, '..')) {
        return null;
    }

    if (str_starts_with($relativePath, 'uploads/')) {
        $relativePath = 'public/' . $relativePath;
    }

    if (!str_starts_with($relativePath, 'public/')) {
        $relativePath = 'public/uploads/productos/' . $relativePath;
    }

    return appUrl($relativePath);
}
