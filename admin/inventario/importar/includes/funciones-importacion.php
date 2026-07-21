<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/productos/includes/funciones-producto.php';
require_once dirname(__DIR__, 4) . '/shared/funciones-stock-fraccionado.php';
require_once __DIR__ . '/xlsx.php';

const IMPORT_MAX_BYTES = 5242880;
const IMPORT_MAX_ROWS = 2000;
const IMPORT_SESSION_KEY = 'inventario_importacion';

const PRODUCT_COLUMNS = [
    'nombre',
    'sku',
    'codigo_barras',
    'categoria',
    'marca',
    'tipo_mascota',
    'precio_venta',
    'stock_inicial',
    'subcategoria',
    'descripcion',
    'ingredientes_materiales',
    'analisis_caracteristicas',
    'etapa_vida_tamano',
    'pais_origen',
    'fraccionadora_importador',
    'datos_reglamentarios',
    'estado',
];
const PRESENTATION_COLUMNS = [
    'sku_producto_base',
    'nombre_presentacion',
    'cantidad_gramos',
    'precio_venta',
    'sku_presentacion',
    'orden',
    'activo',
];

const PRODUCT_COLUMN_ALIASES = [
    'nombre' => ['nombre', 'nombre_producto', 'nombre_del_producto'],
    'sku' => ['sku'],
    'codigo_barras' => ['codigo_barras', 'codigo', 'codigo_de_barras'],
    'categoria' => ['categoria'],
    'marca' => ['marca'],
    'tipo_mascota' => ['tipo_mascota', 'mascota', 'tipo_de_mascota'],
    'precio_venta' => ['precio_venta', 'precio_venta_clp', 'precio_de_venta', 'precio_de_venta_clp'],
    'stock_inicial' => ['stock_inicial'],
    'subcategoria' => ['subcategoria'],
    'descripcion' => ['descripcion', 'descripcion_comercial'],
    'ingredientes_materiales' => ['ingredientes_materiales', 'ingredientes_material', 'ingredientes'],
    'analisis_caracteristicas' => ['analisis_caracteristicas', 'analisis_garantizado_caracteristicas', 'analisis'],
    'etapa_vida_tamano' => ['etapa_vida_tamano', 'etapa_de_vida_tamano', 'etapa_vida', 'tamano'],
    'pais_origen' => ['pais_origen', 'pais_de_origen'],
    'fraccionadora_importador' => ['fraccionadora_importador', 'fraccionadora', 'importador'],
    'datos_reglamentarios' => ['datos_reglamentarios'],
    'estado' => ['estado', 'estado_producto'],
];

const PRESENTATION_COLUMN_ALIASES = [
    'sku_producto_base' => ['sku_producto_base', 'sku_base'],
    'nombre_presentacion' => ['nombre_presentacion', 'presentacion', 'nombre_de_presentacion'],
    'cantidad_gramos' => ['cantidad_gramos', 'gramos', 'cantidad_en_gramos'],
    'precio_venta' => ['precio_venta', 'precio_venta_clp', 'precio_de_venta_clp', 'precio_presentacion'],
    'sku_presentacion' => ['sku_presentacion'],
    'orden' => ['orden'],
    'activo' => ['activo'],
];

function normalizarClaveImportacion(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = strtr($value, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n',
    ]);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $ascii === false ? $value : $ascii) ?: '';

    return trim($normalized, '_');
}

function enteroImportacion(string $value, int $minimum = 0): ?int
{
    if (preg_match('/^\d+$/', trim($value)) !== 1) {
        return null;
    }
    $integer = filter_var($value, FILTER_VALIDATE_INT);
    return $integer !== false && $integer >= $minimum ? $integer : null;
}

function formatearPrecioImportacion(int|string $value): string
{
    return '$' . number_format((int) $value, 0, ',', '.');
}

function formatearStockImportacion(int $quantity, bool $fractionable): string
{
    return formatearCantidadStock($quantity, $fractionable);
}

