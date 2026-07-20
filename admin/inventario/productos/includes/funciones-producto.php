<?php

declare(strict_types=1);

function valoresInicialesProducto(): array
{
    return [
        'nombre' => '', 'id_categoria' => '', 'id_marca' => '', 'tipo_mascota' => '',
        'precio_venta' => '', 'stock_inicial' => '', 'unidad_stock_inicial' => 'unidad', 'sku' => '', 'codigo_barras' => '',
        'subcategoria' => '', 'formato' => '', 'peso_contenido' => '', 'unidad' => '',
        'stock_minimo' => '5', 'unidad_stock_minimo' => 'unidad', 'descripcion' => '', 'ingredientes_materiales' => '',
        'analisis_caracteristicas' => '', 'etapa_vida_tamano' => '', 'pais_origen' => '',
        'fraccionadora_importador' => '', 'datos_reglamentarios' => '', 'activo' => true,
        'cantidad_actual' => '0',
    ];
}

function obtenerOpcionesProducto(PDO $connection, ?int $currentCategoryId = null, ?int $currentBrandId = null): array
{
    $categorySql = 'SELECT id_categoria, nombre, maneja_fraccionamiento, activo FROM categorias WHERE activo = TRUE';
    $categoryParameters = [];
    if ($currentCategoryId !== null) {
        $categorySql .= ' OR id_categoria = :current_category_id';
        $categoryParameters['current_category_id'] = $currentCategoryId;
    }
    $categories = $connection->prepare($categorySql . ' ORDER BY orden, nombre');
    $categories->execute($categoryParameters);

    $brandSql = 'SELECT id_marca, nombre, activo FROM marcas WHERE activo = TRUE';
    $brandParameters = [];
    if ($currentBrandId !== null) {
        $brandSql .= ' OR id_marca = :current_brand_id';
        $brandParameters['current_brand_id'] = $currentBrandId;
    }
    $brands = $connection->prepare($brandSql . ' ORDER BY nombre');
    $brands->execute($brandParameters);

    return ['categorias' => $categories->fetchAll(), 'marcas' => $brands->fetchAll()];
}

function obtenerCategoriaProducto(PDO $connection, int $categoryId): ?array
{
    $statement = $connection->prepare(
        'SELECT id_categoria, maneja_fraccionamiento, activo FROM categorias WHERE id_categoria = :id_categoria'
    );
    $statement->execute(['id_categoria' => $categoryId]);
    $category = $statement->fetch();

    return is_array($category) ? $category : null;
}

function validarReferenciasProducto(PDO $connection, int $categoryId, int $brandId): array
{
    $statement = $connection->prepare(
        'SELECT
            EXISTS(SELECT 1 FROM categorias WHERE id_categoria = :category_id AND activo = TRUE) AS categoria_valida,
            EXISTS(SELECT 1 FROM marcas WHERE id_marca = :brand_id AND activo = TRUE) AS marca_valida'
    );
    $statement->execute(['category_id' => $categoryId, 'brand_id' => $brandId]);
    $result = $statement->fetch();

    return is_array($result) ? $result : ['categoria_valida' => false, 'marca_valida' => false];
}

function validarReferenciasProductoEdicion(
    PDO $connection,
    int $productId,
    int $categoryId,
    int $brandId
): array {
    $statement = $connection->prepare(
        'SELECT
            EXISTS(
                SELECT 1 FROM categorias
                WHERE id_categoria = :category_id
                AND (activo = TRUE OR id_categoria = (
                    SELECT id_categoria FROM productos WHERE id_producto = :category_product_id
                ))
            ) AS categoria_valida,
            EXISTS(
                SELECT 1 FROM marcas
                WHERE id_marca = :brand_id
                AND (activo = TRUE OR id_marca = (
                    SELECT id_marca FROM productos WHERE id_producto = :brand_product_id
                ))
            ) AS marca_valida'
    );
    $statement->execute([
        'category_id' => $categoryId,
        'category_product_id' => $productId,
        'brand_id' => $brandId,
        'brand_product_id' => $productId,
    ]);
    $result = $statement->fetch();

    return is_array($result) ? $result : ['categoria_valida' => false, 'marca_valida' => false];
}

