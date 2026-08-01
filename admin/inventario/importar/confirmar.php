<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/funciones-importacion.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['inventario_importacion_error'] = 'La solicitud no es válida. Vuelve a revisar el archivo.';
    header('Location: ' . appUrl('admin/inventario/importar/index.php'), true, 303);
    exit;
}
$token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
$result = preg_match('/^[a-f0-9]{48}$/', $token) === 1 ? cargarImportacionValidada($token) : null;
if ($result === null || (int) ($result['resumen']['errores'] ?? 1) !== 0) {
    $_SESSION['inventario_importacion_error'] = 'La previsualización no es válida o contiene errores.';
    header('Location: ' . appUrl('admin/inventario/importar/index.php'), true, 303);
    exit;
}

$connection = null;
try {
    $connection = database();
    $connection->beginTransaction();
    $activeCategories = [];
    foreach ($connection->query('SELECT id_categoria, slug FROM categorias WHERE activo = TRUE')->fetchAll() as $category) {
        $activeCategories[(int) $category['id_categoria']] = $category;
    }
    $existingSlugs = array_fill_keys(array_map('strtolower', $connection->query('SELECT slug FROM productos')->fetchAll(PDO::FETCH_COLUMN)), true);
    $existingProducts = [];
    $fractionableProductIds = [];
    foreach ($connection->query("SELECT p.id_producto, LOWER(TRIM(p.sku)) AS sku, p.detalles_opcionales, c.slug AS categoria_slug FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria WHERE p.sku IS NOT NULL AND TRIM(p.sku) <> ''")->fetchAll() as $product) {
        $existingProducts[(string) $product['sku']] = (int) $product['id_producto'];
        if (esProductoFraccionable($product)) $fractionableProductIds[(int) $product['id_producto']] = true;
    }
    $productStatement = $connection->prepare("INSERT INTO productos (id_categoria, id_marca, nombre, slug, tipo_mascota, precio_venta, sku, codigo_barras, detalles_opcionales, estado) VALUES (:id_categoria, :id_marca, :nombre, :slug, :tipo_mascota, :precio_venta, :sku, :codigo_barras, CAST(:detalles AS jsonb), :estado) RETURNING id_producto");
    $stockStatement = $connection->prepare('INSERT INTO stock (id_producto, cantidad_actual, cantidad_reservada, stock_minimo) VALUES (:id_producto, :cantidad, 0, :stock_minimo)');
    $movementStatement = $connection->prepare("INSERT INTO movimientos_stock (id_producto, id_usuario, tipo_movimiento, cantidad, stock_anterior, stock_final, origen, motivo) VALUES (:id_producto, :id_usuario, 'carga_inicial', :cantidad, 0, :cantidad_final, 'manual', 'Importación Excel')");
    foreach ($result['productos'] as $product) {
        $category = $activeCategories[(int) $product['id_categoria']] ?? null;
        if ($category === null) throw new RuntimeException('La categoria del producto ya no existe o esta inactiva.');
        if (esCategoriaAlimentos($category) && trim((string) $product['subcategoria']) === '') {
            throw new RuntimeException('La subcategoria es obligatoria para los productos de Alimentos.');
        }
        $fractionable = esProductoFraccionable(['categoria_slug' => $category['slug'], 'subcategoria' => $product['subcategoria']]);
        $petType = mb_strtolower(trim((string) ($product['tipo_mascota'] ?? '')));
        if (!in_array($petType, ['perro', 'gato', 'ambos', 'otro'], true)) {
            throw new RuntimeException('tipo_mascota inválido para el producto ' . (string) ($product['nombre'] ?? 'sin nombre'));
        }
        $baseSlug = generarSlugBase((string) $product['nombre']);
        $slug = $baseSlug;
        for ($suffix = 2; isset($existingSlugs[strtolower($slug)]); $suffix++) $slug = $baseSlug . '-' . $suffix;
        $existingSlugs[strtolower($slug)] = true;
        $details = [];
        foreach (['subcategoria', 'descripcion', 'ingredientes_materiales', 'analisis_caracteristicas', 'etapa_vida_tamano', 'pais_origen', 'fraccionadora_importador', 'datos_reglamentarios'] as $field) {
            if ((string) $product[$field] !== '') $details[$field] = (string) $product[$field];
        }
        if (isset($details['subcategoria'])) {
            $details['subcategoria_codigo'] = codigoSubcategoriaProducto($details['subcategoria']);
        }
        $productStatement->execute([
            'id_categoria' => (int) $product['id_categoria'], 'id_marca' => (int) $product['id_marca'],
            'nombre' => (string) $product['nombre'], 'slug' => $slug, 'tipo_mascota' => $petType,
            'precio_venta' => (int) $product['precio_venta'], 'sku' => $product['sku'] === '' ? null : $product['sku'],
            'codigo_barras' => $product['codigo_barras'] === '' ? null : $product['codigo_barras'],
            'detalles' => json_encode((object) $details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'estado' => $product['estado'],
        ]);
        $productId = (int) $productStatement->fetchColumn();
        if ($fractionable) $fractionableProductIds[$productId] = true;
        if ((string) $product['sku'] !== '') $existingProducts[mb_strtolower(trim((string) $product['sku']))] = $productId;
        $stockStatement->execute(['id_producto' => $productId, 'cantidad' => (int) $product['stock_inicial'], 'stock_minimo' => $fractionable ? 5000 : 5]);
        if ((int) $product['stock_inicial'] > 0) {
            $movementStatement->execute(['id_producto' => $productId, 'id_usuario' => (int) $_SESSION['id_usuario'], 'cantidad' => (int) $product['stock_inicial'], 'cantidad_final' => (int) $product['stock_inicial']]);
        }
    }
    $presentationStatement = $connection->prepare('INSERT INTO producto_presentaciones (id_producto, nombre, cantidad_gramos, precio_venta, sku, activo, orden) VALUES (:id_producto, :nombre, :cantidad_gramos, :precio_venta, :sku, :activo, :orden)');
    foreach ($result['presentaciones'] as $presentation) {
        $productId = $existingProducts[(string) $presentation['sku_producto_base']] ?? null;
        if ($productId === null) throw new RuntimeException('No se encontró el producto base de una presentación.');
        if (!isset($fractionableProductIds[$productId])) throw new RuntimeException('Las presentaciones solo aplican a productos fraccionables.');
        $presentationStatement->execute([
            'id_producto' => $productId, 'nombre' => (string) $presentation['nombre_presentacion'],
            'cantidad_gramos' => (int) $presentation['cantidad_gramos'], 'precio_venta' => (int) $presentation['precio_venta'],
            'sku' => $presentation['sku_presentacion'] === '' ? null : $presentation['sku_presentacion'],
            'activo' => (bool) $presentation['activo'], 'orden' => (int) $presentation['orden'],
        ]);
    }
    registrarImportacionCompletada($connection, $result, (int) $_SESSION['id_usuario']);
    $connection->commit();
    eliminarImportacionTemporal();
    header('Location: ' . appUrl('admin/inventario/index.php?importado=1'), true, 303);
    exit;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) $connection->rollBack();
    $reference = codigoErrorImportacion();
    $technicalDetail = resumirErrorImportacion($exception);
    $trace = preg_replace('/\s+/', ' ', $exception->getTraceAsString()) ?? '';
    error_log(sprintf(
        '[%s] Excel import error: %s | %s:%d | trace: %s',
        $reference,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        mb_substr($trace, 0, 1200)
    ));
    $_SESSION['inventario_importacion_confirmacion_error'] = [
        'mensaje' => 'No fue posible completar la importación. No se insertó ningún producto.',
        'detalle' => $technicalDetail,
        'referencia' => $reference,
    ];
    header('Location: ' . appUrl('admin/inventario/importar/validar.php?token=' . $token), true, 303);
    exit;
}
