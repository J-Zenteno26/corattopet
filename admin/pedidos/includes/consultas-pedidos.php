<?php

declare(strict_types=1);

function obtenerResumenPedidos(PDO $connection): array
{
    $statement = $connection->query("SELECT
        COUNT(*) FILTER (WHERE estado = 'recibido') AS recibidos,
        COUNT(*) FILTER (WHERE estado = 'en_preparacion') AS en_preparacion,
        COUNT(*) FILTER (WHERE estado_pago = 'pendiente') AS pendientes_pago,
        COUNT(*) FILTER (WHERE estado = 'entregado') AS entregados,
        COUNT(*) FILTER (WHERE estado = 'cancelado') AS cancelados
        FROM pedidos");
    $summary = $statement->fetch();
    return is_array($summary) ? $summary : ['recibidos' => 0, 'en_preparacion' => 0, 'pendientes_pago' => 0, 'entregados' => 0, 'cancelados' => 0];
}

function listarPedidos(PDO $connection, array $filters): array
{
    [$where, $bindings] = construirFiltrosPedidos($filters);
    $count = $connection->prepare('SELECT COUNT(*) FROM pedidos p LEFT JOIN clientes c ON c.id_cliente = p.id_cliente' . $where);
    enlazarPedidos($count, $bindings);
    $count->execute();
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $filters['por_pagina']));
    $page = min($filters['pagina'], $pages);
    $offset = ($page - 1) * $filters['por_pagina'];
    $statement = $connection->prepare('SELECT p.id_pedido, p.codigo_pedido, p.estado, p.estado_pago, p.total,
        p.metodo_entrega, p.creado_en, c.nombre AS cliente_nombre, c.email AS cliente_email
        FROM pedidos p LEFT JOIN clientes c ON c.id_cliente = p.id_cliente' . $where . '
        ORDER BY p.creado_en DESC, p.id_pedido DESC LIMIT :limit OFFSET :offset');
    enlazarPedidos($statement, $bindings);
    $statement->bindValue(':limit', $filters['por_pagina'], PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return ['registros' => $statement->fetchAll(), 'total_registros' => $total, 'total_paginas' => $pages, 'pagina_actual' => $page, 'por_pagina' => $filters['por_pagina']];
}

function construirFiltrosPedidos(array $filters): array
{
    $conditions = []; $bindings = [];
    if ($filters['buscar'] !== '') {
        $conditions[] = '(p.codigo_pedido ILIKE :buscar OR c.nombre ILIKE :buscar OR c.email ILIKE :buscar)';
        $bindings['buscar'] = '%' . $filters['buscar'] . '%';
    }
    if ($filters['estado'] !== '') { $conditions[] = 'p.estado = :estado'; $bindings['estado'] = $filters['estado']; }
    if ($filters['estado_pago'] !== '') { $conditions[] = 'p.estado_pago = :estado_pago'; $bindings['estado_pago'] = $filters['estado_pago']; }
    if ($filters['fecha_desde'] !== '') { $conditions[] = 'p.creado_en >= CAST(:fecha_desde AS date)'; $bindings['fecha_desde'] = $filters['fecha_desde']; }
    if ($filters['fecha_hasta'] !== '') { $conditions[] = "p.creado_en < CAST(:fecha_hasta AS date) + INTERVAL '1 day'"; $bindings['fecha_hasta'] = $filters['fecha_hasta']; }
    return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $bindings];
}

function enlazarPedidos(PDOStatement $statement, array $bindings): void
{
    foreach ($bindings as $name => $value) { $statement->bindValue(':' . $name, $value, PDO::PARAM_STR); }
}

function obtenerPedido(PDO $connection, int $orderId, bool $forUpdate = false): ?array
{
    $statement = $connection->prepare('SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email,
        c.telefono AS cliente_telefono, c.rut AS cliente_rut, c.direccion AS cliente_direccion,
        c.comuna AS cliente_comuna, c.region AS cliente_region
        FROM pedidos p LEFT JOIN clientes c ON c.id_cliente = p.id_cliente
        WHERE p.id_pedido = :id_pedido' . ($forUpdate ? ' FOR UPDATE OF p' : ''));
    $statement->bindValue(':id_pedido', $orderId, PDO::PARAM_INT);
    $statement->execute();
    $order = $statement->fetch();
    return is_array($order) ? $order : null;
}

function obtenerDetallesPedido(PDO $connection, int $orderId): array
{
    $statement = $connection->prepare('SELECT d.*, pp.nombre AS presentacion_nombre
        FROM pedido_detalles d LEFT JOIN producto_presentaciones pp ON pp.id_presentacion = d.id_presentacion
        WHERE d.id_pedido = :id_pedido ORDER BY d.id_detalle');
    $statement->bindValue(':id_pedido', $orderId, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function obtenerHistorialPedido(PDO $connection, int $orderId): array
{
    $statement = $connection->prepare('SELECT h.*, u.nombre AS usuario_nombre
        FROM pedido_historial_estados h LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario
        WHERE h.id_pedido = :id_pedido ORDER BY h.creado_en DESC, h.id_historial DESC');
    $statement->bindValue(':id_pedido', $orderId, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}
