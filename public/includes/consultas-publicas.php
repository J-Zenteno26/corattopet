<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function obtenerConfiguracionPublica(PDO $pdo): array
{
    $statement = $pdo->prepare('SELECT * FROM configuracion_tienda WHERE id_configuracion = :id LIMIT 1');
    $statement->execute(['id' => 1]);
    return $statement->fetch() ?: [];
}

function obtenerMetadataCatalogoPublico(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            COALESCE(
                (SELECT json_build_object('whatsapp_principal', ct.whatsapp_principal)
                 FROM configuracion_tienda ct
                 WHERE ct.id_configuracion = 1
                 LIMIT 1),
                '{}'::json
            ) AS config,
            COALESCE(
                (SELECT json_agg(json_build_object('id', c.id_categoria, 'nombre', c.nombre) ORDER BY c.orden, c.nombre)
                 FROM categorias c WHERE c.activo = TRUE),
                '[]'::json
            ) AS categorias,
            COALESCE(
                (SELECT json_agg(json_build_object('id', m.id_marca, 'nombre', m.nombre) ORDER BY m.nombre)
                 FROM marcas m WHERE m.activo = TRUE),
                '[]'::json
            ) AS marcas,
            COALESCE(
                (SELECT json_agg(
                    json_build_object(
                        'id', s.id_subcategoria,
                        'id_categoria', s.id_categoria,
                        'nombre', s.nombre,
                        'slug', s.slug
                    ) ORDER BY s.id_categoria, s.orden, s.nombre
                 )
                 FROM subcategorias s
                 INNER JOIN categorias c ON c.id_categoria = s.id_categoria
                 WHERE s.activo = TRUE AND c.activo = TRUE),
                '[]'::json
            ) AS subcategorias"
    );
    $row = $statement->fetch();
    $decode = static function (mixed $value, array $fallback): array {
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : $fallback;
    };

    return [
        'config' => $decode($row['config'] ?? '{}', []),
        'categorias' => $decode($row['categorias'] ?? '[]', []),
        'marcas' => $decode($row['marcas'] ?? '[]', []),
        'subcategorias' => $decode($row['subcategorias'] ?? '[]', []),
    ];
}

function obtenerProductosDestacadosHome(PDO $pdo, int $limite = 6): array
{
    $statement = $pdo->prepare(
        "SELECT p.id_producto, p.nombre, p.tipo_mascota, p.detalles_opcionales,
                m.nombre AS marca, c.nombre AS categoria,
                (SELECT ip.archivo FROM imagenes_producto ip
                 WHERE ip.id_producto = p.id_producto AND ip.activo = TRUE
                 ORDER BY ip.es_principal DESC, ip.orden, ip.id_imagen LIMIT 1) AS imagen
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria
         INNER JOIN marcas m ON m.id_marca = p.id_marca
         WHERE p.estado = 'activo' AND c.activo = TRUE AND c.nombre ILIKE :categoria
         ORDER BY p.actualizado_en DESC, p.id_producto DESC LIMIT :limite"
    );
    $statement->bindValue(':categoria', '%alimento%', PDO::PARAM_STR);
    $statement->bindValue(':limite', max(1, min(6, $limite)), PDO::PARAM_INT);
    $statement->execute();

    return array_map(static function (array $product): array {
        $details = json_decode((string) ($product['detalles_opcionales'] ?? ''), true);
        $details = is_array($details) ? $details : [];
        $source = (string) ($details['analisis_caracteristicas'] ?? $details['descripcion'] ?? 'Una alternativa seleccionada por su calidad y formulación.');
        $source = trim(preg_replace('/\s+/', ' ', strip_tags($source)) ?? '');
        $product['beneficio'] = mb_strlen($source) > 135 ? mb_substr($source, 0, 132) . '…' : $source;
        $product['ideal_para'] = $details['etapa_vida_tamano'] ?? ucfirst((string) ($product['tipo_mascota'] ?? 'perros y gatos'));
        $product['proteina'] = $details['ingredientes_materiales'] ?? '';
        $product['formato'] = trim((string) (($details['formato'] ?? '') . ' ' . ($details['peso_contenido'] ?? '') . ' ' . ($details['unidad'] ?? '')));
        return $product;
    }, $statement->fetchAll());
}