function validarDuplicadosProducto(PDO $connection, ?string $sku, ?string $barcode, ?int $excludedId = null): array
{
    $skuExclusion = $excludedId === null ? '' : ' AND id_producto <> :sku_excluded_id';
    $barcodeExclusion = $excludedId === null ? '' : ' AND id_producto <> :barcode_excluded_id';
    $statement = $connection->prepare(
        'SELECT
            CASE WHEN CAST(:sku_check AS text) IS NULL THEN FALSE ELSE EXISTS(
                SELECT 1 FROM productos WHERE LOWER(TRIM(sku)) = LOWER(TRIM(:sku_value))' . $skuExclusion . '
            ) END AS sku_duplicado,
            CASE WHEN CAST(:barcode_check AS text) IS NULL THEN FALSE ELSE EXISTS(
                SELECT 1 FROM productos WHERE TRIM(codigo_barras) = TRIM(:barcode_value)' . $barcodeExclusion . '
            ) END AS codigo_duplicado'
    );
    $statement->execute([
        'sku_check' => $sku,
        'sku_value' => $sku,
        'barcode_check' => $barcode,
        'barcode_value' => $barcode,
        ...($excludedId === null ? [] : [
            'sku_excluded_id' => $excludedId,
            'barcode_excluded_id' => $excludedId,
        ]),
    ]);
    $result = $statement->fetch();

    return is_array($result) ? $result : ['sku_duplicado' => false, 'codigo_duplicado' => false];
}

function generarSlugBase(string $name): string
{
    $name = strtr($name, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $slug = strtolower($transliterated === false ? $name : $transliterated);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'producto';
}

function generarSlugUnico(PDO $connection, string $name, ?int $excludedId = null): string
{
    $base = generarSlugBase($name);
    $sql = "SELECT slug FROM productos WHERE (LOWER(slug) = :base OR LOWER(slug) LIKE :pattern ESCAPE '\\')";
    $parameters = ['base' => $base, 'pattern' => $base . '-%'];
    if ($excludedId !== null) {
        $sql .= ' AND id_producto <> :excluded_id';
        $parameters['excluded_id'] = $excludedId;
    }
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $existing = array_fill_keys(array_map('strtolower', $statement->fetchAll(PDO::FETCH_COLUMN)), true);

    if (!isset($existing[$base])) {
        return $base;
    }

    for ($suffix = 2; ; $suffix++) {
        $candidate = $base . '-' . $suffix;
        if (!isset($existing[$candidate])) {
            return $candidate;
        }
    }
}

function construirDetallesOpcionales(array $values): array
{
    $textFields = [
        'subcategoria', 'formato', 'descripcion', 'ingredientes_materiales',
        'analisis_caracteristicas', 'etapa_vida_tamano', 'pais_origen',
        'fraccionadora_importador', 'datos_reglamentarios',
    ];
    $details = [];

    foreach ($textFields as $field) {
        if ($values[$field] !== '') {
            $details[$field] = $values[$field];
        }
    }

    if ($values['peso_contenido'] !== '') {
        $details['peso_contenido'] = (float) $values['peso_contenido'];
    }
    if ($values['unidad'] !== '') {
        $details['unidad'] = $values['unidad'];
    }

    return $details;
}

function valoresEdicionProducto(array $product): array
{
    $details = json_decode((string) $product['detalles_opcionales'], true, 512, JSON_THROW_ON_ERROR);
    $recognizedDetails = array_intersect_key(
        is_array($details) ? $details : [],
        array_flip([
            'subcategoria', 'formato', 'peso_contenido', 'unidad', 'descripcion',
            'ingredientes_materiales', 'analisis_caracteristicas', 'etapa_vida_tamano',
            'pais_origen', 'fraccionadora_importador', 'datos_reglamentarios',
        ])
    );

    return array_merge(valoresInicialesProducto(), $recognizedDetails, [
        'nombre' => (string) $product['nombre'],
        'id_categoria' => (string) $product['id_categoria'],
        'id_marca' => (string) $product['id_marca'],
        'tipo_mascota' => (string) $product['tipo_mascota'],
        'sku' => $product['sku'] === null ? '' : (string) $product['sku'],
        'codigo_barras' => $product['codigo_barras'] === null ? '' : (string) $product['codigo_barras'],
        'precio_venta' => (string) ((int) $product['precio_venta']),
        'stock_minimo' => (string) $product['stock_minimo'],
        'unidad_stock_minimo' => esProductoFraccionable($product) ? 'g' : 'unidad',
        'cantidad_actual' => (string) $product['cantidad_actual'],
        'activo' => $product['estado'] === 'activo',
    ]);
}

function idPositivoProducto(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function guardarEstadoFormularioProducto(
    array $values,
    array $errors,
    ?string $generalError = null,
    string $key = 'producto_formulario'
): void
{
    $_SESSION[$key] = [
        'valores' => $values,
        'errores' => $errors,
        'error_general' => $generalError,
    ];
}

function consumirEstadoFormularioProducto(string $key = 'producto_formulario'): array
{
    $state = $_SESSION[$key] ?? [];
    unset($_SESSION[$key]);

    return is_array($state) ? $state : [];
}

function valorBooleanoPostgres(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 't', 'true'], true);
}
