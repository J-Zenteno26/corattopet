<?php

declare(strict_types=1);

function filtrosSqlClientes(array $filters): array
{
    $where = []; $bindings = [];
    foreach (['nombre', 'email', 'telefono', 'comuna', 'region'] as $field) {
        if ($filters[$field] !== '') { $where[] = "c.{$field} ILIKE :{$field}"; $bindings[$field] = '%' . $filters[$field] . '%'; }
    }
    return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $bindings];
}

function enlazarClientes(PDOStatement $statement, array $bindings): void
{
    foreach ($bindings as $name => $value) { $statement->bindValue(':' . $name, $value, PDO::PARAM_STR); }
}

function contarClientes(PDO $pdo, array $filters): int
{
    [$where, $bindings] = filtrosSqlClientes($filters);
    $statement = $pdo->prepare('SELECT COUNT(*) FROM clientes c' . $where); enlazarClientes($statement, $bindings); $statement->execute();
    return (int) $statement->fetchColumn();
}

function obtenerClientes(PDO $pdo, array $filters, int $page, int $perPage): array
{
    [$where, $bindings] = filtrosSqlClientes($filters); $offset = ($page - 1) * $perPage;
    $statement = $pdo->prepare('SELECT c.*, COUNT(p.id_pedido) AS cantidad_pedidos,
        COALESCE(SUM(p.total), 0) AS total_comprado, MAX(p.creado_en) AS ultima_compra
        FROM clientes c LEFT JOIN pedidos p ON p.id_cliente = c.id_cliente' . $where . '
        GROUP BY c.id_cliente ORDER BY c.creado_en DESC, c.id_cliente DESC LIMIT :limit OFFSET :offset');
    enlazarClientes($statement, $bindings); $statement->bindValue(':limit', $perPage, PDO::PARAM_INT); $statement->bindValue(':offset', $offset, PDO::PARAM_INT); $statement->execute();
    return $statement->fetchAll();
}

function obtenerResumenClientes(PDO $pdo, array $filters = []): array
{
    [$where, $bindings] = filtrosSqlClientes(array_merge(['nombre'=>'','email'=>'','telefono'=>'','comuna'=>'','region'=>''], $filters));
    $statement = $pdo->prepare('SELECT COUNT(DISTINCT c.id_cliente) AS registrados,
        COUNT(DISTINCT c.id_cliente) FILTER (WHERE p.id_pedido IS NOT NULL) AS con_pedidos,
        COUNT(DISTINCT c.id_cliente) FILTER (WHERE c.creado_en >= date_trunc(\'month\', CURRENT_DATE)) AS nuevos_mes,
        COUNT(p.id_pedido) AS pedidos_asociados, COALESCE(SUM(p.total), 0) AS total_vendido
        FROM clientes c LEFT JOIN pedidos p ON p.id_cliente = c.id_cliente' . $where);
    enlazarClientes($statement, $bindings); $statement->execute(); $row = $statement->fetch();
    return is_array($row) ? $row : ['registrados'=>0,'con_pedidos'=>0,'nuevos_mes'=>0,'pedidos_asociados'=>0,'total_vendido'=>0];
}

function obtenerClientePorId(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM clientes WHERE id_cliente = :id'); $statement->bindValue(':id', $id, PDO::PARAM_INT); $statement->execute(); $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function obtenerResumenCliente(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare("SELECT COUNT(*) AS cantidad_pedidos, COALESCE(SUM(total),0) AS total_comprado,
        COALESCE(AVG(total),0)::integer AS ticket_promedio,
        COUNT(*) FILTER (WHERE estado NOT IN ('entregado','cancelado')) AS pedidos_pendientes,
        COUNT(*) FILTER (WHERE estado_pago = 'pagado') AS pedidos_pagados, MAX(creado_en) AS ultima_compra
        FROM pedidos WHERE id_cliente = :id"); $statement->bindValue(':id', $id, PDO::PARAM_INT); $statement->execute();
    return $statement->fetch() ?: [];
}

function obtenerPedidosCliente(PDO $pdo, int $id, ?int $limit = null): array
{
    $sql = 'SELECT id_pedido, codigo_pedido, creado_en, estado, estado_pago, total FROM pedidos WHERE id_cliente = :id ORDER BY creado_en DESC, id_pedido DESC';
    if ($limit !== null) { $sql .= ' LIMIT :limit'; }
    $statement = $pdo->prepare($sql); $statement->bindValue(':id', $id, PDO::PARAM_INT); if ($limit !== null) { $statement->bindValue(':limit', $limit, PDO::PARAM_INT); } $statement->execute();
    return $statement->fetchAll();
}

function actualizarCliente(PDO $pdo, int $id, array $data): void
{
    $statement = $pdo->prepare('UPDATE clientes SET nombre=:nombre,email=:email,telefono=:telefono,rut=:rut,direccion=:direccion,comuna=:comuna,region=:region,actualizado_en=CURRENT_TIMESTAMP WHERE id_cliente=:id');
    foreach (array_keys(inicialCliente()) as $field) { $statement->bindValue(':' . $field, $data[$field] === '' ? null : $data[$field], $data[$field] === '' ? PDO::PARAM_NULL : PDO::PARAM_STR); }
    $statement->bindValue(':id', $id, PDO::PARAM_INT); $statement->execute();
}
