<?php

declare(strict_types=1);

function valoresInicialesProducto(): array
{
    return [
        'nombre' => '', 'id_categoria' => '', 'id_marca' => '', 'tipo_mascota' => '',
        'precio_venta' => '', 'stock_inicial' => '', 'sku' => '', 'codigo_barras' => '',
        'subcategoria' => '', 'formato' => '', 'peso_contenido' => '', 'unidad' => '',
        'stock_minimo' => '5', 'descripcion' => '', 'ingredientes_materiales' => '',
        'analisis_caracteristicas' => '', 'etapa_vida_tamano' => '', 'pais_origen' => '',
        'fraccionadora_importador' => '', 'datos_reglamentarios' => '',
    ];
}

function obtenerOpcionesProducto(PDO $connection): array
{
    $categories = $connection->prepare(
        'SELECT id_categoria, nombre FROM categorias WHERE activo = TRUE ORDER BY orden, nombre'
    );
    $categories->execute();

    $brands = $connection->prepare(
        'SELECT id_marca, nombre FROM marcas WHERE activo = TRUE ORDER BY nombre'
    );
    $brands->execute();

    return ['categorias' => $categories->fetchAll(), 'marcas' => $brands->fetchAll()];
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

function validarDuplicadosProducto(PDO $connection, ?string $sku, ?string $barcode): array
{
    $statement = $connection->prepare(
        'SELECT
            CASE WHEN CAST(:sku_check AS text) IS NULL THEN FALSE ELSE EXISTS(
                SELECT 1 FROM productos WHERE LOWER(TRIM(sku)) = LOWER(TRIM(:sku_value))
            ) END AS sku_duplicado,
            CASE WHEN CAST(:barcode_check AS text) IS NULL THEN FALSE ELSE EXISTS(
                SELECT 1 FROM productos WHERE TRIM(codigo_barras) = TRIM(:barcode_value)
            ) END AS codigo_duplicado'
    );
    $statement->execute([
        'sku_check' => $sku,
        'sku_value' => $sku,
        'barcode_check' => $barcode,
        'barcode_value' => $barcode,
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

function generarSlugUnico(PDO $connection, string $name): string
{
    $base = generarSlugBase($name);
    $statement = $connection->prepare(
        "SELECT slug FROM productos WHERE LOWER(slug) = :base OR LOWER(slug) LIKE :pattern ESCAPE '\\'"
    );
    $statement->execute(['base' => $base, 'pattern' => $base . '-%']);
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

function guardarEstadoFormularioProducto(array $values, array $errors, ?string $generalError = null): void
{
    $_SESSION['producto_formulario'] = [
        'valores' => $values,
        'errores' => $errors,
        'error_general' => $generalError,
    ];
}

function consumirEstadoFormularioProducto(): array
{
    $state = $_SESSION['producto_formulario'] ?? [];
    unset($_SESSION['producto_formulario']);

    return is_array($state) ? $state : [];
}

function valorBooleanoPostgres(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 't', 'true'], true);
}