function obtenerWhatsappPublico(array $config): string
{
    $number = preg_replace('/\D+/', '', (string) ($config['whatsapp_principal'] ?? '')) ?? '';
    return $number === '' ? '#contacto' : 'https://wa.me/' . $number . '?text=' . rawurlencode('Hola Coratto Pet, necesito orientación para elegir alimento.');
}

function valorBooleanoPublico(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 't', 'true'], true);
}

function enriquecerProductoPublico(array $product): array
{
    $details = json_decode((string) ($product['detalles_opcionales'] ?? ''), true);
    $product['detalles'] = is_array($details) ? $details : [];
    $details = $product['detalles'];
    $product['fraccionable'] = mb_strtoupper((string) ($details['subcategoria'] ?? '')) === 'ALIMENTO SECO';
    $product['disponible'] = (float) ($product['cantidad_disponible'] ?? 0) > 0;

    return $product;
}

function obtenerProductosCalculadoraPublica(PDO $pdo): array
{
    $statement = $pdo->prepare(
        "SELECT p.id_producto,p.nombre,p.sku,p.tipo_mascota,p.detalles_opcionales,
                COALESCE(m.nombre,'Sin marca') AS marca,
                GREATEST(
                    COALESCE(
                        (
                            SELECT SUM(sl.cantidad_disponible_g)
                            FROM stock_lotes sl
                            WHERE sl.id_producto = p.id_producto
                            AND sl.activo = TRUE
                            AND sl.fecha_vencimiento >= CURRENT_DATE
                        ),
                        0
                    ) - COALESCE(s.cantidad_reservada, 0),
                    0
                ) AS cantidad_disponible,
                (SELECT ip.archivo FROM imagenes_producto ip
                 WHERE ip.id_producto=p.id_producto AND ip.activo=TRUE
                 ORDER BY ip.es_principal DESC,ip.orden,ip.id_imagen LIMIT 1) AS imagen
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria=p.id_categoria
         LEFT JOIN marcas m ON m.id_marca=p.id_marca
         LEFT JOIN stock s ON s.id_producto=p.id_producto
         WHERE p.estado='activo' AND c.activo=TRUE
           AND (m.id_marca IS NULL OR m.activo=TRUE)
           AND LOWER(COALESCE(p.detalles_opcionales->'calculadora'->>'activo','false'))='true'
           AND UPPER(COALESCE(p.detalles_opcionales->>'subcategoria',''))='ALIMENTO SECO'
         ORDER BY p.nombre ASC"
    );
    $statement->execute();

    return array_map(static function (array $row): array {
        $details = json_decode((string) ($row['detalles_opcionales'] ?? ''), true);
        $details = is_array($details) ? $details : [];
        $profile = is_array($details['calculadora'] ?? null) ? $details['calculadora'] : [];
        $strings = static function (mixed $values): array {
            if (!is_array($values)) return [];
            return array_values(array_filter(array_map(static fn(mixed $value): string => strtolower(trim((string) $value)), $values), static fn(string $value): bool => $value !== ''));
        };
        $image = ltrim(str_replace('\\', '/', trim((string) ($row['imagen'] ?? ''))), '/');
        if ($image !== '' && !str_contains($image, '..')) {
            if (str_starts_with($image, 'public/')) {
                $image = substr($image, 7);
            }

            if (!str_starts_with($image, 'uploads/productos/')) {
                $image = 'uploads/productos/' . $image;
            }

            $image = 'https://corattopet.cl/public/' . $image;
        } else {
            $image = '';
        }
        $kcal = filter_var($profile['kcal_kg'] ?? null, FILTER_VALIDATE_FLOAT);
        $sku = trim((string) ($row['sku'] ?? ''));

        return [
            'id' => (int) $row['id_producto'], 'sku' => $sku, 'nombre' => (string) $row['nombre'],
            'marca' => (string) $row['marca'], 'especie' => strtolower((string) $row['tipo_mascota']),
            'imagen' => $image, 'disponible' => (float) $row['cantidad_disponible'] > 0,
            'kcalKg' => $kcal === false || $kcal <= 0 ? null : (float) $kcal,
            'energiaVerificada' => valorBooleanoPublico($profile['energia_verificada'] ?? false),
            'etapas' => $strings($profile['etapas'] ?? []), 'tamanos' => $strings($profile['tamanos'] ?? []),
            'proteinas' => $strings($profile['proteinas'] ?? []),
            'esterilizados' => valorBooleanoPublico($profile['esterilizados'] ?? false),
            'controlPeso' => valorBooleanoPublico($profile['control_peso'] ?? false),
            'sensible' => valorBooleanoPublico($profile['sensible'] ?? false),
            'grainFree' => valorBooleanoPublico($profile['grain_free'] ?? false),
            'url' => appUrl('public/catalogo.php?sku=' . rawurlencode($sku)),
        ];
    }, $statement->fetchAll());
}

