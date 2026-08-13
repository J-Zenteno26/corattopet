<?php

declare(strict_types=1);

const CHECKOUT_MARGEN_PESO = 0.10;

function obtenerRegionesCheckout(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id_region, nombre, abreviatura
         FROM regiones
         WHERE activo = TRUE
         ORDER BY id_region"
    )->fetchAll();
}

function obtenerComunasCheckout(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id_comuna, id_region, nombre
         FROM comunas
         WHERE activo = TRUE
         ORDER BY nombre"
    )->fetchAll();
}

function obtenerPesoEstimadoProductoCheckout(PDO $pdo, int $idProducto): ?int
{
    static $cache = [];

    if (array_key_exists($idProducto, $cache)) {
        return $cache[$idProducto];
    }

    $statement = $pdo->prepare(
        "SELECT cd.peso_estimado_gramos
         FROM productos_categorias_despacho pcd
         INNER JOIN categorias_despacho cd
            ON cd.id_categoria_despacho = pcd.id_categoria_despacho
           AND cd.activo = TRUE
         WHERE pcd.id_producto = :id_producto
         LIMIT 1"
    );
    $statement->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
    $statement->execute();

    $peso = $statement->fetchColumn();
    $cache[$idProducto] = $peso === false ? null : max(0, (int) $peso);

    return $cache[$idProducto];
}

function obtenerResumenCheckout(PDO $pdo, bool $validarDespacho = true): array
{
    $items = [];
    $subtotal = 0;
    $pesoTotalGramos = 0;
    $errores = [];

    foreach (obtenerCarritoSesion() as $clave => $linea) {
        $idProducto = (int) ($linea['id_producto'] ?? 0);
        $idPresentacion = isset($linea['id_presentacion']) && $linea['id_presentacion'] !== null
            ? (int) $linea['id_presentacion']
            : null;
        $cantidad = max(1, (int) ($linea['cantidad'] ?? 1));

        $item = obtenerItemCarritoPublico($pdo, $idProducto, $idPresentacion);

        if ($item === null) {
            $errores[] = 'Uno de los productos ya no está disponible.';
            continue;
        }

        if (
            $idPresentacion === null
            && (int) ($item['cantidad_presentaciones_activas'] ?? 0) > 0
        ) {
            $errores[] = 'Uno de los productos requiere elegir una presentación.';
            continue;
        }

        $precioUnitario = max(0, (int) ($item['precio_venta'] ?? 0));
        $cantidadDisponible = max(0, (int) ($item['cantidad_disponible'] ?? 0));

        if (!(bool) ($item['disponible'] ?? false)) {
            $errores[] = 'Uno de los productos se encuentra sin stock.';
        } elseif ($cantidad > $cantidadDisponible) {
            $errores[] = 'La cantidad solicitada supera el stock disponible.';
        }

        if ($idPresentacion !== null && (int) ($item['cantidad_gramos'] ?? 0) > 0) {
            $pesoUnitario = (int) $item['cantidad_gramos'];
        } else {
            $pesoUnitario = obtenerPesoEstimadoProductoCheckout($pdo, $idProducto);

            if ($pesoUnitario === null || $pesoUnitario <= 0) {
                if ($validarDespacho) {
                    $errores[] = sprintf(
                        'El producto "%s" todavía no tiene una categoría de despacho.',
                        (string) ($item['nombre_producto'] ?? 'Producto')
                    );
                }

                $pesoUnitario = 0;
            }
        }

        $subtotalItem = $precioUnitario * $cantidad;
        $pesoItem = $pesoUnitario * $cantidad;

        $items[] = [
            'clave' => $clave,
            'id_producto' => $idProducto,
            'id_presentacion' => $idPresentacion,
            'nombre' => (string) ($item['nombre_producto'] ?? ''),
            'nombre_item' => (string) ($item['nombre_item'] ?? ''),
            'sku' => (string) ($item['sku'] ?? ''),
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => $subtotalItem,
            'peso_unitario_gramos' => $pesoUnitario,
            'peso_total_gramos' => $pesoItem,
        ];

        $subtotal += $subtotalItem;
        $pesoTotalGramos += $pesoItem;
    }

    if ($items === []) {
        $errores[] = 'Tu carrito se encuentra vacío.';
    }
    $pesoTarifableGramos = (int) ceil(
        $pesoTotalGramos * (1 + CHECKOUT_MARGEN_PESO)
    );

    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'peso_total_gramos' => $pesoTotalGramos,
        'peso_tarifable_gramos' => $pesoTarifableGramos,
        'errores' => array_values(array_unique($errores)),
        'valido' => $errores === [],
    ];
}

function obtenerTarifaDespachoCheckout(
    PDO $pdo,
    int $idComuna,
    int $pesoTarifableGramos
): ?array {
    if ($idComuna <= 0 || $pesoTarifableGramos <= 0) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT
            td.id_tarifa_despacho,
            td.valor,
            td.peso_maximo_gramos,
            (
                SELECT MAX(td2.monto_envio_gratis)
                FROM tarifas_despacho td2
                WHERE td2.id_comuna = td.id_comuna
            ) AS monto_envio_gratis,
            c.id_comuna,
            c.nombre AS comuna,
            r.id_region,
            r.nombre AS region
         FROM tarifas_despacho td
         INNER JOIN comunas c
            ON c.id_comuna = td.id_comuna
           AND c.activo = TRUE
         INNER JOIN regiones r
            ON r.id_region = c.id_region
           AND r.activo = TRUE
         WHERE td.id_comuna = :id_comuna
           AND td.activo = TRUE
           AND td.peso_maximo_gramos >= :peso_tarifable
         ORDER BY td.peso_maximo_gramos ASC
         LIMIT 1"
    );

    $statement->bindValue(':id_comuna', $idComuna, PDO::PARAM_INT);
    $statement->bindValue(':peso_tarifable', $pesoTarifableGramos, PDO::PARAM_INT);
    $statement->execute();

    $tarifa = $statement->fetch();

    return is_array($tarifa) ? $tarifa : null;
}

