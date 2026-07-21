<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-stock-fraccionado.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 2) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-inventario.php';
require_once __DIR__ . '/consultas/listar-productos.php';
require_once __DIR__ . '/importar/includes/xlsx.php';

requireAuthentication();

$parameters = normalizarParametrosInventario($_GET);
$path = null;
try {
    $connection = database();
    $products = listarProductosInventarioExportacion($connection, $parameters, 5001);
    if (count($products) > 5000) {
        throw new LengthException('La exportación supera el límite de 5000 productos. Aplica filtros más específicos.');
    }
    $presentations = listarPresentacionesExportacion($connection, array_column($products, 'id_producto'));
    $exportedAt = new DateTimeImmutable();
    $filtered = hayFiltrosInventarioActivos($parameters);
    $headers = ['ID producto', 'Producto', 'SKU', 'Código de barras', 'Categoría', 'Marca', 'Tipo mascota', 'Tipo de stock', 'Precio', 'Stock actual', 'Stock mínimo', 'Estado stock', 'Presentaciones activas', 'Estado producto', 'Última actualización'];
    $rows = [
        ['INVENTARIO CORATTO PET'],
        ['Exportado el ' . $exportedAt->format('d-m-Y H:i') . ($filtered ? ' · Exportación filtrada' : ' · Inventario completo')],
        [$filtered ? 'Los resultados respetan los filtros aplicados en Inventario.' : 'La exportación contiene todos los productos vigentes.'],
        $headers,
    ];
    foreach ($products as $product) {
        $fractionable = esProductoFraccionable($product);
        $rows[] = [
            ['value' => (int) $product['id_producto'], 'type' => 'number', 'style' => 8],
            (string) $product['nombre'],
            $product['sku'] === null ? '' : (string) $product['sku'],
            $product['codigo_barras'] === null ? '' : (string) $product['codigo_barras'],
            (string) $product['categoria'],
            $product['marca'] === null ? 'Sin marca' : (string) $product['marca'],
            textoTipoMascota($product['tipo_mascota']),
            $fractionable ? 'Fraccionable' : 'Unidad',
            $fractionable ? 'Por presentación' : ['value' => (int) $product['precio_venta'], 'type' => 'number', 'style' => 9],
            formatearCantidadStock((int) $product['cantidad_disponible'], $fractionable),
            formatearCantidadStock((int) $product['stock_minimo'], $fractionable),
            textoEstadoStockInventario($product),
            $fractionable ? ['value' => (int) $product['presentaciones_activas'], 'type' => 'number', 'style' => 8] : 'No aplica',
            ucfirst((string) $product['estado']),
            formatearFechaInventario($product['actualizado_en']),
        ];
    }
    $lastInventoryRow = max(4, count($rows));
    $sheets = [[
        'name' => 'Inventario', 'rows' => $rows,
        'row_styles' => [1 => 1, 2 => 2, 3 => $filtered ? 6 : 4, 4 => 3],
        'row_heights' => [1 => 28, 2 => 28, 3 => 26, 4 => 34],
        'widths' => [12, 32, 20, 20, 20, 20, 18, 18, 20, 18, 18, 18, 20, 18, 22],
        'freeze_row' => 4, 'auto_filter' => 'A4:O' . $lastInventoryRow,
        'merges' => ['A1:O1', 'A2:O2', 'A3:O3'],
    ]];
    if ($presentations !== []) {
        $presentationRows = [
            ['PRESENTACIONES DE PRODUCTOS EXPORTADOS'],
            ['Solo se incluyen presentaciones vinculadas a los productos de la hoja Inventario.'],
            [],
            ['ID producto', 'Producto base', 'SKU producto base', 'Nombre presentación', 'Cantidad gramos', 'Cantidad formateada', 'Precio venta', 'SKU presentación', 'Activa', 'Orden'],
        ];
        foreach ($presentations as $presentation) {
            $presentationRows[] = [
                ['value' => (int) $presentation['id_producto'], 'type' => 'number', 'style' => 8],
                (string) $presentation['producto_base'],
                $presentation['sku_producto_base'] === null ? '' : (string) $presentation['sku_producto_base'],
                (string) $presentation['nombre'],
                ['value' => (int) $presentation['cantidad_gramos'], 'type' => 'number', 'style' => 8],
                formatearCantidadStock((int) $presentation['cantidad_gramos'], true),
                ['value' => (int) $presentation['precio_venta'], 'type' => 'number', 'style' => 9],
                $presentation['sku'] === null ? '' : (string) $presentation['sku'],
                in_array($presentation['activo'], [true, 1, '1', 't', 'true'], true) ? 'Sí' : 'No',
                ['value' => (int) $presentation['orden'], 'type' => 'number', 'style' => 8],
            ];
        }
        $sheets[] = ['name' => 'Presentaciones', 'rows' => $presentationRows, 'row_styles' => [1 => 1, 2 => 2, 4 => 3], 'row_heights' => [1 => 28, 2 => 28, 4 => 32], 'widths' => [12, 30, 22, 26, 18, 20, 18, 22, 12, 10], 'freeze_row' => 4, 'auto_filter' => 'A4:J' . count($presentationRows), 'merges' => ['A1:J1', 'A2:J2']];
    }

    $temporary = tempnam(sys_get_temp_dir(), 'coratto_export_');
    if ($temporary === false) { throw new RuntimeException('No fue posible crear el archivo temporal.'); }
    @unlink($temporary);
    $path = $temporary . '.xlsx';
    crearLibroXlsx($path, $sheets);
    enviarDescargaXlsx($path, 'Inventario_CorattoPet_' . $exportedAt->format('Ymd_Hi') . '.xlsx');
} catch (Throwable $exception) {
    if (is_string($path)) { @unlink($path); }
    $reference = registrarExcepcionAdmin('Inventory XLSX export error', $exception);
    $message = $exception instanceof LengthException ? $exception->getMessage() : 'No fue posible generar la exportación. Intenta nuevamente.';
    guardarModalAdmin('error', 'No fue posible exportar el inventario', $message, ['reference' => $reference]);
    header('Location: ' . appUrl('admin/inventario/index.php'), true, 303);
    exit;
}