function construirFiltrosCatalogoPublico(array $filters): array
{
    $where = ["p.estado = 'activo'", 'c.activo = TRUE', "NULLIF(TRIM(p.sku), '') IS NOT NULL", '(m.id_marca IS NULL OR m.activo = TRUE)'];
    $bindings = [];

    $search = trim((string) ($filters['buscar'] ?? ''));
    if ($search !== '') {
        $where[] = "(
        p.nombre ILIKE :buscar_nombre
        OR p.sku ILIKE :buscar_sku
        OR c.nombre ILIKE :buscar_categoria
        OR m.nombre ILIKE :buscar_marca
        OR EXISTS (
            SELECT 1
            FROM subcategorias sc_busqueda
            WHERE sc_busqueda.id_categoria = p.id_categoria
            AND sc_busqueda.activo = TRUE
            AND (
                p.detalles_opcionales->>'subcategoria_codigo' = sc_busqueda.slug
                OR LOWER(TRIM(p.detalles_opcionales->>'subcategoria')) = LOWER(TRIM(sc_busqueda.nombre))
            )
            AND (
                sc_busqueda.nombre ILIKE :buscar_subcategoria_nombre
                OR sc_busqueda.slug ILIKE :buscar_subcategoria_slug
            )
        )
    )";
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $searchPattern = '%' . $escapedSearch . '%';
        foreach ([
            'buscar_nombre',
            'buscar_sku',
            'buscar_categoria',
            'buscar_marca',
            'buscar_subcategoria_nombre',
            'buscar_subcategoria_slug',
        ] as $searchBinding) {
            $bindings[$searchBinding] = $searchPattern;
        }
    }
    foreach (['tipo_mascota' => 'p.tipo_mascota', 'categoria' => 'p.id_categoria', 'marca' => 'p.id_marca'] as $key => $column) {
        $value = trim((string) ($filters[$key] ?? ''));
        if ($value !== '') {
            $where[] = $column . ' = :' . $key;
            $bindings[$key] = $value;
        }
    }
    if (trim((string) ($filters['categoria'] ?? '')) !== '' && trim((string) ($filters['subcategoria'] ?? '')) !== '') {
        $where[] = "EXISTS (
            SELECT 1 FROM subcategorias sc
            WHERE sc.id_categoria = p.id_categoria
              AND sc.activo = TRUE
              AND sc.slug = :subcategoria
              AND (
                p.detalles_opcionales->>'subcategoria_codigo' = sc.slug
                OR LOWER(TRIM(p.detalles_opcionales->>'subcategoria')) = LOWER(TRIM(sc.nombre))
              )
        )";
        $bindings['subcategoria'] = $filters['subcategoria'];
    }
    if (in_array(($filters['fraccionable'] ?? ''), ['si', 'no'], true)) {
        $condition = "COALESCE(UPPER(p.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO', FALSE)";
        $where[] = $filters['fraccionable'] === 'si' ? $condition : 'NOT (' . $condition . ')';
    }
    if (($filters['disponibilidad'] ?? '') === 'disponible') {
        $where[] = 'COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) > 0';
    }

    return [implode(' AND ', $where), $bindings];
}