function formatearDineroCheckout(int|float $valor): string
{
    return '$' . number_format((float) $valor, 0, ',', '.');
}


function calcularCostoDespachoCheckout(array $tarifa, int $subtotal): array
{
    $costoNormal = max(0, (int) ($tarifa['valor'] ?? 0));
    $montoEnvioGratis = isset($tarifa['monto_envio_gratis'])
        && $tarifa['monto_envio_gratis'] !== null
        && $tarifa['monto_envio_gratis'] !== ''
        ? max(0, (int) $tarifa['monto_envio_gratis'])
        : null;

    $aplicaEnvioGratis = $montoEnvioGratis !== null
        && $subtotal >= $montoEnvioGratis;

    $faltanteEnvioGratis = $montoEnvioGratis !== null
        ? max(0, $montoEnvioGratis - $subtotal)
        : null;

    return [
        'costo_normal' => $costoNormal,
        'costo_despacho' => $aplicaEnvioGratis ? 0 : $costoNormal,
        'monto_envio_gratis' => $montoEnvioGratis,
        'aplica_envio_gratis' => $aplicaEnvioGratis,
        'faltante_envio_gratis' => $faltanteEnvioGratis,
    ];
}

/**
 * Normaliza un texto recibido desde checkout.
 */
function normalizarTextoCheckout(
    mixed $valor,
    int $maximo,
    bool $obligatorio = false
): string {
    if (!is_scalar($valor)) {
        $valor = '';
    }

    $texto = trim(
        preg_replace('/\s+/u', ' ', (string) $valor) ?? ''
    );

    $texto = mb_substr($texto, 0, $maximo);

    if ($obligatorio && $texto === '') {
        throw new InvalidArgumentException(
            'Completa todos los datos obligatorios.'
        );
    }

    return $texto;
}

/**
 * Interpreta valores booleanos provenientes de PostgreSQL/configuración.
 */
function valorBooleanoCheckout(mixed $valor): bool
{
    return in_array($valor, [true, 1, '1', 't', 'true'], true);
}


function obtenerMontoMinimoCheckout(
    array $config,
    string $metodoEntrega
): int {
    if ($metodoEntrega === 'retiro_en_tienda') {
        return max(
            0,
            (int) ($config['monto_minimo_retiro'] ?? 4000)
        );
    }

    return max(
        0,
        (int) ($config['monto_minimo_despacho'] ?? 12000)
    );
}

function validarMontoMinimoCheckout(
    int $subtotal,
    array $config,
    string $metodoEntrega
): void {
    $minimo = obtenerMontoMinimoCheckout(
        $config,
        $metodoEntrega
    );

    if ($subtotal >= $minimo) {
        return;
    }

    $faltante = $minimo - $subtotal;

    throw new InvalidArgumentException(
        sprintf(
            'La compra mínima para %s es de %s. Te faltan %s.',
            $metodoEntrega === 'retiro_en_tienda'
                ? 'retiro en tienda'
                : 'despacho',
            formatearDineroCheckout($minimo),
            formatearDineroCheckout($faltante)
        )
    );
}


/**
 * Devuelve las modalidades de entrega habilitadas en la configuración.
 *
 * @return array<string, string>
 */
function modalidadesEntregaCheckout(array $config): array
{
    $modalidades = [];

    if (valorBooleanoCheckout($config['permite_despacho'] ?? false)) {
        $modalidades['despacho'] = 'Despacho a domicilio';
    }

    if (valorBooleanoCheckout($config['permite_retiro'] ?? false)) {
        $modalidades['retiro_en_tienda'] = 'Retiro en tienda';
    }

    return $modalidades;
}

/**
 * Valida que la modalidad enviada siga habilitada en la tienda.
 */
function validarModalidadEntregaCheckout(mixed $valor, array $config): string
{
    $metodo = is_scalar($valor) ? trim((string) $valor) : '';
    $disponibles = modalidadesEntregaCheckout($config);

    if ($metodo === '' || !array_key_exists($metodo, $disponibles)) {
        throw new InvalidArgumentException(
            'Selecciona una modalidad de entrega disponible.'
        );
    }

    return $metodo;
}

/**
 * Valida y normaliza los datos personales y de entrega.
 */