function formatearEstadoImportacion(string $status): string
{
    return mb_strtolower($status) === 'activo' ? 'Activo' : 'Inactivo';
}

function textoTipoProductoImportacion(bool $fractionable): string
{
    return $fractionable ? 'Alimento fraccionable' : 'Producto por unidad';
}

function etiquetaTipoMascotaImportacion(string $value): string
{
    $normalized = mb_strtolower(trim($value));

    return [
        'perro' => 'Perro',
        'gato' => 'Gato',
        'ambos' => 'Ambos',
        'otro' => 'Otro',
    ][$normalized] ?? ucfirst($normalized);
}

function codigoErrorImportacion(): string
{
    return 'IMP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function resumirErrorImportacion(Throwable $exception): string
{
    $message = preg_replace('/\s+/', ' ', trim($exception->getMessage())) ?? '';
    $message = preg_replace('/(password|passwd|pwd)\s*[=:]\s*[^\s;]+/i', '$1=[oculto]', $message) ?? $message;
    $message = preg_replace('/\b(?:postgres(?:ql)?|mysql):[^\s]+/i', '[conexión oculta]', $message) ?? $message;
    if ($message === '') {
        return 'Error interno sin detalle adicional.';
    }

    return mb_strlen($message) > 240 ? mb_substr($message, 0, 237) . '...' : $message;
}

function aliasColumnasImportacion(string $sheetName): array
{
    return $sheetName === 'Presentaciones' ? PRESENTATION_COLUMN_ALIASES : PRODUCT_COLUMN_ALIASES;
}

function posicionesEncabezadoImportacion(array $row, array $aliases): array
{
    $aliasToColumn = [];
    foreach ($aliases as $column => $columnAliases) {
        foreach ($columnAliases as $alias) {
            $aliasToColumn[normalizarClaveImportacion($alias)] = $column;
        }
    }

    $positions = [];
    foreach ($row as $position => $header) {
        $column = $aliasToColumn[normalizarClaveImportacion((string) $header)] ?? null;
        if ($column !== null && !isset($positions[$column])) {
            $positions[$column] = $position;
        }
    }

    return $positions;
}

function filasConEncabezadoImportacion(array $rows, array $expectedColumns, string $sheetName, array &$generalErrors): array
{
    if ($rows === []) {
        $generalErrors[] = "La hoja {$sheetName} esta vacia.";
        return [];
    }
    $aliases = aliasColumnasImportacion($sheetName);
    $headerRowNumber = null;
    $positions = [];
    $bestScore = 0;
    foreach ($rows as $rowNumber => $row) {
        $candidatePositions = posicionesEncabezadoImportacion($row, $aliases);
        $score = count($candidatePositions);
        if ($score > $bestScore) {
            $bestScore = $score;
            $headerRowNumber = $rowNumber;
            $positions = $candidatePositions;
        }
        if ($score === count($expectedColumns)) {
            break;
        }
    }

    $minimumMatches = max(3, (int) ceil(count($expectedColumns) / 2));
    if ($headerRowNumber === null || $bestScore < $minimumMatches) {
        $generalErrors[] = "No se encontro la fila de encabezados de la hoja {$sheetName}.";
        return [];
    }

    $missing = array_values(array_diff($expectedColumns, array_keys($positions)));
    if ($missing !== []) {
        $generalErrors[] = "La hoja {$sheetName} no contiene las columnas: " . implode(', ', $missing) . '.';
        return [];
    }
    $result = [];
    foreach ($rows as $rowNumber => $row) {
        if ($rowNumber <= $headerRowNumber) {
            continue;
        }
        $mapped = [];
        foreach ($expectedColumns as $column) {
            $mapped[$column] = trim((string) ($row[$positions[$column]] ?? ''));
        }
        if (implode('', $mapped) === '') {
            continue;
        }
        $result[] = ['fila' => $rowNumber, 'datos' => $mapped];
    }
    if (count($result) > IMPORT_MAX_ROWS) {
        $generalErrors[] = "La hoja {$sheetName} supera el limite de " . IMPORT_MAX_ROWS . ' filas.';
        return [];
    }
    return $result;
}

function cargarCatalogosImportacion(PDO $connection): array
{
    $categories = $connection->query('SELECT id_categoria, nombre, slug, maneja_fraccionamiento FROM categorias WHERE activo = TRUE')->fetchAll();
    $brands = $connection->query('SELECT id_marca, nombre FROM marcas WHERE activo = TRUE')->fetchAll();
    $products = $connection->query('SELECT p.id_producto, p.sku, p.codigo_barras, c.maneja_fraccionamiento FROM productos p INNER JOIN categorias c ON c.id_categoria = p.id_categoria')->fetchAll();
    $presentationSkus = $connection->query("SELECT sku FROM producto_presentaciones WHERE sku IS NOT NULL AND TRIM(sku) <> ''")->fetchAll(PDO::FETCH_COLUMN);

    $catalogs = ['categorias' => [], 'marcas' => [], 'productos_sku' => [], 'codigos' => [], 'presentaciones_sku' => []];
    foreach ($categories as $category) {
        foreach ([$category['nombre'], $category['slug']] as $key) {
            $catalogs['categorias'][normalizarClaveImportacion((string) $key)] = $category;
        }
    }
    foreach ($brands as $brand) {
        $catalogs['marcas'][normalizarClaveImportacion((string) $brand['nombre'])] = $brand;
    }
    foreach ($products as $product) {
        if ($product['sku'] !== null && trim((string) $product['sku']) !== '') {
            $catalogs['productos_sku'][mb_strtolower(trim((string) $product['sku']))] = $product;
        }
        if ($product['codigo_barras'] !== null && trim((string) $product['codigo_barras']) !== '') {
            $catalogs['codigos'][trim((string) $product['codigo_barras'])] = true;
        }
    }
    foreach ($presentationSkus as $sku) {
        $catalogs['presentaciones_sku'][mb_strtolower(trim((string) $sku))] = true;
    }
    return $catalogs;
}

function validarLibroImportacion(PDO $connection, array $sheets): array
{
    $generalErrors = []; //lista vacía
    foreach (['Productos', 'Presentaciones'] as $requiredSheet) {
        if (!array_key_exists($requiredSheet, $sheets)) {
            $generalErrors[] = "Falta una hoja requerida :{$requiredSheet}.";
        }
    }
    if ($generalErrors !== []) {
        return resultadoImportacion([], [], $generalErrors, [], []);
    }
    $productRows = filasConEncabezadoImportacion($sheets['Productos'], PRODUCT_COLUMNS, 'Productos', $generalErrors);
    $presentationRows = filasConEncabezadoImportacion($sheets['Presentaciones'], PRESENTATION_COLUMNS, 'Presentaciones', $generalErrors);
    if ($generalErrors !== []) {
        return resultadoImportacion([], [], $generalErrors, $productRows, $presentationRows);
    }

    $catalogs = cargarCatalogosImportacion($connection);
    $products = [];
    $productErrors = [];
    $seenSkus = [];
    $seenBarcodes = [];
    foreach ($productRows as $row) {
        $data = $row['datos'];
        $errors = [];
        if (mb_strlen($data['nombre']) < 2 || mb_strlen($data['nombre']) > 180)
            $errors[] = 'nombre debe tener entre 2 y 180 caracteres.';
        $skuKey = mb_strtolower($data['sku']);
        if ($skuKey !== '' && (isset($seenSkus[$skuKey]) || isset($catalogs['productos_sku'][$skuKey])))
            $errors[] = 'SKU duplicado en el Excel o en la base de datos.';
        if ($skuKey !== '')
            $seenSkus[$skuKey] = true;
        if ($data['codigo_barras'] !== '' && (isset($seenBarcodes[$data['codigo_barras']]) || isset($catalogs['codigos'][$data['codigo_barras']])))
            $errors[] = 'codigo_barras duplicado en el Excel o en la base de datos.';
        if ($data['codigo_barras'] !== '')
            $seenBarcodes[$data['codigo_barras']] = true;
        $category = $catalogs['categorias'][normalizarClaveImportacion($data['categoria'])] ?? null;
        if ($category === null)
            $errors[] = 'La categoria no existe o esta inactiva.';
        $brand = $catalogs['marcas'][normalizarClaveImportacion($data['marca'])] ?? null;
        if ($brand === null)
            $errors[] = 'La marca no existe o esta inactiva.';
        $petType = mb_strtolower(trim($data['tipo_mascota']));
        if (!in_array($petType, ['perro', 'gato', 'ambos', 'otro'], true))
            $errors[] = 'tipo_mascota no es valido.';
        $stock = enteroImportacion($data['stock_inicial']);
        if ($stock === null)
            $errors[] = 'stock_inicial debe ser un entero mayor o igual a 0, expresado sin unidades.';
        $fractionable = $category !== null && valorBooleanoPostgres($category['maneja_fraccionamiento']);
        $price = $fractionable ? 0 : enteroImportacion($data['precio_venta']);
        if (!$fractionable && $price === null)
            $errors[] = 'precio_venta es obligatorio y debe ser un entero mayor o igual a 0.';
        $status = $data['estado'] === '' ? 'activo' : mb_strtolower($data['estado']);
        if (!in_array($status, ['activo', 'inactivo'], true))
            $errors[] = 'estado debe ser activo o inactivo.';
        foreach (['subcategoria' => 100, 'pais_origen' => 100, 'fraccionadora_importador' => 180] as $field => $max) {
            if (mb_strlen($data[$field]) > $max)
                $errors[] = "{$field} supera {$max} caracteres.";
        }
        if ($errors !== []) {
            foreach ($errors as $error)
                $productErrors[] = ['fila' => $row['fila'], 'mensaje' => $error];
            continue;
        }
        $products[$skuKey !== '' ? $skuKey : '__row_' . $row['fila']] = [
            ...$data,
            'fila' => $row['fila'],
            'id_categoria' => (int) $category['id_categoria'],
            'id_marca' => (int) $brand['id_marca'],
            'fraccionable' => $fractionable,
            'tipo_mascota' => $petType,
            'precio_venta' => (int) $price,
            'stock_inicial' => (int) $stock,
            'estado' => $status,
        ];
    }

    $presentations = [];
    $presentationErrors = [];
    $seenPresentationSkus = [];
    foreach ($presentationRows as $row) {
        $data = $row['datos'];
        $errors = [];
        $baseKey = mb_strtolower($data['sku_producto_base']);
        $base = $products[$baseKey] ?? $catalogs['productos_sku'][$baseKey] ?? null;
        if ($baseKey === '' || $base === null)
            $errors[] = 'sku_producto_base no existe en Productos ni en la base de datos.';
        elseif (!valorBooleanoPostgres($base['fraccionable'] ?? $base['maneja_fraccionamiento'] ?? false))
            $errors[] = 'Las presentaciones solo aplican a productos fraccionables.';
        if (mb_strlen($data['nombre_presentacion']) < 3 || mb_strlen($data['nombre_presentacion']) > 120)
            $errors[] = 'nombre_presentacion debe tener entre 3 y 120 caracteres.';
        $grams = enteroImportacion($data['cantidad_gramos'], 1);
        if ($grams === null)
            $errors[] = 'cantidad_gramos debe ser un entero mayor que 0.';
        $price = enteroImportacion($data['precio_venta']);
        if ($price === null)
            $errors[] = 'precio_venta debe ser un entero mayor o igual a 0.';
        $presentationSkuKey = mb_strtolower($data['sku_presentacion']);
        if ($presentationSkuKey !== '' && (isset($seenPresentationSkus[$presentationSkuKey]) || isset($catalogs['presentaciones_sku'][$presentationSkuKey])))
            $errors[] = 'sku_presentacion duplicado en el Excel o en la base de datos.';
        if ($presentationSkuKey !== '')
            $seenPresentationSkus[$presentationSkuKey] = true;
        $order = $data['orden'] === '' ? 0 : enteroImportacion($data['orden']);
        if ($order === null)
            $errors[] = 'orden debe ser un entero mayor o igual a 0.';
        $activeValue = $data['activo'] === '' ? 'si' : normalizarClaveImportacion($data['activo']);
        if (!in_array($activeValue, ['si', 'no', 'true', 'false', '1', '0'], true))
            $errors[] = 'activo debe ser si, no, true, false, 1 o 0.';
        if ($errors !== []) {
            foreach ($errors as $error)
                $presentationErrors[] = ['fila' => $row['fila'], 'mensaje' => $error];
            continue;
        }
        $presentations[] = [
            ...$data,
            'fila' => $row['fila'],
            'sku_producto_base' => $baseKey,
            'cantidad_gramos' => (int) $grams,
            'precio_venta' => (int) $price,
            'orden' => (int) $order,
            'activo' => in_array($activeValue, ['si', 'true', '1'], true),
            'producto_existente' => isset($catalogs['productos_sku'][$baseKey]) && !isset($products[$baseKey]),
        ];
    }

    return resultadoImportacion(array_values($products), $presentations, $generalErrors, $productRows, $presentationRows, $productErrors, $presentationErrors);
}

function resultadoImportacion(array $products, array $presentations, array $generalErrors, array $productRows, array $presentationRows, array $productErrors = [], array $presentationErrors = []): array
{
    return [
        'productos' => $products,
        'presentaciones' => $presentations,
        'errores_generales' => $generalErrors,
        'errores_productos' => $productErrors,
        'errores_presentaciones' => $presentationErrors,
        'resumen' => [
            'productos_detectados' => count($productRows),
            'productos_validos' => count($products),
            'presentaciones_detectadas' => count($presentationRows),
            'presentaciones_validas' => count($presentations),
            'errores' => count($generalErrors) + count($productErrors) + count($presentationErrors),
        ],
    ];
}

function directorioTemporalImportacion(): string
{
    $directory = dirname(__DIR__, 4) . '/storage/importaciones';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('No fue posible preparar el almacenamiento temporal.');
    }
    return $directory;
}

function guardarImportacionValidada(array $result): string
{
    eliminarImportacionTemporal();
    $token = bin2hex(random_bytes(24));
    $path = directorioTemporalImportacion() . '/' . $token . '.json';
    file_put_contents($path, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
    $_SESSION[IMPORT_SESSION_KEY] = ['token' => $token, 'creado' => time()];
    return $token;
}

function cargarImportacionValidada(string $token): ?array
{
    $state = $_SESSION[IMPORT_SESSION_KEY] ?? null;
    if (!is_array($state) || !hash_equals((string) ($state['token'] ?? ''), $token) || time() - (int) ($state['creado'] ?? 0) > 3600)
        return null;
    $path = directorioTemporalImportacion() . '/' . $token . '.json';
    if (!is_file($path))
        return null;
    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function eliminarImportacionTemporal(): void
{
    $state = $_SESSION[IMPORT_SESSION_KEY] ?? null;
    if (is_array($state) && preg_match('/^[a-f0-9]{48}$/', (string) ($state['token'] ?? '')) === 1) {
        $path = directorioTemporalImportacion() . '/' . $state['token'] . '.json';
        if (is_file($path))
            @unlink($path);
    }
    unset($_SESSION[IMPORT_SESSION_KEY]);
}
