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

function obtenerProductosCatalogoPublico(PDO $pdo, array $filters = []): array
{
    $where = ["p.estado = 'activo'", 'c.activo = TRUE', "NULLIF(TRIM(p.sku), '') IS NOT NULL", '(m.id_marca IS NULL OR m.activo = TRUE)'];
    $bindings = [];

    $search = trim((string) ($filters['buscar'] ?? ''));
    if ($search !== '') {
        $where[] = "(p.nombre ILIKE :buscar ESCAPE '\\' OR m.nombre ILIKE :buscar ESCAPE '\\' OR p.sku ILIKE :buscar ESCAPE '\\' OR c.nombre ILIKE :buscar ESCAPE '\\')";
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $bindings['buscar'] = '%' . $escapedSearch . '%';
    }
    foreach (['tipo_mascota' => 'p.tipo_mascota', 'categoria' => 'p.id_categoria', 'marca' => 'p.id_marca'] as $key => $column) {
        $value = trim((string) ($filters[$key] ?? ''));
        if ($value !== '') {
            $where[] = $column . ' = :' . $key;
            $bindings[$key] = $value;
        }
    }
    if (in_array(($filters['fraccionable'] ?? ''), ['si', 'no'], true)) {
        $condition = "COALESCE(UPPER(p.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO', FALSE)";
        $where[] = $filters['fraccionable'] === 'si' ? $condition : 'NOT (' . $condition . ')';
    }
    if (($filters['disponibilidad'] ?? '') === 'disponible') {
        $where[] = 'COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) > 0';
    }

    $sql = "SELECT p.id_producto, p.nombre, p.sku, p.tipo_mascota, p.precio_venta,
                   p.detalles_opcionales, c.nombre AS categoria, c.maneja_fraccionamiento,
                   COALESCE(m.nombre, 'Sin marca') AS marca,
                   COALESCE(s.cantidad_actual - s.cantidad_reservada, 0) AS cantidad_disponible,
                   (SELECT ip.archivo FROM imagenes_producto ip
                    WHERE ip.id_producto = p.id_producto AND ip.activo = TRUE
                    ORDER BY ip.es_principal DESC, ip.orden, ip.id_imagen LIMIT 1) AS imagen,
                   (SELECT string_agg(pp.nombre, ' · ' ORDER BY pp.orden, pp.cantidad_gramos)
                    FROM producto_presentaciones pp
                    WHERE pp.id_producto = p.id_producto AND pp.activo = TRUE) AS presentaciones_resumen
            FROM productos p
            INNER JOIN categorias c ON c.id_categoria = p.id_categoria
            LEFT JOIN marcas m ON m.id_marca = p.id_marca
            LEFT JOIN stock s ON s.id_producto = p.id_producto
            WHERE " . implode(' AND ', $where) . '
            ORDER BY p.nombre ASC';
    $statement = $pdo->prepare($sql);
    foreach ($bindings as $key => $value) {
        $statement->bindValue(':' . $key, $value, is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
    }
    $statement->execute();

    return array_map('enriquecerProductoPublico', $statement->fetchAll());
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

function obtenerPresentacionesPublicasProducto(PDO $pdo, int $idProducto): array
{
    $statement = $pdo->prepare(
        "SELECT pp.nombre, pp.cantidad_gramos, pp.precio_venta, pp.sku,
                CASE WHEN UPPER(p.detalles_opcionales->>'subcategoria') = 'ALIMENTO SECO'
                     THEN EXISTS(SELECT 1 FROM stock_lotes sl
                                 WHERE sl.id_producto=pp.id_producto AND sl.activo=TRUE
                                   AND sl.fecha_vencimiento>=CURRENT_DATE
                                   AND sl.cantidad_disponible_g>=pp.cantidad_gramos)
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

function obtenerFiltrosCatalogoPublico(PDO $pdo): array
{
    return [
        'categorias' => $pdo->query("SELECT id_categoria AS id, nombre FROM categorias WHERE activo = TRUE ORDER BY orden, nombre")->fetchAll(),
        'marcas' => $pdo->query("SELECT id_marca AS id, nombre FROM marcas WHERE activo = TRUE ORDER BY nombre")->fetchAll(),
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
                a.imagen_portada, a.video_url, a.autor_publico, a.fecha_publicacion,
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