function validarDatosClienteCheckout(
    array $datos,
    string $metodoEntrega = 'despacho'
): array
{
    $nombre = normalizarTextoCheckout(
        $datos['nombre'] ?? '',
        120,
        true
    );

    $email = normalizarTextoCheckout(
        $datos['email'] ?? '',
        160,
        true
    );
    $email = mb_strtolower($email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(
            'Ingresa un correo electrónico válido.'
        );
    }

    $telefono = normalizarTextoCheckout(
        $datos['telefono'] ?? '',
        30,
        true
    );

    if (!preg_match('/^[0-9+\s().-]{8,30}$/', $telefono)) {
        throw new InvalidArgumentException(
            'Ingresa un teléfono válido.'
        );
    }

    $observaciones = normalizarTextoCheckout(
        $datos['observaciones'] ?? '',
        500
    );

    $direccion = '';
    $referencia = '';
    $idComuna = null;

    if ($metodoEntrega === 'despacho') {
        $direccion = normalizarTextoCheckout(
            $datos['direccion'] ?? '',
            180,
            true
        );

        $referencia = normalizarTextoCheckout(
            $datos['referencia'] ?? '',
            180
        );

        $idComunaValidada = filter_var(
            $datos['id_comuna'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idComunaValidada === false) {
            throw new InvalidArgumentException(
                'Selecciona una comuna válida.'
            );
        }

        $idComuna = (int) $idComunaValidada;
    }

    return [
        'nombre' => $nombre,
        'email' => $email,
        'telefono' => $telefono,
        'direccion' => $direccion,
        'referencia' => $referencia,
        'observaciones' => $observaciones,
        'id_comuna' => $idComuna,
    ];
}

/**
 * Genera un código público breve y único para el pedido.
 */
function generarCodigoPedidoCheckout(PDO $pdo): string
{
    for ($intento = 0; $intento < 8; $intento++) {
        $codigo = sprintf(
            'COR-%s-%s',
            date('Ymd'),
            strtoupper(bin2hex(random_bytes(3)))
        );

        $statement = $pdo->prepare(
            'SELECT 1
             FROM pedidos
             WHERE codigo_pedido = :codigo
             LIMIT 1'
        );
        $statement->execute(['codigo' => $codigo]);

        if ($statement->fetchColumn() === false) {
            return $codigo;
        }
    }

    throw new RuntimeException(
        'No fue posible generar el código del pedido.'
    );
}

/**
 * Busca un cliente existente por correo o crea uno para compra invitada.
 *
 * No modifica el perfil de una cuenta existente.
 */
function obtenerOCrearClienteCheckout(
    PDO $pdo,
    array $datosCliente,
    string $comuna,
    string $region,
    ?int $idClienteSesion = null
): int {
    if ($idClienteSesion !== null && $idClienteSesion > 0) {
        $statement = $pdo->prepare(
            "SELECT id_cliente
             FROM clientes
             WHERE id_cliente = :id_cliente
               AND activo = TRUE
             LIMIT 1"
        );
        $statement->execute(['id_cliente' => $idClienteSesion]);
        $idCliente = $statement->fetchColumn();

        if ($idCliente !== false) {
            return (int) $idCliente;
        }
    }
    $statement = $pdo->prepare(
        "SELECT id_cliente
         FROM clientes
         WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
         ORDER BY id_cliente ASC
         LIMIT 1"
    );
    $statement->execute(['email' => $datosCliente['email']]);

    $idCliente = $statement->fetchColumn();

    if ($idCliente !== false) {
        return (int) $idCliente;
    }

    $statement = $pdo->prepare(
        "INSERT INTO clientes (
            nombre,
            email,
            telefono,
            direccion,
            comuna,
            region
         ) VALUES (
            :nombre,
            :email,
            :telefono,
            :direccion,
            :comuna,
            :region
         )
         RETURNING id_cliente"
    );

    $statement->execute([
        'nombre' => $datosCliente['nombre'],
        'email' => $datosCliente['email'],
        'telefono' => $datosCliente['telefono'],
        'direccion' => $datosCliente['direccion'],
        'comuna' => $comuna,
        'region' => $region,
    ]);

    return (int) $statement->fetchColumn();
}

/**
 * Reserva el stock asociado a un pedido recién creado.
 *
 * - Productos normales: reserva unidades.
 * - Alimento seco con presentación: reserva gramos.
 *
 * Debe ejecutarse dentro de la misma transacción que crea el pedido.
 */