function enlazarFiltrosCatalogoPublico(PDOStatement $statement, array $bindings): void
{
    foreach ($bindings as $key => $value) {
        $statement->bindValue(':' . $key, $value, is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
    }
}

function contarProductosCatalogoPublico(PDO $pdo, array $filters = []): int
{
    [$whereSql, $bindings] = construirFiltrosCatalogoPublico($filters);
    $statement = $pdo->prepare(
        "SELECT COUNT(p.id_producto)
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria
         LEFT JOIN marcas m ON m.id_marca = p.id_marca
         LEFT JOIN stock s ON s.id_producto = p.id_producto
         WHERE {$whereSql}"
    );
    enlazarFiltrosCatalogoPublico($statement, $bindings);
    $statement->execute();

    return (int) $statement->fetchColumn();
}

function obtenerProductosCatalogoPublico(
    PDO $pdo,
    array $filters = [],
    ?int $limit = null,
    int $offset = 0
): array {
    [$whereSql, $bindings] = construirFiltrosCatalogoPublico($filters);
    $paginationSql = '';
    if ($limit !== null) {
        $paginationSql = ' LIMIT :limit OFFSET :offset';
    }

    $sql = "WITH productos_filtrados AS (
                SELECT p.id_producto, p.nombre, p.sku, p.tipo_mascota, p.precio_venta,
                       p.detalles_opcionales, c.nombre AS categoria, c.maneja_fraccionamiento,
                       COALESCE(m.nombre, 'Sin marca') AS marca,
                       COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) AS cantidad_disponible
                FROM productos p
                INNER JOIN categorias c ON c.id_categoria = p.id_categoria
                LEFT JOIN marcas m ON m.id_marca = p.id_marca
                LEFT JOIN stock s ON s.id_producto = p.id_producto
                WHERE {$whereSql}
                ORDER BY p.nombre ASC, p.id_producto ASC{$paginationSql}
            )
            SELECT pf.*,
                   imagen.archivo AS imagen,
                   presentaciones.resumen AS presentaciones_resumen
            FROM productos_filtrados pf
            LEFT JOIN LATERAL (
                SELECT ip.archivo
                FROM imagenes_producto ip
                WHERE ip.id_producto = pf.id_producto AND ip.activo = TRUE
                ORDER BY ip.es_principal DESC, ip.orden, ip.id_imagen
                LIMIT 1
            ) imagen ON TRUE
            LEFT JOIN LATERAL (
                SELECT string_agg(pp.nombre, ' · ' ORDER BY pp.orden, pp.cantidad_gramos) AS resumen
                FROM producto_presentaciones pp
                WHERE pp.id_producto = pf.id_producto AND pp.activo = TRUE
            ) presentaciones ON TRUE
            ORDER BY pf.nombre ASC, pf.id_producto ASC";
    $statement = $pdo->prepare($sql);
    enlazarFiltrosCatalogoPublico($statement, $bindings);
    if ($limit !== null) {
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    }
    $statement->execute();

    return array_map('enriquecerProductoPublico', $statement->fetchAll());
}

