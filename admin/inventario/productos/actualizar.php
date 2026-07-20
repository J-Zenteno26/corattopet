<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once __DIR__ . '/includes/validaciones-producto.php';
require_once __DIR__ . '/includes/funciones-producto.php';
require_once __DIR__ . '/consultas/buscar-producto.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$productId = idPositivoProducto($_POST['id_producto'] ?? null);
if ($productId === null) {
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=no_encontrado'), true, 303);
    exit;
}

$stateKey = 'producto_editar_' . $productId;
$formUrl = appUrl('admin/inventario/productos/editar.php?id=' . $productId);
[$values, $errors] = validarDatosProducto($_POST, true);

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoFormularioProducto(
        $values,
        [],
        'La solicitud no es válida. Recarga el formulario e intenta nuevamente.',
        $stateKey
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}

if ($errors !== []) {
    guardarEstadoFormularioProducto($values, $errors, null, $stateKey);
    header('Location: ' . $formUrl, true, 303);
    exit;
}

$connection = null;

try {
    $connection = database();
    $product = buscarProductoParaEditar($connection, $productId);
    if ($product === null) {
        header('Location: ' . appUrl('admin/inventario/index.php?mensaje=no_encontrado'), true, 303);
        exit;
    }
    $category = obtenerCategoriaProducto($connection, (int) $values['id_categoria']);
    $fractionable = $category !== null && esProductoFraccionable($category);
    validarProductoPorCategoria($values, $errors, $fractionable, true);
    if ($fractionable) {
        $values['formato'] = '';
        $values['peso_contenido'] = '';
        $values['unidad'] = '';
    } else {
        validarCamposFormatoProducto($values, $errors);
    }

    $references = validarReferenciasProductoEdicion(
        $connection,
        $productId,
        (int) $values['id_categoria'],
        (int) $values['id_marca']
    );
    if (!valorBooleanoPostgres($references['categoria_valida'] ?? false)) {
        $errors['id_categoria'] = 'Selecciona una categoría válida.';
    }
    if (!valorBooleanoPostgres($references['marca_valida'] ?? false)) {
        $errors['id_marca'] = 'Selecciona una marca válida.';
    }

    $sku = $values['sku'] === '' ? null : $values['sku'];
    $barcode = $values['codigo_barras'] === '' ? null : $values['codigo_barras'];
    $duplicates = validarDuplicadosProducto($connection, $sku, $barcode, $productId);
    if (valorBooleanoPostgres($duplicates['sku_duplicado'] ?? false)) {
        $errors['sku'] = 'Ya existe un producto con este SKU.';
    }
    if (valorBooleanoPostgres($duplicates['codigo_duplicado'] ?? false)) {
        $errors['codigo_barras'] = 'Ya existe un producto con este código de barras.';
    }

    if ($errors !== []) {
        guardarEstadoFormularioProducto($values, $errors, null, $stateKey);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $slug = generarSlugUnico($connection, $values['nombre'], $productId);
    $detailsJson = json_encode(
        (object) construirDetallesOpcionales($values),
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    $minimumStock = $values['stock_minimo'] === '' ? ($fractionable ? 0 : 5) : (int) $values['_stock_minimo_entero'];

    $connection->beginTransaction();

    $productStatement = $connection->prepare(
        'UPDATE productos SET
            id_categoria = :id_categoria,
            id_marca = :id_marca,
            nombre = :nombre,
            slug = :slug,
            tipo_mascota = :tipo_mascota,
            precio_venta = :precio_venta,
            sku = :sku,
            codigo_barras = :codigo_barras,
            detalles_opcionales = CAST(:detalles_opcionales AS jsonb),
            estado = :estado,
            actualizado_en = CURRENT_TIMESTAMP
        WHERE id_producto = :id_producto'
    );
    $productStatement->execute([
        'id_categoria' => (int) $values['id_categoria'],
        'id_marca' => (int) $values['id_marca'],
        'nombre' => $values['nombre'],
        'slug' => $slug,
        'tipo_mascota' => $values['tipo_mascota'],
        'precio_venta' => (int) $values['_precio_venta_entero'],
        'sku' => $sku,
        'codigo_barras' => $barcode,
        'detalles_opcionales' => $detailsJson,
        'estado' => $values['activo'] ? 'activo' : 'inactivo',
        'id_producto' => $productId,
    ]);

    $stockStatement = $connection->prepare(
        'UPDATE stock SET stock_minimo = :stock_minimo, actualizado_en = CURRENT_TIMESTAMP
        WHERE id_producto = :id_producto'
    );
    $stockStatement->execute(['stock_minimo' => $minimumStock, 'id_producto' => $productId]);
    if ($stockStatement->rowCount() !== 1) {
        throw new RuntimeException('Product stock record was not found.');
    }

    $connection->commit();
    header('Location: ' . appUrl('admin/inventario/index.php?mensaje=actualizado'), true, 303);
    exit;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    $message = $exception->getMessage();
    if ($exception->getCode() === '23505' && str_contains($message, 'productos_sku_unico')) {
        $errors['sku'] = 'Ya existe un producto con este SKU.';
    } elseif ($exception->getCode() === '23505' && str_contains($message, 'productos_codigo_barras_unico')) {
        $errors['codigo_barras'] = 'Ya existe un producto con este código de barras.';
    }

    error_log('Product update error: ' . $message);
    guardarEstadoFormularioProducto(
        $values,
        $errors,
        $errors === [] ? 'No fue posible actualizar el producto. Intenta nuevamente.' : null,
        $stateKey
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}