function reservarStockPedidoCheckout(PDO $pdo, int $idPedido): void
{
    if ($idPedido <= 0) {
        throw new InvalidArgumentException('El pedido no es válido para reservar stock.');
    }

    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'La reserva de stock debe ejecutarse dentro de una transacción.'
        );
    }

    $pedidoStatement = $pdo->prepare(
        "SELECT estado_stock
         FROM pedidos
         WHERE id_pedido = :id_pedido
         FOR UPDATE"
    );
    $pedidoStatement->execute(['id_pedido' => $idPedido]);

    $estadoStock = $pedidoStatement->fetchColumn();

    if ($estadoStock === false) {
        throw new RuntimeException('No fue posible encontrar el pedido para reservar stock.');
    }

    if ($estadoStock === 'reservado') {
        return;
    }

    if (!in_array($estadoStock, ['sin_reserva', 'liberado'], true)) {
        throw new RuntimeException(
            'El pedido no se encuentra en un estado válido para reservar stock.'
        );
    }

    $detallesStatement = $pdo->prepare(
        "SELECT
            d.id_producto,
            p.nombre AS nombre_producto,
            CASE
                WHEN COALESCE(p.detalles_opcionales->>'subcategoria_codigo', '') = 'alimento-seco'
                  OR UPPER(COALESCE(p.detalles_opcionales->>'subcategoria', '')) = 'ALIMENTO SECO'
                THEN TRUE
                ELSE FALSE
            END AS es_alimento_seco,
            SUM(
                CASE
                    WHEN COALESCE(p.detalles_opcionales->>'subcategoria_codigo', '') = 'alimento-seco'
                      OR UPPER(COALESCE(p.detalles_opcionales->>'subcategoria', '')) = 'ALIMENTO SECO'
                    THEN d.cantidad * COALESCE(pp.cantidad_gramos, d.cantidad_gramos, 0)
                    ELSE d.cantidad
                END
            ) AS cantidad_reservar
         FROM pedido_detalles d
         INNER JOIN productos p
            ON p.id_producto = d.id_producto
         LEFT JOIN producto_presentaciones pp
            ON pp.id_presentacion = d.id_presentacion
           AND pp.id_producto = d.id_producto
         WHERE d.id_pedido = :id_pedido
         GROUP BY
            d.id_producto,
            p.nombre,
            CASE
                WHEN COALESCE(p.detalles_opcionales->>'subcategoria_codigo', '') = 'alimento-seco'
                  OR UPPER(COALESCE(p.detalles_opcionales->>'subcategoria', '')) = 'ALIMENTO SECO'
                THEN TRUE
                ELSE FALSE
            END
         ORDER BY d.id_producto ASC"
    );
    $detallesStatement->execute(['id_pedido' => $idPedido]);
    $reservas = $detallesStatement->fetchAll();

    if ($reservas === []) {
        throw new RuntimeException('El pedido no contiene productos para reservar.');
    }

    $stockStatement = $pdo->prepare(
        "SELECT cantidad_actual, cantidad_reservada
         FROM stock
         WHERE id_producto = :id_producto
         FOR UPDATE"
    );

    $lotesVigentesStatement = $pdo->prepare(
        "SELECT COALESCE(SUM(cantidad_disponible_g), 0)
         FROM stock_lotes
         WHERE id_producto = :id_producto
           AND activo = TRUE
           AND fecha_vencimiento >= CURRENT_DATE"
    );

    $actualizarStockStatement = $pdo->prepare(
        "UPDATE stock
         SET cantidad_actual = :cantidad_actual,
             cantidad_reservada = :cantidad_reservada,
             actualizado_en = CURRENT_TIMESTAMP
         WHERE id_producto = :id_producto"
    );

    foreach ($reservas as $reserva) {
        $idProducto = (int) $reserva['id_producto'];
        $cantidadReservar = (int) $reserva['cantidad_reservar'];
        $esAlimentoSeco = filter_var(
            $reserva['es_alimento_seco'],
            FILTER_VALIDATE_BOOL
        );

        if ($cantidadReservar <= 0) {
            throw new RuntimeException(
                sprintf(
                    'No fue posible determinar la cantidad de stock a reservar para "%s".',
                    (string) $reserva['nombre_producto']
                )
            );
        }

        $stockStatement->execute(['id_producto' => $idProducto]);
        $stock = $stockStatement->fetch();

        if (!is_array($stock)) {
            throw new RuntimeException(
                sprintf(
                    'El producto "%s" no tiene un registro de stock disponible.',
                    (string) $reserva['nombre_producto']
                )
            );
        }

        $cantidadActual = (int) floor((float) $stock['cantidad_actual']);
        $cantidadReservada = (int) floor((float) $stock['cantidad_reservada']);

        if ($esAlimentoSeco) {
            $lotesVigentesStatement->execute(['id_producto' => $idProducto]);
            $cantidadActual = (int) floor(
                (float) $lotesVigentesStatement->fetchColumn()
            );
        }

        $cantidadDisponible = max(0, $cantidadActual - $cantidadReservada);

        if ($cantidadReservar > $cantidadDisponible) {
            $unidad = $esAlimentoSeco ? ' g' : ' unidad(es)';

            throw new RuntimeException(
                sprintf(
                    'Stock insuficiente para "%s". Disponible: %s%s.',
                    (string) $reserva['nombre_producto'],
                    number_format($cantidadDisponible, 0, ',', '.'),
                    $unidad
                )
            );
        }

        $actualizarStockStatement->execute([
            'cantidad_actual' => $cantidadActual,
            'cantidad_reservada' => $cantidadReservada + $cantidadReservar,
            'id_producto' => $idProducto,
        ]);
    }

    $actualizarPedido = $pdo->prepare(
        "UPDATE pedidos
         SET estado_stock = 'reservado',
             stock_reservado_en = CURRENT_TIMESTAMP,
             actualizado_en = CURRENT_TIMESTAMP
         WHERE id_pedido = :id_pedido
           AND estado_stock IN ('sin_reserva', 'liberado')"
    );
    $actualizarPedido->execute(['id_pedido' => $idPedido]);

    if ($actualizarPedido->rowCount() !== 1) {
        throw new RuntimeException('No fue posible confirmar la reserva de stock del pedido.');
    }
}

/**
 * Libera el stock reservado por un pedido cuyo pago no fue completado.
 *
 * - Productos normales: libera unidades reservadas.
 * - Alimento seco con presentación: libera gramos reservados.
 * - No modifica el stock físico disponible.
 *
 * Debe ejecutarse dentro de una transacción.
 * Es idempotente: si el pedido ya está liberado o no tiene reserva,
 * no vuelve a modificar el stock.
 */
