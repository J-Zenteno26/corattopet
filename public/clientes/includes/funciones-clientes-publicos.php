<?php

declare(strict_types=1);

function rutaRetornoCliente(mixed $value, string $default = 'public/clientes/cuenta.php'): string
{
    if (!is_scalar($value)) {
        return $default;
    }

    $route = trim((string) $value);

    if (
        $route === ''
        || strlen($route) > 220
        || str_contains($route, "\r")
        || str_contains($route, "\n")
        || str_contains($route, '://')
        || str_starts_with($route, '//')
        || str_contains($route, '..')
        || !str_starts_with($route, 'public/')
    ) {
        return $default;
    }

    return $route;
}

function clientePublicoSesion(PDO $pdo): ?array
{
    $id = filter_var(
        $_SESSION['id_cliente'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT
            id_cliente,
            nombre,
            apellido,
            email,
            telefono,
            rut,
            direccion,
            comuna,
            region,
            password_hash,
            activo,
            email_verificado,
            creado_en,
            actualizado_en
         FROM clientes
         WHERE id_cliente = :id_cliente
           AND activo = TRUE
         LIMIT 1"
    );
    $statement->execute(['id_cliente' => (int) $id]);
    $cliente = $statement->fetch();

    return is_array($cliente) ? $cliente : null;
}

function exigirClientePublico(PDO $pdo, string $returnRoute): array
{
    $cliente = clientePublicoSesion($pdo);

    if ($cliente !== null) {
        return $cliente;
    }

    unset(
        $_SESSION['id_cliente'],
        $_SESSION['cliente_nombre'],
        $_SESSION['cliente_email']
    );

    header(
        'Location: ' . appUrl(
            'public/clientes/login.php?return=' . rawurlencode($returnRoute)
        ),
        true,
        302
    );
    exit;
}

function sincronizarSesionClientePublico(array $cliente): void
{
    $_SESSION['id_cliente'] = (int) $cliente['id_cliente'];
    $_SESSION['cliente_nombre'] = (string) $cliente['nombre'];
    $_SESSION['cliente_email'] = (string) $cliente['email'];
}

function resumenCuentaCliente(PDO $pdo, int $idCliente): array
{
    $statement = $pdo->prepare(
        "SELECT
            COUNT(*) AS pedidos,
            COUNT(*) FILTER (
                WHERE estado NOT IN ('entregado', 'cancelado')
            ) AS activos,
            COUNT(*) FILTER (
                WHERE estado = 'entregado'
            ) AS entregados,
            COALESCE(
                SUM(total) FILTER (WHERE estado_pago = 'pagado'),
                0
            ) AS total_pagado
         FROM pedidos
         WHERE id_cliente = :id_cliente"
    );
    $statement->execute(['id_cliente' => $idCliente]);
    $row = $statement->fetch();

    return is_array($row)
        ? $row
        : ['pedidos' => 0, 'activos' => 0, 'entregados' => 0, 'total_pagado' => 0];
}

function pedidosCuentaCliente(
    PDO $pdo,
    int $idCliente,
    ?int $limit = null
): array {
    $sql = "SELECT
                id_pedido,
                codigo_pedido,
                estado,
                estado_pago,
                subtotal,
                costo_despacho,
                total,
                metodo_entrega,
                creado_en
            FROM pedidos
            WHERE id_cliente = :id_cliente
            ORDER BY creado_en DESC, id_pedido DESC";

    if ($limit !== null) {
        $sql .= ' LIMIT :limit';
    }

    $statement = $pdo->prepare($sql);
    $statement->bindValue(':id_cliente', $idCliente, PDO::PARAM_INT);

    if ($limit !== null) {
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    }

    $statement->execute();

    return $statement->fetchAll();
}

function pedidoCuentaCliente(
    PDO $pdo,
    int $idCliente,
    int $idPedido
): ?array {
    $statement = $pdo->prepare(
        "SELECT
            p.id_pedido,
            p.codigo_pedido,
            p.estado,
            p.estado_pago,
            p.estado_stock,
            p.subtotal,
            p.descuento,
            p.costo_despacho,
            p.total,
            p.metodo_entrega,
            p.direccion_entrega,
            p.comuna_entrega,
            p.region_entrega,
            p.referencia_entrega,
            p.metodo_pago,
            p.observaciones_cliente,
            p.nombre_cliente,
            p.email_cliente,
            p.telefono_cliente,
            p.creado_en,
            p.actualizado_en
         FROM pedidos p
         WHERE p.id_pedido = :id_pedido
           AND p.id_cliente = :id_cliente
         LIMIT 1"
    );
    $statement->execute([
        'id_pedido' => $idPedido,
        'id_cliente' => $idCliente,
    ]);
    $pedido = $statement->fetch();

    return is_array($pedido) ? $pedido : null;
}

function detallesPedidoCuentaCliente(PDO $pdo, int $idPedido): array
{
    $statement = $pdo->prepare(
        "SELECT
            id_detalle,
            nombre_producto,
            sku,
            tipo_item,
            cantidad,
            cantidad_gramos,
            precio_unitario,
            subtotal
         FROM pedido_detalles
         WHERE id_pedido = :id_pedido
         ORDER BY id_detalle"
    );
    $statement->execute(['id_pedido' => $idPedido]);

    return $statement->fetchAll();
}

function fichasAlimentacionCliente(PDO $pdo, int $idCliente): array
{
    $statement = $pdo->prepare(
        "SELECT id_ficha, id_producto, snapshot, creado_en
         FROM fichas_alimentacion_clientes
         WHERE id_cliente = :id_cliente
         ORDER BY creado_en DESC, id_ficha DESC"
    );
    $statement->execute(['id_cliente' => $idCliente]);

    return array_map(static function (array $row): array {
        $snapshot = json_decode((string) ($row['snapshot'] ?? ''), true);
        $row['snapshot'] = is_array($snapshot) ? $snapshot : [];
        return $row;
    }, $statement->fetchAll());
}

function fichaAlimentacionCliente(PDO $pdo, int $idCliente, int $idFicha): ?array
{
    $statement = $pdo->prepare(
        "SELECT id_ficha, id_producto, snapshot, creado_en
         FROM fichas_alimentacion_clientes
         WHERE id_ficha = :id_ficha
           AND id_cliente = :id_cliente
         LIMIT 1"
    );
    $statement->execute(['id_ficha' => $idFicha, 'id_cliente' => $idCliente]);
    $row = $statement->fetch();

    if (!is_array($row)) {
        return null;
    }

    $snapshot = json_decode((string) ($row['snapshot'] ?? ''), true);
    $row['snapshot'] = is_array($snapshot) ? $snapshot : [];
    return $row;
}

function numeroFichaAlimentacion(mixed $value, float $min, float $max, string $field): float
{
    $number = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($number === false || !is_finite((float) $number) || $number < $min || $number > $max) {
        throw new InvalidArgumentException('La ficha contiene un valor inválido en ' . $field . '.');
    }
    return (float) $number;
}

function validarSnapshotFichaAlimentacion(array $payload, array $productos): array
{
    $profile = is_array($payload['profile'] ?? null) ? $payload['profile'] : [];
    $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
    $recommendation = is_array($payload['recommendation'] ?? null) ? $payload['recommendation'] : [];

    $allowed = [
        'species' => ['perro', 'gato'], 'sex' => ['female', 'male'],
        'ageUnit' => ['years', 'months'], 'bodyCondition' => ['thin', 'ideal', 'overweight', 'obese'],
        'activity' => ['low', 'normal', 'high'], 'breedType' => ['mixed', 'defined'],
        'health' => ['healthy', 'sensitive', 'medical', 'pregnancy'],
        'allergy' => ['none', 'pollo', 'pescado', 'cordero', 'vacuno', 'pavo', 'cerdo', 'grain'],
    ];
    foreach ($allowed as $field => $values) {
        if (!in_array($profile[$field] ?? null, $values, true)) {
            throw new InvalidArgumentException('La ficha contiene un perfil de mascota inválido.');
        }
    }

    $size = $profile['size'] ?? null;
    if ($profile['species'] === 'perro' && !in_array($size, ['small', 'medium', 'large', 'giant'], true)) {
        throw new InvalidArgumentException('Selecciona un tamaño válido para la mascota.');
    }
    if ($profile['species'] === 'gato') {
        $size = null;
    }

    $age = numeroFichaAlimentacion($profile['age'] ?? null, 0.1, 360, 'edad');
    $months = $profile['ageUnit'] === 'years' ? $age * 12 : $age;
    if ($months > 360) {
        throw new InvalidArgumentException('La edad de la mascota no es válida.');
    }
    $weight = numeroFichaAlimentacion($profile['weight'] ?? null, 0.2, 150, 'peso');
    $idealWeight = ($profile['idealWeight'] ?? null) === null || $profile['idealWeight'] === ''
        ? null
        : numeroFichaAlimentacion($profile['idealWeight'], 0.2, 150, 'peso ideal');

    if ($profile['species'] === 'gato') {
        $stage = $months < 12 ? 'kitten' : ($months >= 120 ? 'senior' : 'adult');
    } else {
        $adultAt = ['small' => 12, 'medium' => 15, 'large' => 18, 'giant' => 24][$size] ?? 15;
        $seniorAt = (['small' => 10, 'medium' => 9, 'large' => 8, 'giant' => 7][$size] ?? 9) * 12;
        $stage = $months < $adultAt ? 'puppy' : ($months >= $seniorAt ? 'senior' : 'adult');
    }

    if ($stage === 'puppy' || $stage === 'kitten') {
        $factor = $months < 4 ? 3.0 : 2.0;
    } else {
        $sterilized = filter_var($profile['sterilized'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($sterilized === null) {
            throw new InvalidArgumentException('La ficha contiene un dato de esterilización inválido.');
        }
        $factor = $profile['species'] === 'gato'
            ? ($stage === 'senior' ? 1.1 : ($sterilized ? 1.2 : 1.4))
            : ($stage === 'senior' ? 1.4 : ($sterilized ? 1.6 : 1.8));
        $factor *= ['low' => .85, 'normal' => 1.0, 'high' => 1.2][$profile['activity']];
        $factor *= ['thin' => 1.1, 'ideal' => 1.0, 'overweight' => .8, 'obese' => .65][$profile['bodyCondition']];
        if ($profile['health'] === 'pregnancy') {
            $factor *= 1.35;
        }
    }
    $calculationWeight = $idealWeight !== null && in_array($profile['bodyCondition'], ['overweight', 'obese'], true)
        ? $idealWeight
        : $weight;
    $kcalDay = (int) round(70 * ($calculationWeight ** .75) * $factor);
    $meals = in_array($stage, ['puppy', 'kitten'], true) ? ($months < 6 ? 4 : 3) : 2;

    $productId = filter_var($recommendation['productId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $product = null;
    foreach ($productos as $candidate) {
        if ((int) ($candidate['id'] ?? 0) === (int) $productId) {
            $product = $candidate;
            break;
        }
    }
    if ($product === null) {
        throw new InvalidArgumentException('El alimento seleccionado ya no está disponible para la calculadora.');
    }
    $productSpecies = strtolower((string) ($product['especie'] ?? ''));
    $productSpecies = $productSpecies === 'dog' ? 'perro' : ($productSpecies === 'cat' ? 'gato' : $productSpecies);
    $stages = is_array($product['etapas'] ?? null) ? array_map('strval', $product['etapas']) : [];
    $sizes = is_array($product['tamanos'] ?? null) ? array_map('strval', $product['tamanos']) : [];
    $proteins = is_array($product['proteinas'] ?? null) ? array_map('strval', $product['proteinas']) : [];
    if (
        !in_array($productSpecies, [$profile['species'], 'ambos'], true)
        || ($stages !== [] && !in_array($stage, $stages, true))
        || ($profile['species'] === 'perro' && $sizes !== [] && !in_array($size, $sizes, true))
        || (!in_array($profile['allergy'], ['none', 'grain'], true) && in_array($profile['allergy'], $proteins, true))
        || ($profile['allergy'] === 'grain' && empty($product['grainFree']))
    ) {
        throw new InvalidArgumentException('El alimento no es compatible con el perfil calculado.');
    }
    $kcalKg = numeroFichaAlimentacion($product['kcalKg'] ?? null, 1, 20000, 'energía metabolizable');
    $gramsDay = (int) round($kcalDay * 1000 / $kcalKg);
    $gramsMeal = (int) round($gramsDay / $meals);

    if (
        (int) ($result['kcalDay'] ?? 0) !== $kcalDay
        || (string) ($result['stage'] ?? '') !== $stage
        || (int) ($result['meals'] ?? 0) !== $meals
        || abs((float) ($recommendation['kcalKg'] ?? 0) - $kcalKg) > .01
        || (int) ($recommendation['gramsDay'] ?? 0) !== $gramsDay
        || (int) ($recommendation['gramsMeal'] ?? 0) !== $gramsMeal
    ) {
        throw new InvalidArgumentException('La recomendación no coincide con los datos calculados. Vuelve a calcularla.');
    }

    $text = static fn(mixed $value, int $max): string => mb_substr(trim((string) $value), 0, $max);
    return [
        'version' => 1,
        'profile' => [
            'petName' => $text($profile['petName'] ?? '', 60), 'species' => $profile['species'],
            'sex' => $profile['sex'], 'age' => $age, 'ageUnit' => $profile['ageUnit'],
            'weight' => $weight, 'idealWeight' => $idealWeight, 'size' => $size,
            'bodyCondition' => $profile['bodyCondition'], 'activity' => $profile['activity'],
            'sterilized' => (bool) $profile['sterilized'], 'breedType' => $profile['breedType'],
            'breed' => $text($profile['breed'] ?? '', 80), 'health' => $profile['health'],
            'allergy' => $profile['allergy'],
        ],
        'result' => ['stage' => $stage, 'kcalDay' => $kcalDay, 'meals' => $meals],
        'food' => [
            'productId' => (int) $product['id'], 'sku' => (string) $product['sku'],
            'name' => (string) $product['nombre'], 'brand' => (string) $product['marca'],
            'kcalKg' => $kcalKg, 'gramsDay' => $gramsDay, 'gramsMeal' => $gramsMeal,
        ],
        'calculatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
    ];
}

function estadoPedidoCliente(string $estado): string
{
    return match ($estado) {
        'recibido' => 'Recibido',
        'en_preparacion' => 'En preparación',
        'listo_para_retiro' => 'Listo para retiro',
        'enviado' => 'Enviado',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function estadoPagoCliente(string $estado): string
{
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'pagado' => 'Pagado',
        'rechazado' => 'Rechazado',
        'reembolsado' => 'Reembolsado',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function claseEstadoCliente(string $estado): string
{
    return preg_replace('/[^a-z0-9-]/', '', str_replace('_', '-', strtolower($estado))) ?: 'neutral';
}

function dineroCliente(mixed $value): string
{
    return '$' . number_format((float) $value, 0, ',', '.');
}

function fechaCliente(mixed $value, string $format = 'd-m-Y H:i'): string
{
    if (!is_scalar($value) || trim((string) $value) === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable((string) $value))->format($format);
    } catch (Throwable) {
        return '—';
    }
}
