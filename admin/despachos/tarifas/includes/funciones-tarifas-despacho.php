<?php

declare(strict_types=1);

const TRAMOS_TARIFA_DESPACHO = [
    3000 => 'S',
    6000 => 'M',
    16000 => 'L',
    25000 => 'XL',
];

function idPositivoTarifaDespacho(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $id === false ? null : $id;
}

function filtrosTarifasDespacho(array $source): array
{
    return [
        'id_region' => idPositivoTarifaDespacho($source['id_region'] ?? null),
        'id_comuna' => idPositivoTarifaDespacho($source['id_comuna'] ?? null),
        'buscar' => trim(is_scalar($source['buscar'] ?? null) ? (string) $source['buscar'] : ''),
        'pagina' => max(1, (int) ($source['pagina'] ?? 1)),
        'por_pagina' => in_array(
            (int) ($source['por_pagina'] ?? 20),
            [20, 40, 80],
            true
        )
            ? (int) ($source['por_pagina'] ?? 20)
            : 20,
    ];
}

function listarRegionesTarifasDespacho(PDO $connection): array
{
    $statement = $connection->query(
        "SELECT id_region, nombre
         FROM regiones
         WHERE activo = TRUE
         ORDER BY
            CASE codigo_region
                WHEN '13' THEN 1
                WHEN '06' THEN 2
                WHEN '07' THEN 3
                WHEN '16' THEN 4
                WHEN '08' THEN 5
                WHEN '09' THEN 6
                WHEN '14' THEN 7
                ELSE 99
            END,
            nombre"
    );

    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function listarComunasFiltroTarifasDespacho(PDO $connection, ?int $regionId): array
{
    $sql = 'SELECT id_comuna, id_region, nombre
            FROM comunas
            WHERE activo = TRUE';
    $parameters = [];

    if ($regionId !== null) {
        $sql .= ' AND id_region = :id_region';
        $parameters['id_region'] = $regionId;
    }

    $sql .= ' ORDER BY nombre';

    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function resumenTarifasDespacho(PDO $connection): array
{
    $statement = $connection->query(
        'SELECT
            COUNT(DISTINCT c.id_comuna) FILTER (
                WHERE c.activo = TRUE
            ) AS comunas_catalogo,
            COUNT(DISTINCT td.id_comuna) FILTER (
                WHERE td.activo = TRUE
            ) AS comunas_con_tarifa,
            COUNT(*) FILTER (
                WHERE td.activo = TRUE
            ) AS tarifas_activas
         FROM comunas c
         LEFT JOIN tarifas_despacho td
            ON td.id_comuna = c.id_comuna'
    );

    $summary = $statement->fetch();

    return is_array($summary)
        ? $summary
        : [
            'comunas_catalogo' => 0,
            'comunas_con_tarifa' => 0,
            'tarifas_activas' => 0,
        ];
}

function listarTarifasDespacho(PDO $connection, array $filters): array
{
    $where = ['c.activo = TRUE', 'r.activo = TRUE'];
    $parameters = [];

    if ($filters['id_region'] !== null) {
        $where[] = 'r.id_region = :id_region';
        $parameters['id_region'] = $filters['id_region'];
    }

    if ($filters['id_comuna'] !== null) {
        $where[] = 'c.id_comuna = :id_comuna';
        $parameters['id_comuna'] = $filters['id_comuna'];
    }

    if ($filters['buscar'] !== '') {
        $where[] = 'LOWER(c.nombre) LIKE LOWER(:buscar)';
        $parameters['buscar'] = '%' . $filters['buscar'] . '%';
    }

    $whereSql = implode(' AND ', $where);

    $countStatement = $connection->prepare(
        'SELECT COUNT(*)
         FROM comunas c
         INNER JOIN regiones r
            ON r.id_region = c.id_region
         WHERE ' . $whereSql
    );
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();

    $pages = max(1, (int) ceil($total / $filters['por_pagina']));
    $page = min($filters['pagina'], $pages);
    $offset = ($page - 1) * $filters['por_pagina'];

    $sql = 'SELECT
                c.id_comuna,
                c.nombre AS comuna,
                r.id_region,
                r.nombre AS region,
                MAX(td.valor) FILTER (
                    WHERE td.peso_maximo_gramos = 3000
                ) AS valor_s,
                MAX(td.valor) FILTER (
                    WHERE td.peso_maximo_gramos = 6000
                ) AS valor_m,
                MAX(td.valor) FILTER (
                    WHERE td.peso_maximo_gramos = 16000
                ) AS valor_l,
                MAX(td.valor) FILTER (
                    WHERE td.peso_maximo_gramos = 25000
                ) AS valor_xl,
                MAX(td.monto_envio_gratis) AS monto_envio_gratis,
                BOOL_AND(td.activo) FILTER (
                    WHERE td.peso_maximo_gramos IN (3000, 6000, 16000, 25000)
                ) AS tarifas_activas,
                COUNT(td.id_tarifa_despacho) FILTER (
                    WHERE td.peso_maximo_gramos IN (3000, 6000, 16000, 25000)
                ) AS tramos_configurados
            FROM comunas c
            INNER JOIN regiones r
                ON r.id_region = c.id_region
            LEFT JOIN tarifas_despacho td
                ON td.id_comuna = c.id_comuna
            WHERE ' . $whereSql . '
            GROUP BY
                c.id_comuna,
                c.nombre,
                r.id_region,
                r.nombre
            ORDER BY
                r.nombre,
                c.nombre
            LIMIT :limit
            OFFSET :offset';

    $statement = $connection->prepare($sql);

    if (isset($parameters['id_region'])) {
        $statement->bindValue(':id_region', $parameters['id_region'], PDO::PARAM_INT);
    }

    if (isset($parameters['id_comuna'])) {
        $statement->bindValue(':id_comuna', $parameters['id_comuna'], PDO::PARAM_INT);
    }

    if (isset($parameters['buscar'])) {
        $statement->bindValue(':buscar', $parameters['buscar'], PDO::PARAM_STR);
    }

    $statement->bindValue(':limit', $filters['por_pagina'], PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    $rows = $statement->fetchAll();

    return [
        'registros' => is_array($rows) ? $rows : [],
        'total' => $total,
        'pagina' => $page,
        'paginas' => $pages,
        'por_pagina' => $filters['por_pagina'],
    ];
}

function urlPaginacionTarifasDespacho(array $filters, int $page): string
{
    $query = $filters;
    $query['pagina'] = $page;

    foreach (['id_region', 'id_comuna'] as $key) {
        if ($query[$key] === null) {
            unset($query[$key]);
        }
    }

    if ($query['buscar'] === '') {
        unset($query['buscar']);
    }

    return appUrl('admin/despachos/tarifas/index.php?' . http_build_query($query));
}

function formatearValorTarifaDespacho(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    return (string) (int) $value;
}