function liberarStockReservadoPedidoCheckout(
    PDO $pdo,
    int $idPedido
): void {
    if ($idPedido <= 0) {
        throw new InvalidArgumentException(
            'El pedido no es válido para liberar stock.'
        );
    }

    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'La liberación de stock debe ejecutarse dentro de una transacción.'
        );
    }

    $pedidoStatement = $pdo->prepare(
        "SELECT estado_stock
         FROM pedidos
         WHERE id_pedido = :id_pedido
         FOR UPDATE"
    );

    $pedidoStatement->execute([
        'id_pedido' => $idPedido,
    ]);

    $estadoStock = $pedidoStatement->fetchColumn();

    if ($estadoStock === false) {
        throw new RuntimeException(
            'No fue posible encontrar el pedido para liberar su stock.'
        );
    }

    if (in_array($estadoStock, ['sin_reserva', 'liberado'], true)) {
        return;
    }

    if ($estadoStock === 'consumido') {
        return;
    }

    if ($estadoStock !== 'reservado') {
        throw new RuntimeException(
            'El pedido no tiene una reserva de stock válida para liberar.'
        );
    }

    $detallesStatement = $pdo->prepare(
        "SELECT
            d.id_producto,
            p.nombre AS nombre_producto,
            CASE
                WHEN COALESCE(
                    p.detalles_opcionales->>'subcategoria_codigo',
                    ''
                ) = 'alimento-seco'
                  OR UPPER(
                    COALESCE(
                        p.detalles_opcionales->>'subcategoria',
                        ''
                    )
                ) = 'ALIMENTO SECO'
                THEN TRUE
                ELSE FALSE
            END AS es_alimento_seco,
            SUM(
                CASE
                    WHEN COALESCE(
                        p.detalles_opcionales->>'subcategoria_codigo',
                        ''
                    ) = 'alimento-seco'
                      OR UPPER(
                        COALESCE(
                            p.detalles_opcionales->>'subcategoria',
                            ''
                        )
                    ) = 'ALIMENTO SECO'
                    THEN
                        d.cantidad
                        * COALESCE(
                            pp.cantidad_gramos,
                            d.cantidad_gramos,
                            0
                        )
                    ELSE d.cantidad
                END
            ) AS cantidad_liberar
         FROM pedido_detalles d
         INNER JOIN productos p
            ON p.id_producto = d.id_producto
         LEFT JOIN producto_presentaciones pp
            ON pp.id_presentacion = d.id_presentacion
           AND pp.id_producto = d.id_producto
         WHERE d.id_pedido = :id_pedido
         GROUP BY
            d.id_producto,
            p.nombre,
            CASE
                WHEN COALESCE(
                    p.detalles_opcionales->>'subcategoria_codigo',
                    ''
                ) = 'alimento-seco'
                  OR UPPER(
                    COALESCE(
                        p.detalles_opcionales->>'subcategoria',
                        ''
                    )
                ) = 'ALIMENTO SECO'
                THEN TRUE
                ELSE FALSE
            END
         ORDER BY d.id_producto ASC"
    );

    $detallesStatement->execute([
        'id_pedido' => $idPedido,
    ]);

    $reservas = $detallesStatement->fetchAll();

    if ($reservas === []) {
        throw new RuntimeException(
            'El pedido no contiene productos cuya reserva pueda liberarse.'
        );
    }

    $stockStatement = $pdo->prepare(
        "SELECT cantidad_actual, cantidad_reservada
         FROM stock
         WHERE id_producto = :id_producto
         FOR UPDATE"
    );

    $actualizarStock = $pdo->prepare(
        "UPDATE stock
         SET
            cantidad_reservada = :cantidad_reservada,
            actualizado_en = CURRENT_TIMESTAMP
         WHERE id_producto = :id_producto"
    );

    foreach ($reservas as $reserva) {
        $idProducto = (int) $reserva['id_producto'];
        $cantidadLiberar = (int) $reserva['cantidad_liberar'];

        if ($cantidadLiberar <= 0) {
            throw new RuntimeException(
                sprintf(
                    'No fue posible determinar la reserva de "%s".',
                    (string) $reserva['nombre_producto']
                )
            );
        }

        $stockStatement->execute([
            'id_producto' => $idProducto,
        ]);

        $stock = $stockStatement->fetch();

        if (!is_array($stock)) {
            throw new RuntimeException(
                sprintf(
                    'El producto "%s" no tiene un registro de stock.',
                    (string) $reserva['nombre_producto']
                )
            );
        }

        $cantidadReservada = (int) floor(
            (float) $stock['cantidad_reservada']
        );

        $reservaFinal = max(
            0,
            $cantidadReservada - $cantidadLiberar
        );

        $actualizarStock->execute([
            'cantidad_reservada' => $reservaFinal,
            'id_producto' => $idProducto,
        ]);
    }

    $actualizarPedido = $pdo->prepare(
        "UPDATE pedidos
         SET
            estado_stock = 'liberado',
            stock_reservado_en = NULL,
            actualizado_en = CURRENT_TIMESTAMP
         WHERE id_pedido = :id_pedido
           AND estado_stock = 'reservado'"
    );

    $actualizarPedido->execute([
        'id_pedido' => $idPedido,
    ]);

    if ($actualizarPedido->rowCount() !== 1) {
        throw new RuntimeException(
            'No fue posible marcar la reserva del pedido como liberada.'
        );
    }
}
/**
 * Consume físicamente el stock que ya estaba reservado para un pedido pagado.
 *
 * - Productos normales: descuenta unidades de cantidad_actual.
 * - Alimento seco: descuenta gramos desde stock_lotes usando FEFO.
 * - En ambos casos libera únicamente la reserva perteneciente al pedido.
 *
 * Debe ejecutarse dentro de la misma transacción que confirma el pago.
 * Es idempotente: si el pedido ya está consumido, no vuelve a descontar.
 */
