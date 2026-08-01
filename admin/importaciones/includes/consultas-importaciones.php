<?php

declare(strict_types=1);

function tablaImportacionesDisponible(PDO $connection): bool
{
    return filter_var(
        $connection->query("SELECT to_regclass('public.importaciones') IS NOT NULL")->fetchColumn(),
        FILTER_VALIDATE_BOOL
    );
}

function filtrosImportaciones(array $query): array
{
    $status = trim((string) ($query['estado'] ?? ''));
    $allowedStatuses = ['cargado', 'procesando', 'completado', 'error'];
    return [
        'q' => mb_substr(trim((string) ($query['q'] ?? '')), 0, 100),
        'estado' => in_array($status, $allowedStatuses, true) ? $status : '',
        'desde' => fechaFiltroImportacion((string) ($query['desde'] ?? '')),
        'hasta' => fechaFiltroImportacion((string) ($query['hasta'] ?? '')),
    ];
}

function fechaFiltroImportacion(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
}

function obtenerImportaciones(PDO $connection, array $filters): array
{
    $conditions = [];
    $parameters = [];
    if ($filters['q'] !== '') {
        $conditions[] = "(i.nombre_archivo ILIKE :busqueda OR 'Productos y presentaciones' ILIKE :busqueda)";
        $parameters['busqueda'] = '%' . $filters['q'] . '%';
    }
    if ($filters['estado'] !== '') {
        $conditions[] = 'i.estado = :estado';
        $parameters['estado'] = $filters['estado'];
    }
    if ($filters['desde'] !== '') {
        $conditions[] = 'i.creado_en >= CAST(:desde AS date)';
        $parameters['desde'] = $filters['desde'];
    }
    if ($filters['hasta'] !== '') {
        $conditions[] = "i.creado_en < CAST(:hasta AS date) + INTERVAL '1 day'";
        $parameters['hasta'] = $filters['hasta'];
    }
    $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    $statement = $connection->prepare(
        "SELECT i.id_importacion, i.id_usuario, i.nombre_archivo AS archivo,
                'Productos y presentaciones' AS tipo_importacion,
                i.total_filas AS registros_procesados,
                (i.productos_creados + i.productos_actualizados) AS registros_exitosos,
                i.filas_con_error AS errores, 0 AS advertencias, i.estado,
                i.resumen_errores AS resumen, i.creado_en, i.finalizado_en,
                COALESCE(u.nombre, 'Usuario no disponible') AS usuario
         FROM importaciones i
         LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario" . $where . '
         ORDER BY i.creado_en DESC, i.id_importacion DESC
         LIMIT 250'
    );
    $statement->execute($parameters);
    return $statement->fetchAll();
}

function resumenImportaciones(PDO $connection): array
{
    $row = $connection->query(
        "SELECT COUNT(*) AS total,
                MAX(creado_en) AS ultima,
                COALESCE(SUM(total_filas), 0) AS procesados,
                COALESCE(SUM(filas_con_error), 0) AS incidencias
         FROM importaciones"
    )->fetch();
    return is_array($row) ? $row : ['total' => 0, 'ultima' => null, 'procesados' => 0, 'incidencias' => 0];
}

function etiquetaEstadoImportacionHistorial(string $status): string
{
    return match ($status) {
        'cargado' => 'Cargado',
        'procesando' => 'Procesando',
        'completado' => 'Completado',
        'error' => 'Con errores',
        default => 'Sin estado',
    };
}
