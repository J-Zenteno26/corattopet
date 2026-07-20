<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once __DIR__ . '/includes/validaciones-producto.php';
require_once __DIR__ . '/includes/funciones-producto.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$formUrl = appUrl('admin/inventario/productos/crear.php');
[$values, $errors] = validarDatosProducto($_POST);

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    guardarEstadoFormularioProducto($values, [], 'La solicitud no es válida. Recarga el formulario e intenta nuevamente.');
    header('Location: ' . $formUrl, true, 303);
    exit;
}

if ($errors !== []) {
    guardarEstadoFormularioProducto($values, $errors);
    header('Location: ' . $formUrl, true, 303);
    exit;
}

$connection = null;

try {
    $connection = database();
    $references = validarReferenciasProducto($connection, (int) $values['id_categoria'], (int) $values['id_marca']);
    $category = obtenerCategoriaProducto($connection, (int) $values['id_categoria']);
    if (!valorBooleanoPostgres($references['categoria_valida'] ?? false)) {
        $errors['id_categoria'] = 'Selecciona una categoría activa.';
    }
    if (!valorBooleanoPostgres($references['marca_valida'] ?? false)) {
        $errors['id_marca'] = 'Selecciona una marca activa.';
    }
    $fractionable = $category !== null && esProductoFraccionable($category);
    validarProductoPorCategoria($values, $errors, $fractionable, false);
    if ($fractionable) {
        $values['formato'] = '';
        $values['peso_contenido'] = '';
        $values['unidad'] = '';
    } else {
        validarCamposFormatoProducto($values, $errors);
    }

    $sku = $values['sku'] === '' ? null : $values['sku'];
    $barcode = $values['codigo_barras'] === '' ? null : $values['codigo_barras'];
    $duplicates = validarDuplicadosProducto($connection, $sku, $barcode);
    if (valorBooleanoPostgres($duplicates['sku_duplicado'] ?? false)) {
        $errors['sku'] = 'Ya existe un producto con este SKU.';
    }
    if (valorBooleanoPostgres($duplicates['codigo_duplicado'] ?? false)) {
        $errors['codigo_barras'] = 'Ya existe un producto con este código de barras.';
    }

    if ($errors !== []) {
        guardarEstadoFormularioProducto($values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $slug = generarSlugUnico($connection, $values['nombre']);
    $detailsJson = json_encode(
        (object) construirDetallesOpcionales($values),
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    $stockInitial = (int) $values['_stock_inicial_entero'];
    $minimumStock = $fractionable
        ? 0
        : ($values['stock_minimo'] === '' ? 5 : (int) $values['_stock_minimo_entero']);

    $connection->beginTransaction();

    $productStatement = $connection->prepare(
        "INSERT INTO productos (
            id_categoria, id_marca, nombre, slug, tipo_mascota, precio_venta,
            sku, codigo_barras, detalles_opcionales, estado
        ) VALUES (
            :id_categoria, :id_marca, :nombre, :slug, :tipo_mascota, :precio_venta,
            :sku, :codigo_barras, CAST(:detalles_opcionales AS jsonb), 'activo'
        ) RETURNING id_producto"
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
    ]);
    $productId = (int) $productStatement->fetchColumn();

    $stockStatement = $connection->prepare(
        'INSERT INTO stock (id_producto, cantidad_actual, cantidad_reservada, stock_minimo)
        VALUES (:id_producto, :cantidad_actual, 0, :stock_minimo)'
    );
    $stockStatement->execute([
        'id_producto' => $productId,
        'cantidad_actual' => $stockInitial,
        'stock_minimo' => $minimumStock,
    ]);

    if ($stockInitial > 0) {
        $movementStatement = $connection->prepare(
            "INSERT INTO movimientos_stock (
                id_producto, id_usuario, tipo_movimiento, cantidad,
                stock_anterior, stock_final, origen, motivo
            ) VALUES (
                :id_producto, :id_usuario, 'carga_inicial', :cantidad,
                0, :stock_final, 'manual', 'Registro manual del producto'
            )"
        );
        $movementStatement->execute([
            'id_producto' => $productId,
            'id_usuario' => (int) $_SESSION['id_usuario'],
            'cantidad' => $stockInitial,
            'stock_final' => $stockInitial,
        ]);
    }

    $connection->commit();
    $destination = $fractionable
        ? appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $productId . '&creado=1')
        : appUrl('admin/inventario/index.php?creado=1');
    header('Location: ' . $destination, true, 303);
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

    error_log('Product creation error: ' . $message);
    guardarEstadoFormularioProducto(
        $values,
        $errors,
        $errors === [] ? 'No fue posible guardar el producto. Intenta nuevamente.' : null
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}