function consumirStockReservadoPedidoCheckout(
    PDO $pdo,
    int $idPedido,
    string $codigoPedido
): void {
    if ($idPedido <= 0) {
        throw new InvalidArgumentException(
            'El pedido no es válido para consumir stock.'
        );
    }

    if (!$pdo->inTransaction()) {
        throw new RuntimeException(
            'La confirmación de stock debe ejecutarse dentro de una transacción.'
        );
    }

    if (!function_exists('descontarGramosFefo')) {
        throw new RuntimeException(
            'No está disponible la función FEFO necesaria para confirmar el stock.'
        );
    }

    $pedidoStatement = $pdo->prepare(
        "SELECT estado_stock
         FROM pedidos
         WHERE id_pedido = :id_pedido
         FOR UPDATE"
    );
    $pedidoStatement->execute(['id_pedido' => $idPedido]);
    $estadoStock = $pedidoStatement->fetchColumn();

    if ($estadoStock === false) {
        throw new RuntimeException(
            'No fue posible encontrar el pedido para confirmar su stock.'
        );
    }

    if ($estadoStock === 'consumido') {
        return;
    }

    if ($estadoStock !== 'reservado') {
        throw new RuntimeException(
            'El pedido no tiene una reserva de stock válida para confirmar.'
        );
    }

    $detallesStatement = $pdo->prepare(
        "SELECT
            d.id_producto,
            p.nombre AS nombre_producto,
            CASE
                WHEN COALESCE(p.detalles_opcionales->>'subcategoria_codigo', '') = 'alimento-seco'
                  OR UPPER(COALESCE(p.detalles_opcionales->>'subcategoria', '')) = 'ALIMENTO SECO'
                THEN TRUE
                ELSE FALSE
            END AS es_alimento_seco,
            SUM(
                CASE
                    WHEN COALESCE(p.detalles_opcionales->>'subcategoria_codigo', '') = 'alimento-seco'
                      OR UPPER(COALESCE(p.detalles_opcionales->>'subcategoria', '')) = 'ALIMENTO SECO'
                    THEN d.cantidad * COALESCE(pp.cantidad_gramos, d.cantidad_gramos, 0)
                    ELSE d.cantidad
                END
            ) AS cantidad_consumir
         FROM pedido_detalles d
         INNER JOIN productos p
            ON p.id_producto = d.id_producto
         LEFT JOIN producto_presentaciones pp
            ON pp.id_presentacion = d.id_presentacion
           AND pp.id_producto = d.id_producto
         WHERE d.id_pedido = :id_pedido
         GROUP BY
            d.id_producto,
            p.nombre,
            CASE
                WHEN COALESCE(p.detalles_opcionales->>'subcategoria_codigo', '') = 'alimento-seco'
                  OR UPPER(COALESCE(p.detalles_opcionales->>'subcategoria', '')) = 'ALIMENTO SECO'
                THEN TRUE
                ELSE FALSE
            END
         ORDER BY d.id_producto ASC"
    );
    $detallesStatement->execute(['id_pedido' => $idPedido]);
    $consumos = $detallesStatement->fetchAll();

    if ($consumos === []) {
        throw new RuntimeException(
            'El pedido no contiene productos para confirmar stock.'
        );
    }

    $stockStatement = $pdo->prepare(
        "SELECT cantidad_actual, cantidad_reservada
         FROM stock
         WHERE id_producto = :id_producto
         FOR UPDATE"
    );

    $actualizarStockNormal = $pdo->prepare(
        "UPDATE stock
         SET
            cantidad_actual = :cantidad_actual,
            cantidad_reservada = :cantidad_reservada,
            actualizado_en = CURRENT_TIMESTAMP
         WHERE id_producto = :id_producto"
    );

    $actualizarReservaFraccionado = $pdo->prepare(
        "UPDATE stock
         SET
            cantidad_actual = :cantidad_actual,
            cantidad_reservada = :cantidad_reservada,
            actualizado_en = CURRENT_TIMESTAMP
         WHERE id_producto = :id_producto"
    );

    $movimientoNormal = $pdo->prepare(
        "INSERT INTO movimientos_stock (
            id_producto,
            id_usuario,
            tipo_movimiento,
            cantidad,
            stock_anterior,
            stock_final,
            origen,
            motivo,
            referencia
         ) VALUES (
            :id_producto,
            NULL,
            'venta',
            :cantidad,
            :stock_anterior,
            :stock_final,
            'webpay',
            :motivo,
            :referencia
         )"
    );

    foreach ($consumos as $consumo) {
        $idProducto = (int) $consumo['id_producto'];
        $cantidadConsumir = (int) $consumo['cantidad_consumir'];
        $esAlimentoSeco = filter_var(
            $consumo['es_alimento_seco'],
            FILTER_VALIDATE_BOOL
        );

        if ($cantidadConsumir <= 0) {
            throw new RuntimeException(
                sprintf(
                    'No fue posible determinar el stock a consumir para "%s".',
                    (string) $consumo['nombre_producto']
                )
            );
        }

        $stockStatement->execute(['id_producto' => $idProducto]);
        $stock = $stockStatement->fetch();

        if (!is_array($stock)) {
            throw new RuntimeException(
                sprintf(
                    'El producto "%s" no tiene un registro de stock.',
                    (string) $consumo['nombre_producto']
                )
            );
        }

        $cantidadActual = (int) floor((float) $stock['cantidad_actual']);
        $cantidadReservada = (int) floor((float) $stock['cantidad_reservada']);

        if ($cantidadReservada < $cantidadConsumir) {
            throw new RuntimeException(
                sprintf(
                    'La reserva de stock de "%s" es menor que la cantidad a confirmar.',
                    (string) $consumo['nombre_producto']
                )
            );
        }

    if ($esAlimentoSeco) {
        $reservaFinal = $cantidadReservada - $cantidadConsumir;

        /*
        * Primero libera la porción de reserva perteneciente a este pedido.
        *
        * Esto es necesario antes de ejecutar FEFO porque descontarGramosFefo()
        * sincroniza cantidad_actual con los lotes. Si cantidad_actual bajara
        * antes de reducir cantidad_reservada, podría violarse el constraint
        * stock_reserva_no_supera_actual.
        *
        * Todo ocurre dentro de la misma transacción: si FEFO falla,
        * esta liberación también se revierte.
        */
        $actualizarReservaFraccionado->execute([
            'cantidad_actual' => $cantidadActual,
            'cantidad_reservada' => $reservaFinal,
            'id_producto' => $idProducto,
        ]);

        $stockFinal = descontarGramosFefo(
            $pdo,
            $idProducto,
            $cantidadConsumir,
            null,
            'venta',
            'webpay',
            'Venta confirmada por Webpay',
            $codigoPedido
        );

        continue;
    }

        if ($cantidadActual < $cantidadConsumir) {
            throw new RuntimeException(
                sprintf(
                    'El stock físico de "%s" es insuficiente para confirmar la venta.',
                    (string) $consumo['nombre_producto']
                )
            );
        }

        $stockFinal = $cantidadActual - $cantidadConsumir;
        $reservaFinal = $cantidadReservada - $cantidadConsumir;

        $actualizarStockNormal->execute([
            'cantidad_actual' => $stockFinal,
            'cantidad_reservada' => $reservaFinal,
            'id_producto' => $idProducto,
        ]);

        $movimientoNormal->execute([
            'id_producto' => $idProducto,
            'cantidad' => -$cantidadConsumir,
            'stock_anterior' => $cantidadActual,
            'stock_final' => $stockFinal,
            'motivo' => 'Venta confirmada por Webpay',
            'referencia' => $codigoPedido,
        ]);
    }

    $actualizarPedido = $pdo->prepare(
        "UPDATE pedidos
         SET
            estado_stock = 'consumido',
            actualizado_en = CURRENT_TIMESTAMP
         WHERE id_pedido = :id_pedido
           AND estado_stock = 'reservado'"
    );
    $actualizarPedido->execute(['id_pedido' => $idPedido]);

    if ($actualizarPedido->rowCount() !== 1) {
        throw new RuntimeException(
            'No fue posible marcar el stock del pedido como consumido.'
        );
    }
}

