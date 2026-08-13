<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-lotes.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/validaciones-producto.php';
require_once __DIR__ . '/includes/funciones-producto.php';
require_once __DIR__ . '/imagenes/includes/funciones-imagenes-producto.php';
require_once dirname(__DIR__, 2) . '/importaciones/drive/includes/funciones-drive.php';

requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

/**
 * Convierte la estructura múltiple de $_FILES en archivos individuales.
 *
 * @return array<int, array{name:mixed,type:mixed,tmp_name:mixed,error:mixed,size:mixed}>
 */
function normalizarImagenesProductoCreacion(mixed $files): array
{
    if (!is_array($files) || !is_array($files['name'] ?? null)) {
        return [];
    }

    $normalized = [];

    foreach (array_keys($files['name']) as $index) {
        $error = $files['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ((int) $error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

$formUrl = appUrl('admin/inventario/productos/crear.php');
[$values, $errors] = validarDatosProducto($_POST);
$values['id_proveedor'] = trim((string) ($_POST['id_proveedor'] ?? ''));
$imageAltText = normalizarTextoAlternativoImagen($_POST['imagen_alt_text'] ?? null);
$values['imagen_alt_text'] = $imageAltText ?? '';

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
$validatedImages = [];
$imageTemporaries = [];

try {
    $imageFiles = normalizarImagenesProductoCreacion(
        $_FILES['imagenes_producto'] ?? null
    );

    if (count($imageFiles) > IMAGEN_PRODUCTO_MAX_CANTIDAD) {
        throw new ImagenProductoException(
            'Puedes seleccionar un máximo de 5 imágenes por producto.'
        );
    }

    foreach ($imageFiles as $imageFile) {
        $originalName = basename(trim((string) ($imageFile['name'] ?? '')));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($extension, ['heic', 'heif'], true)) {
            $uploadedPath = is_string($imageFile['tmp_name'] ?? null) ? $imageFile['tmp_name'] : '';
            $validatedHeic = validarDescargaImagenDrive($uploadedPath, [
                'nombre' => $originalName,
                'extension' => $extension,
                'tamano_declarado' => isset($imageFile['size']) ? (int) $imageFile['size'] : null,
            ]);
            $converted = convertirHeicAWebpDrive($uploadedPath, $imageTemporaries);
            $validatedImages[] = [
                'tipo' => 'archivo_local',
                'temporal' => $converted['temporal_final'],
                'extension' => $converted['extension_final'],
                'mime' => $converted['mime_final'],
                'nombre_original' => $validatedHeic['nombre_original'],
            ];
            continue;
        }

        $validatedImages[] = [
            'tipo' => 'subida',
            ...validarArchivoImagenProducto($imageFile),
        ];
    }
} catch (ImagenProductoException|DriveImageException $exception) {
    limpiarTemporalesDrive($imageTemporaries);
    guardarEstadoFormularioProducto(
        $values,
        ['imagenes_producto' => $exception->getMessage()]
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}

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
    $fractionable = aplicarReglaSubcategoriaProducto($connection, $values, $errors, $category);
    aplicarReglaEnergiaMetabolizableProducto($values, $errors, $category);
    $lotes = normalizarLotesFormulario($_POST['lotes'] ?? []);
    if ($fractionable) {
        $lotErrors = validarLotesStock($lotes);
        $stockFromLots = calcularStockInicialLotesProducto($lotes);
        if ($lotes === [] || $stockFromLots <= 0) {
            $lotErrors['lotes'] = 'Ingresa al menos un lote con cantidad válida.';
        }
        $errors += $lotErrors;
        $values['lotes'] = $lotes;
        $values['_stock_inicial_lotes'] = $stockFromLots;
        $values['_stock_inicial_entero'] = (int) round($stockFromLots);
    }
    validarProductoPorCategoria($values, $errors, $fractionable, false);
    if ($fractionable) {
        $values['formato'] = '';
        $values['peso_contenido'] = '';
        $values['unidad'] = '';
        $values['fraccionadora_importador'] = '';
    }
    $supplierId = null;
    if ($fractionable && trim((string) ($_POST['id_proveedor'] ?? '')) !== '') {
        $supplierId = filter_var($_POST['id_proveedor'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($supplierId === false) {
            $errors['id_proveedor'] = 'Selecciona un proveedor válido.';
            $supplierId = null;
        } else {
            $supplierCheck = $connection->prepare('SELECT 1 FROM proveedores WHERE id_proveedor=:id AND activo=TRUE');
            $supplierCheck->execute(['id' => $supplierId]);
            if ($supplierCheck->fetchColumn() === false) {
                $errors['id_proveedor'] = 'El proveedor seleccionado no está activo.';
                $supplierId = null;
            } else {
                $supplierId = (int) $supplierId;
                $values['id_proveedor'] = (string) $supplierId;
            }
        }
    }
    validarCamposFormatoProducto($values, $errors);

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
        limpiarTemporalesDrive($imageTemporaries);
        guardarEstadoFormularioProducto($values, $errors);
        header('Location: ' . $formUrl, true, 303);
        exit;
    }

    $slug = generarSlugUnico($connection, $values['nombre']);
    $detailsJson = json_encode(
        (object) construirDetallesOpcionales($values),
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    // En alimento seco, todo el peso vigente de los lotes es stock vendible.
    $stockInitial = (int) $values['_stock_inicial_entero'];
    $minimumStock = $fractionable
        ? 5000
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

    if ($fractionable) {
        if ($supplierId !== null) {
            $connection->prepare('INSERT INTO proveedor_productos (id_proveedor,id_producto,activo) VALUES (:proveedor,:producto,TRUE) ON CONFLICT (id_proveedor,id_producto) DO UPDATE SET activo=TRUE')
                ->execute(['proveedor' => $supplierId, 'producto' => $productId]);
        }
        guardarLotesStock($connection, $productId, $lotes, $supplierId);
    }

    if ($stockInitial > 0) {
        $movementStatement = $connection->prepare(
            "INSERT INTO movimientos_stock (
                id_producto, id_usuario, tipo_movimiento, cantidad,
                stock_anterior, stock_final, origen, motivo
            ) VALUES (
                :id_producto, :id_usuario, 'carga_inicial', :cantidad,
                0, :stock_final, 'manual', :motivo
            )"
        );
        $movementStatement->execute([
            'id_producto' => $productId,
            'id_usuario' => (int) $_SESSION['id_usuario'],
            'cantidad' => $stockInitial,
            'stock_final' => $stockInitial,
            'motivo' => $fractionable ? 'Carga inicial desde lotes' : 'Registro manual del producto',
        ]);
    }

    $connection->commit();
    $imagesSaved = 0;
    $imageWarnings = [];

    foreach ($validatedImages as $validatedImage) {
        try {
            if (($validatedImage['tipo'] ?? '') === 'archivo_local') {
                guardarImagenProductoDesdeArchivoLocal(
                    $connection,
                    $productId,
                    $validatedImage['temporal'],
                    $validatedImage['nombre_original'],
                    $imageAltText,
                    false,
                    $validatedImage['mime'],
                    $validatedImage['extension']
                );
            } else {
                guardarImagenProductoValidada(
                    $connection,
                    $productId,
                    $validatedImage,
                    $imageAltText
                );
            }
            $imagesSaved++;
        } catch (Throwable $imageException) {
            $imageWarnings[] = registrarExcepcionAdmin(
                'Product created image upload error',
                $imageException
            );
        }
    }

    limpiarTemporalesDrive($imageTemporaries);

    $imageSaved = $imagesSaved > 0;
    $imageWarningReference = $imageWarnings[0] ?? null;
    if ($imageWarningReference !== null) {
        guardarModalAdmin(
            'warning',
            $fractionable ? 'Alimento creado sin imagen' : 'Producto creado sin imagen',
            $fractionable
                ? 'El alimento fue creado, pero una o más imágenes no pudieron subirse.'
                : 'El producto fue creado, pero una o más imágenes no pudieron subirse.',
            ['reference' => $imageWarningReference]
        );
    } else {
        guardarModalAdmin(
            'success',
            $fractionable ? 'Alimento creado' : 'Producto creado',
            $fractionable
                ? ($imageSaved
                    ? sprintf(
                        'El alimento fue registrado correctamente con %d imagen(es). La primera quedó como principal. Ahora puedes configurar sus presentaciones.',
                        $imagesSaved
                    )
                    : 'El alimento fue registrado correctamente. Ahora puedes configurar sus presentaciones.')
                : ($imageSaved
                    ? sprintf(
                        'El producto fue registrado correctamente con %d imagen(es). La primera quedó como principal.',
                        $imagesSaved
                    )
                    : 'El producto fue registrado correctamente.')
        );
    }
    $destination = $fractionable
        ? appUrl('admin/inventario/presentaciones/index.php?id_producto=' . $productId)
        : appUrl('admin/inventario/index.php');
    header('Location: ' . $destination, true, 303);
    exit;
} catch (Throwable $exception) {
    limpiarTemporalesDrive($imageTemporaries);
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }

    $message = $exception->getMessage();
    if ($exception->getCode() === '23505' && str_contains($message, 'productos_sku_unico')) {
        $errors['sku'] = 'Ya existe un producto con este SKU.';
    } elseif ($exception->getCode() === '23505' && str_contains($message, 'productos_codigo_barras_unico')) {
        $errors['codigo_barras'] = 'Ya existe un producto con este código de barras.';
    }

    $reference = registrarExcepcionAdmin('Product creation error', $exception);
    guardarEstadoFormularioProducto(
        $values,
        $errors,
        $errors === [] ? 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.' : null,
        'producto_formulario',
        $errors === [] ? $reference : null
    );
    header('Location: ' . $formUrl, true, 303);
    exit;
}