function obtenerPaginaProductosCatalogoPublico(
    PDO $pdo,
    array $filters,
    int $limit,
    int $offset
): array {
    [$whereSql, $bindings] = construirFiltrosCatalogoPublico($filters);
    $statement = $pdo->prepare(
        "WITH productos_coincidentes AS (
            SELECT p.id_producto, p.nombre, p.sku, p.tipo_mascota, p.precio_venta,
                   p.detalles_opcionales, c.nombre AS categoria, c.maneja_fraccionamiento,
                   COALESCE(m.nombre, 'Sin marca') AS marca,
                   COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) AS cantidad_disponible,
                   COUNT(*) OVER() AS total_coincidencias
            FROM productos p
            INNER JOIN categorias c ON c.id_categoria = p.id_categoria
            LEFT JOIN marcas m ON m.id_marca = p.id_marca
            LEFT JOIN stock s ON s.id_producto = p.id_producto
            WHERE {$whereSql}
        ), pagina AS (
            SELECT *
            FROM productos_coincidentes
            ORDER BY nombre ASC, id_producto ASC
            LIMIT :limit OFFSET :offset
        ), pagina_enriquecida AS (
            SELECT pagina.*,
                   imagen.archivo AS imagen,
                   presentaciones.resumen AS presentaciones_resumen
            FROM pagina
            LEFT JOIN LATERAL (
                SELECT ip.archivo
                FROM imagenes_producto ip
                WHERE ip.id_producto = pagina.id_producto AND ip.activo = TRUE
                ORDER BY ip.es_principal DESC, ip.orden, ip.id_imagen
                LIMIT 1
            ) imagen ON TRUE
            LEFT JOIN LATERAL (
                SELECT string_agg(pp.nombre, ' · ' ORDER BY pp.orden, pp.cantidad_gramos) AS resumen
                FROM producto_presentaciones pp
                WHERE pp.id_producto = pagina.id_producto AND pp.activo = TRUE
            ) presentaciones ON TRUE
        ), total AS (
            SELECT COALESCE(MAX(total_coincidencias), 0)::bigint AS total_resultados
            FROM productos_coincidentes
        )
        SELECT pagina_enriquecida.*, total.total_resultados
        FROM total
        LEFT JOIN pagina_enriquecida ON TRUE
        ORDER BY pagina_enriquecida.nombre ASC NULLS LAST,
                 pagina_enriquecida.id_producto ASC NULLS LAST"
    );
    enlazarFiltrosCatalogoPublico($statement, $bindings);
    $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll();
    $total = (int) ($rows[0]['total_resultados'] ?? 0);
    $products = [];
    foreach ($rows as $row) {
        if ($row['id_producto'] === null) {
            continue;
        }
        unset($row['total_resultados'], $row['total_coincidencias']);
        $products[] = enriquecerProductoPublico($row);
    }

    return ['registros' => $products, 'total' => $total];
}

function obtenerProductoPublicoPorSku(PDO $pdo, string $sku): ?array
{
    $statement = $pdo->prepare(
        "SELECT p.id_producto, p.nombre, p.sku, p.tipo_mascota, p.precio_venta,
                p.detalles_opcionales, c.nombre AS categoria, c.maneja_fraccionamiento,
                COALESCE(m.nombre, 'Sin marca') AS marca,
                COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) AS cantidad_disponible,
                (SELECT ip.archivo FROM imagenes_producto ip
                 WHERE ip.id_producto = p.id_producto AND ip.activo = TRUE
                 ORDER BY ip.es_principal DESC, ip.orden, ip.id_imagen LIMIT 1) AS imagen,
                pp.id_presentacion AS presentacion_detectada_id,
                pp.nombre AS presentacion_detectada_nombre,
                pp.sku AS presentacion_detectada_sku
         FROM productos p
         INNER JOIN categorias c ON c.id_categoria = p.id_categoria AND c.activo = TRUE
         LEFT JOIN marcas m ON m.id_marca = p.id_marca
         LEFT JOIN stock s ON s.id_producto = p.id_producto
         LEFT JOIN producto_presentaciones pp ON pp.id_producto = p.id_producto
             AND pp.activo = TRUE AND LOWER(TRIM(pp.sku)) = LOWER(TRIM(:sku_presentacion))
         WHERE p.estado = 'activo' AND (m.id_marca IS NULL OR m.activo = TRUE)
           AND (LOWER(TRIM(p.sku)) = LOWER(TRIM(:sku_producto)) OR pp.id_presentacion IS NOT NULL)
         ORDER BY CASE WHEN LOWER(TRIM(p.sku)) = LOWER(TRIM(:sku_orden)) THEN 0 ELSE 1 END
         LIMIT 1"
    );
    $statement->execute(['sku_presentacion' => $sku, 'sku_producto' => $sku, 'sku_orden' => $sku]);
    $product = $statement->fetch();

    return is_array($product) ? enriquecerProductoPublico($product) : null;
}

function obtenerImagenesPublicasProducto(PDO $pdo, int $idProducto): array
{
    if ($idProducto <= 0) {
        return [];
    }

    $statement = $pdo->prepare(
        "SELECT id_imagen, archivo, texto_alternativo, es_principal, orden
         FROM imagenes_producto
         WHERE id_producto = :id_producto
           AND activo = TRUE
         ORDER BY es_principal DESC, orden, id_imagen"
    );
    $statement->execute(['id_producto' => $idProducto]);

    return $statement->fetchAll();
}