/**
 * Crea el pedido completo y sus detalles dentro de una transacción activa.
 */
function crearPedidoCheckout(
    PDO $pdo,
    array $datosCliente,
    array $resumen,
    ?array $tarifa,
    string $metodoEntrega = 'despacho',
    ?int $idClienteSesion = null
): array {
    if (!in_array($metodoEntrega, ['despacho', 'retiro_en_tienda'], true)) {
        throw new InvalidArgumentException('La modalidad de entrega no es válida.');
    }

    if ($metodoEntrega === 'despacho' && $tarifa === null) {
        throw new InvalidArgumentException(
            'No existe una tarifa de despacho válida para el pedido.'
        );
    }

    $codigoPedido = generarCodigoPedidoCheckout($pdo);
    $esDespacho = $metodoEntrega === 'despacho';
    $comunaCliente = $esDespacho ? (string) ($tarifa['comuna'] ?? '') : '';
    $regionCliente = $esDespacho ? (string) ($tarifa['region'] ?? '') : '';

    $idCliente = obtenerOCrearClienteCheckout(
        $pdo,
        $datosCliente,
        $comunaCliente,
        $regionCliente,
        $idClienteSesion
    );

    $subtotal = (int) $resumen['subtotal'];
    $calculoDespacho = $esDespacho
        ? calcularCostoDespachoCheckout($tarifa ?? [], $subtotal)
        : [
            'costo_despacho' => 0,
            'monto_envio_gratis' => null,
            'aplica_envio_gratis' => false,
            'faltante_envio_gratis' => null,
        ];
    $costoDespacho = (int) $calculoDespacho['costo_despacho'];
    $total = $subtotal + $costoDespacho;

    $statement = $pdo->prepare(
        "INSERT INTO pedidos (
            codigo_pedido,
            id_cliente,
            estado,
            estado_pago,
            estado_stock,
            subtotal,
            descuento,
            costo_despacho,
            total,
            metodo_entrega,
            direccion_entrega,
            comuna_entrega,
            region_entrega,
            metodo_pago,
            observaciones_cliente,
            nombre_cliente,
            email_cliente,
            telefono_cliente,
            id_region_despacho,
            id_comuna_despacho,
            referencia_entrega,
            peso_total_gramos,
            peso_tarifable_gramos,
            peso_maximo_tarifa_gramos,
            id_tarifa_despacho
         ) VALUES (
            :codigo_pedido,
            :id_cliente,
            'recibido',
            'pendiente',
            'sin_reserva',
            :subtotal,
            0,
            :costo_despacho,
            :total,
            :metodo_entrega,
            :direccion_entrega,
            :comuna_entrega,
            :region_entrega,
            'webpay',
            :observaciones_cliente,
            :nombre_cliente,
            :email_cliente,
            :telefono_cliente,
            :id_region_despacho,
            :id_comuna_despacho,
            :referencia_entrega,
            :peso_total_gramos,
            :peso_tarifable_gramos,
            :peso_maximo_tarifa_gramos,
            :id_tarifa_despacho
         )
         RETURNING id_pedido"
    );

    $statement->execute([
        'codigo_pedido' => $codigoPedido,
        'id_cliente' => $idCliente,
        'subtotal' => $subtotal,
        'costo_despacho' => $costoDespacho,
        'total' => $total,
        'metodo_entrega' => $metodoEntrega,
        'direccion_entrega' => $esDespacho ? $datosCliente['direccion'] : null,
        'comuna_entrega' => $esDespacho ? (string) ($tarifa['comuna'] ?? '') : null,
        'region_entrega' => $esDespacho ? (string) ($tarifa['region'] ?? '') : null,
        'observaciones_cliente' => $datosCliente['observaciones'] !== ''
            ? $datosCliente['observaciones']
            : null,
        'nombre_cliente' => $datosCliente['nombre'],
        'email_cliente' => $datosCliente['email'],
        'telefono_cliente' => $datosCliente['telefono'],
        'id_region_despacho' => $esDespacho ? (int) ($tarifa['id_region'] ?? 0) : null,
        'id_comuna_despacho' => $esDespacho ? (int) ($tarifa['id_comuna'] ?? 0) : null,
        'referencia_entrega' => $esDespacho && $datosCliente['referencia'] !== ''
            ? $datosCliente['referencia']
            : null,
        'peso_total_gramos' => (int) $resumen['peso_total_gramos'],
        'peso_tarifable_gramos' => (int) $resumen['peso_tarifable_gramos'],
        'peso_maximo_tarifa_gramos' => $esDespacho ? (int) ($tarifa['peso_maximo_gramos'] ?? 0) : null,
        'id_tarifa_despacho' => $esDespacho ? (int) ($tarifa['id_tarifa_despacho'] ?? 0) : null,
    ]);

    $idPedido = (int) $statement->fetchColumn();

    $detalleStatement = $pdo->prepare(
        "INSERT INTO pedido_detalles (
            id_pedido,
            id_producto,
            id_presentacion,
            nombre_producto,
            sku,
            tipo_item,
            cantidad,
            cantidad_gramos,
            precio_unitario,
            subtotal
         ) VALUES (
            :id_pedido,
            :id_producto,
            :id_presentacion,
            :nombre_producto,
            :sku,
            :tipo_item,
            :cantidad,
            :cantidad_gramos,
            :precio_unitario,
            :subtotal
         )"
    );

    foreach ($resumen['items'] as $item) {
        $detalleStatement->execute([
            'id_pedido' => $idPedido,
            'id_producto' => (int) $item['id_producto'],
            'id_presentacion' => $item['id_presentacion'] !== null
                ? (int) $item['id_presentacion']
                : null,
            'nombre_producto' => $item['nombre_item'] !== ''
                ? $item['nombre'] . ' · ' . $item['nombre_item']
                : $item['nombre'],
            'sku' => $item['sku'] !== ''
                ? $item['sku']
                : null,
            'tipo_item' => $item['id_presentacion'] !== null
                ? 'presentacion'
                : 'producto',
            'cantidad' => (int) $item['cantidad'],
            'cantidad_gramos' => (int) $item['peso_unitario_gramos'] > 0
                ? (int) $item['peso_unitario_gramos']
                : null,
            'precio_unitario' => (int) $item['precio_unitario'],
            'subtotal' => (int) $item['subtotal'],
        ]);
    }

    reservarStockPedidoCheckout($pdo, $idPedido);

    $historial = $pdo->prepare(
        "INSERT INTO pedido_historial_estados (
            id_pedido,
            estado_nuevo,
            estado_pago_nuevo,
            observacion
         ) VALUES (
            :id_pedido,
            'recibido',
            'pendiente',
            :observacion
         )"
    );

    $historial->execute([
        'id_pedido' => $idPedido,
        'observacion' => $esDespacho
            ? 'Pedido creado desde checkout público con despacho. Stock reservado y pendiente de iniciar pago Webpay.'
            : 'Pedido creado desde checkout público con retiro en tienda. Stock reservado y pendiente de iniciar pago Webpay.',
    ]);

    return [
        'id_pedido' => $idPedido,
        'codigo_pedido' => $codigoPedido,
        'id_cliente' => $idCliente,
        'subtotal' => $subtotal,
        'costo_despacho' => $costoDespacho,
        'total' => $total,
        'metodo_entrega' => $metodoEntrega,
    ];
}