function obtenerPresentacionesPublicasProducto(PDO $pdo, int $idProducto): array
{
    $statement = $pdo->prepare(
        "SELECT pp.id_presentacion, pp.nombre, pp.cantidad_gramos, pp.precio_venta, pp.sku,
                CASE WHEN UPPER(p.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO'
                     THEN (
                         GREATEST(
                             COALESCE(
                                 (SELECT SUM(sl.cantidad_disponible_g)
                                  FROM stock_lotes sl
                                  WHERE sl.id_producto = pp.id_producto
                                    AND sl.activo = TRUE
                                    AND sl.fecha_vencimiento >= CURRENT_DATE),
                                 0
                             ) - COALESCE(s.cantidad_reservada, 0),
                             0
                         ) >= pp.cantidad_gramos
                     )
                     ELSE (COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) >= pp.cantidad_gramos)
                END AS disponible
         FROM producto_presentaciones pp
         INNER JOIN productos p ON p.id_producto=pp.id_producto
         INNER JOIN categorias c ON c.id_categoria=p.id_categoria
         LEFT JOIN stock s ON s.id_producto = pp.id_producto
         WHERE pp.id_producto = :id_producto AND pp.activo = TRUE
         ORDER BY pp.orden, pp.cantidad_gramos, pp.nombre"
    );
    $statement->execute(['id_producto' => $idProducto]);
    return $statement->fetchAll();
}
/**
 * Obtiene una línea comprable del catálogo para reconstruirla en el carrito.
 *
 * La sesión solo conserva identificadores y cantidad. Los datos comerciales
 * se consultan nuevamente desde la base de datos para no confiar en precios,
 * nombres o disponibilidad almacenados en el navegador.
 */
function obtenerItemCarritoPublico(
    PDO $pdo,
    int $idProducto,
    ?int $idPresentacion = null
): ?array {
    if ($idProducto <= 0) {
        return null;
    }

    /*
     * Producto base sin presentación.
     */
    if ($idPresentacion === null) {
        $statement = $pdo->prepare(
            "SELECT
                p.id_producto,
                NULL::INTEGER AS id_presentacion,
                p.nombre AS nombre_producto,
                p.nombre AS nombre_item,
                p.sku,
                p.precio_venta,
                p.tipo_mascota,
                p.detalles_opcionales,
                c.nombre AS categoria,
                c.maneja_fraccionamiento,
                COALESCE(m.nombre, 'Sin marca') AS marca,
                COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) AS cantidad_disponible,
                (
                    SELECT COUNT(pp.id_presentacion)
                    FROM producto_presentaciones pp
                    WHERE pp.id_producto = p.id_producto
                      AND pp.activo = TRUE
                ) AS cantidad_presentaciones_activas,
                (
                    SELECT ip.archivo
                    FROM imagenes_producto ip
                    WHERE ip.id_producto = p.id_producto
                      AND ip.activo = TRUE
                    ORDER BY
                        ip.es_principal DESC,
                        ip.orden,
                        ip.id_imagen
                    LIMIT 1
                ) AS imagen
             FROM productos p
             INNER JOIN categorias c
                ON c.id_categoria = p.id_categoria
               AND c.activo = TRUE
             LEFT JOIN marcas m
                ON m.id_marca = p.id_marca
             LEFT JOIN stock s
                ON s.id_producto = p.id_producto
             WHERE p.id_producto = :id_producto
               AND p.estado = 'activo'
               AND (m.id_marca IS NULL OR m.activo = TRUE)
             LIMIT 1"
        );

        $statement->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
        $statement->execute();

        $item = $statement->fetch();

        if (!is_array($item)) {
            return null;
        }

        $detalles = json_decode(
            (string) ($item['detalles_opcionales'] ?? ''),
            true
        );

        $item['detalles'] = is_array($detalles) ? $detalles : [];
        $item['tipo_item'] = 'producto';
        $item['cantidad_disponible'] = max(
            0,
            (int) floor((float) ($item['cantidad_disponible'] ?? 0))
        );
        $item['cantidad_presentaciones_activas'] = max(
            0,
            (int) ($item['cantidad_presentaciones_activas'] ?? 0)
        );
        $item['precio_venta'] = max(
            0,
            (int) ($item['precio_venta'] ?? 0)
        );
        $item['disponible'] = $item['cantidad_disponible'] > 0;

        return $item;
    }

    if ($idPresentacion <= 0) {
        return null;
    }

    /*
     * Presentación específica de un producto.
     *
     * Para alimentos secos, la disponibilidad se calcula desde el saldo total
     * vendible menos lo ya reservado. Las presentaciones no tienen stock propio.
     */
    $statement = $pdo->prepare(
        "SELECT
            p.id_producto,
            pp.id_presentacion,
            p.nombre AS nombre_producto,
            pp.nombre AS nombre_item,
            pp.sku,
            pp.precio_venta,
            pp.cantidad_gramos,
            p.tipo_mascota,
            p.detalles_opcionales,
            c.nombre AS categoria,
            c.maneja_fraccionamiento,
            COALESCE(m.nombre, 'Sin marca') AS marca,
            CASE
                WHEN UPPER(p.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO'
                THEN FLOOR(
                    GREATEST(
                        COALESCE(
                            (SELECT SUM(sl.cantidad_disponible_g)
                             FROM stock_lotes sl
                             WHERE sl.id_producto = p.id_producto
                               AND sl.activo = TRUE
                               AND sl.fecha_vencimiento >= CURRENT_DATE),
                            0
                        ) - COALESCE(s.cantidad_reservada, 0),
                        0
                    ) / pp.cantidad_gramos
                )
                ELSE FLOOR(
                    COALESCE(
                        s.cantidad_actual - s.cantidad_reservada,
                        0
                    ) / pp.cantidad_gramos
                )
            END AS cantidad_disponible,
            (
                SELECT ip.archivo
                FROM imagenes_producto ip
                WHERE ip.id_producto = p.id_producto
                  AND ip.activo = TRUE
                ORDER BY
                    ip.es_principal DESC,
                    ip.orden,
                    ip.id_imagen
                LIMIT 1
            ) AS imagen
         FROM producto_presentaciones pp
         INNER JOIN productos p
            ON p.id_producto = pp.id_producto
           AND p.estado = 'activo'
         INNER JOIN categorias c
            ON c.id_categoria = p.id_categoria
           AND c.activo = TRUE
         LEFT JOIN marcas m
            ON m.id_marca = p.id_marca
         LEFT JOIN stock s
            ON s.id_producto = p.id_producto
         WHERE pp.id_presentacion = :id_presentacion
           AND pp.id_producto = :id_producto
           AND pp.activo = TRUE
           AND (m.id_marca IS NULL OR m.activo = TRUE)
         LIMIT 1"
    );

    $statement->bindValue(':id_presentacion', $idPresentacion, PDO::PARAM_INT);
    $statement->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
    $statement->execute();

    $item = $statement->fetch();

    if (!is_array($item)) {
        return null;
    }

    $detalles = json_decode(
        (string) ($item['detalles_opcionales'] ?? ''),
        true
    );

    $item['detalles'] = is_array($detalles) ? $detalles : [];
    $item['tipo_item'] = 'presentacion';
    $item['cantidad_gramos'] = (int) ($item['cantidad_gramos'] ?? 0);
    $item['cantidad_disponible'] = max(
        0,
        (int) floor((float) ($item['cantidad_disponible'] ?? 0))
    );
    $item['precio_venta'] = max(
        0,
        (int) ($item['precio_venta'] ?? 0)
    );
    $item['disponible'] = $item['cantidad_disponible'] > 0;

    return $item;
}
function obtenerFiltrosCatalogoPublico(PDO $pdo): array
{
    $metadata = obtenerMetadataCatalogoPublico($pdo);

    return [
        'categorias' => $metadata['categorias'],
        'marcas' => $metadata['marcas'],
        'subcategorias' => $metadata['subcategorias'],
    ];
}

function obtenerCategoriasPublicas(PDO $pdo): array
{
    $statement = $pdo->prepare(
        "SELECT id_categoria AS id, nombre
         FROM categorias
         WHERE activo = TRUE
         ORDER BY orden NULLS LAST, nombre"
    );
    $statement->execute();
    return $statement->fetchAll();
}

function obtenerCategoriasBlogPublico(PDO $pdo): array
{
    $statement = $pdo->prepare(
        'SELECT id_categoria_blog, nombre, slug
         FROM blog_categorias
         WHERE activo = TRUE
         ORDER BY orden, nombre'
    );
    $statement->execute();
    return $statement->fetchAll();
}

function obtenerArticuloDestacadoBlogPublico(PDO $pdo): ?array
{
    $statement = $pdo->prepare(
        "SELECT a.id_articulo, a.titulo, a.slug, a.extracto, a.imagen_portada,
                a.autor_publico, a.fecha_publicacion, c.nombre AS categoria
         FROM blog_articulos a
         INNER JOIN blog_categorias c ON c.id_categoria_blog = a.id_categoria_blog
         WHERE a.estado = 'publicado' AND a.destacado = TRUE AND c.activo = TRUE
         ORDER BY a.fecha_publicacion DESC NULLS LAST, a.id_articulo DESC
         LIMIT 1"
    );
    $statement->execute();
    $article = $statement->fetch();
    return is_array($article) ? $article : null;
}

function contarArticulosBlogPublico(PDO $pdo, ?string $categorySlug, ?int $excludedArticleId): int
{
    $where = ["a.estado = 'publicado'", 'c.activo = TRUE'];
    $bindings = [];
    if ($categorySlug !== null) {
        $where[] = 'c.slug = :categoria';
        $bindings['categoria'] = $categorySlug;
    }
    if ($excludedArticleId !== null) {
        $where[] = 'a.id_articulo <> :excluido';
        $bindings['excluido'] = $excludedArticleId;
    }
    $statement = $pdo->prepare(
        'SELECT COUNT(a.id_articulo)
         FROM blog_articulos a
         INNER JOIN blog_categorias c ON c.id_categoria_blog = a.id_categoria_blog
         WHERE ' . implode(' AND ', $where)
    );
    foreach ($bindings as $name => $value) {
        $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->execute();
    return (int) $statement->fetchColumn();
}

function obtenerArticulosBlogPublico(PDO $pdo, ?string $categorySlug, ?int $excludedArticleId, int $page, int $perPage = 6): array
{
    $where = ["a.estado = 'publicado'", 'c.activo = TRUE'];
    $bindings = [];
    if ($categorySlug !== null) {
        $where[] = 'c.slug = :categoria';
        $bindings['categoria'] = $categorySlug;
    }
    if ($excludedArticleId !== null) {
        $where[] = 'a.id_articulo <> :excluido';
        $bindings['excluido'] = $excludedArticleId;
    }
    $statement = $pdo->prepare(
        'SELECT a.id_articulo, a.titulo, a.slug, a.extracto, a.imagen_portada,
                a.autor_publico, a.fecha_publicacion, c.nombre AS categoria
         FROM blog_articulos a
         INNER JOIN blog_categorias c ON c.id_categoria_blog = a.id_categoria_blog
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY a.fecha_publicacion DESC NULLS LAST, a.id_articulo DESC
         LIMIT :limite OFFSET :desplazamiento'
    );
    foreach ($bindings as $name => $value) {
        $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $statement->bindValue(':limite', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':desplazamiento', ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function obtenerArticuloBlogPublicoPorSlug(PDO $pdo, string $slug): ?array
{
    $statement = $pdo->prepare(
        "SELECT a.id_articulo, a.titulo, a.slug, a.extracto, a.contenido_html,
                a.imagen_portada, a.imagen_complementaria, a.video_url, a.autor_publico, a.fecha_publicacion,
                a.seo_titulo, a.seo_descripcion, c.nombre AS categoria
         FROM blog_articulos a
         INNER JOIN blog_categorias c ON c.id_categoria_blog = a.id_categoria_blog
         WHERE a.slug = :slug AND a.estado = 'publicado' AND c.activo = TRUE
         LIMIT 1"
    );
    $statement->bindValue(':slug', $slug, PDO::PARAM_STR);
    $statement->execute();
    $article = $statement->fetch();
    return is_array($article) ? $article : null;
}
