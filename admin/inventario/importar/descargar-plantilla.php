<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/xlsx.php';

requireAuthentication();

$categories = ['Alimentos', 'Accesorios', 'Juguetes', 'Higiene', 'Viaje'];
$brands = ['Pett', 'Brit Care', 'Acana'];
try {
    $connection = database();
    $categories = $connection->query('SELECT nombre FROM categorias WHERE activo = TRUE ORDER BY orden, nombre')->fetchAll(PDO::FETCH_COLUMN) ?: $categories;
    $brands = $connection->query('SELECT nombre FROM marcas WHERE activo = TRUE ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN) ?: $brands;
} catch (Throwable $exception) {
    error_log('Template reference lists error: ' . $exception->getMessage());
}

$productHeaders = ['SKU', 'Nombre del producto', 'Código de barras', 'Categoría', 'Marca', 'Tipo de mascota', 'Precio de venta (CLP)', 'Stock inicial', 'Subcategoría', 'Descripción comercial', 'Ingredientes / materiales', 'Análisis garantizado / características', 'Etapa de vida / tamaño', 'País de origen', 'Fraccionadora / importador', 'Datos reglamentarios', 'Estado', 'Nombre archivo imagen', 'Notas internas'];
$presentationHeaders = ['SKU producto base', 'Nombre presentación', 'Cantidad gramos', 'Precio venta (CLP)', 'SKU presentación', 'Orden', 'Activo', 'Notas internas'];
$productLastColumn = letrasColumnaXlsx(count($productHeaders) - 1);
$presentationLastColumn = letrasColumnaXlsx(count($presentationHeaders) - 1);
$listRows = [['Categorías', 'Marcas', 'Tipo mascota', 'Estado producto', 'Activo presentación']];
$maxLists = max(count($categories), count($brands), 4);
$petTypes = ['Perro', 'Gato', 'Ambos', 'Otro'];
$states = ['Activo', 'Inactivo'];
$activeValues = ['Sí', 'No'];
for ($index = 0; $index < $maxLists; $index++) {
    $listRows[] = [$categories[$index] ?? '', $brands[$index] ?? '', $petTypes[$index] ?? '', $states[$index] ?? '', $activeValues[$index] ?? ''];
}

$sheets = [
    ['name' => 'Productos', 'rows' => [
        ['PLANTILLA DE CARGA MASIVA · PRODUCTOS CORATTO PET'],
        ['Carga productos normales y alimentos fraccionables. Los alimentos usan stock en gramos y precios por presentaciones.'],
        ['IMPORTANTE: para Alimentos escribe 10000 para 10 kg. No uses “10 kg”, “10k”, “12,5” ni “12.5”.'],
        $productHeaders,
        ['TEST-COLLAR-AZUL', 'Collar ajustable azul TEST', '', 'Accesorios', 'Pett', 'Perro', ['value' => 5990, 'type' => 'number', 'style' => 9], ['value' => 20, 'type' => 'number', 'style' => 8], '', 'Collar ajustable para perro', 'Tela y herrajes', '', 'Adulto', 'Chile', '', '', 'Activo', '', 'Ejemplo de producto normal'],
        ['TEST-BRIT-URINARY-10K', 'Brit Care Urinary TEST', '', 'Alimentos', 'Brit Care', 'Gato', '', ['value' => 10000, 'type' => 'number', 'style' => 8], '', 'Alimento para gatos', '', 'Análisis según envase', 'Adulto', '', 'Importador de ejemplo', '', 'Activo', '', 'Stock expresado en gramos; precio en Presentaciones'],
        [],
        ['NOTAS DE CARGA'],
        ['• Para Alimentos, el stock inicial debe ir en gramos.'],
        ['• El precio base de Alimentos debe quedar vacío; los precios se ingresan en Presentaciones.'],
        ['• No cambies los nombres de las hojas ni los encabezados.'],
    ], 'row_styles' => [1 => 1, 2 => 2, 3 => 6, 4 => 3, 5 => 5, 6 => 5, 8 => 2, 9 => 4, 10 => 4, 11 => 4], 'row_heights' => [1 => 28, 2 => 34, 3 => 36, 4 => 34, 5 => 32, 6 => 36], 'widths' => [18, 30, 18, 18, 18, 16, 18, 14, 18, 34, 30, 34, 22, 16, 26, 26, 14, 24, 34], 'freeze_row' => 4, 'auto_filter' => 'A4:' . $productLastColumn . '6', 'merges' => ['A1:' . $productLastColumn . '1', 'A2:' . $productLastColumn . '2', 'A3:' . $productLastColumn . '3', 'A8:' . $productLastColumn . '8', 'A9:' . $productLastColumn . '9', 'A10:' . $productLastColumn . '10', 'A11:' . $productLastColumn . '11']],
    ['name' => 'Presentaciones', 'rows' => [
        ['PRESENTACIONES PARA ALIMENTOS FRACCIONABLES'],
        ['Solo aplican a productos de categoría Alimentos. El SKU producto base debe existir en Productos o en la base de datos.'],
        ['Cantidad gramos debe ser un entero mayor que 0.'],
        $presentationHeaders,
        ['TEST-BRIT-URINARY-10K', 'Bolsa 250 g', ['value' => 250, 'type' => 'number', 'style' => 8], ['value' => 2990, 'type' => 'number', 'style' => 9], 'TEST-BRIT-URINARY-250G', ['value' => 1, 'type' => 'number', 'style' => 8], 'Sí', ''],
        ['TEST-BRIT-URINARY-10K', 'Bolsa 1 kg', ['value' => 1000, 'type' => 'number', 'style' => 8], ['value' => 8990, 'type' => 'number', 'style' => 9], 'TEST-BRIT-URINARY-1KG', ['value' => 2, 'type' => 'number', 'style' => 8], 'Sí', ''],
    ], 'row_styles' => [1 => 1, 2 => 2, 3 => 6, 4 => 3, 5 => 5, 6 => 5], 'row_heights' => [1 => 28, 2 => 34, 3 => 28, 4 => 32], 'widths' => [26, 26, 18, 20, 26, 10, 12, 30], 'freeze_row' => 4, 'auto_filter' => 'A4:' . $presentationLastColumn . '6', 'merges' => ['A1:' . $presentationLastColumn . '1', 'A2:' . $presentationLastColumn . '2', 'A3:' . $presentationLastColumn . '3']],
    ['name' => 'Listas', 'rows' => array_merge([['LISTAS DE REFERENCIA'], ['Usa estas opciones para completar las hojas Productos y Presentaciones.'], []], $listRows), 'row_styles' => [1 => 1, 2 => 2, 4 => 3], 'row_heights' => [1 => 28, 2 => 28, 4 => 26], 'widths' => [24, 24, 18, 20, 22], 'freeze_row' => 4, 'auto_filter' => 'A4:E' . ($maxLists + 4), 'merges' => ['A1:E1', 'A2:E2']],
    ['name' => 'Instrucciones', 'rows' => [
        ['INSTRUCCIONES · CARGA MASIVA CORATTO PET'],
        ['Lee estas indicaciones antes de completar y subir el archivo.'],
        [],
        ['1. Productos normales'], ['Usan precio de venta directo y stock inicial en unidades.'],
        ['2. Alimentos'], ['Usan categoría Alimentos, stock inicial en gramos, precio base vacío y precios en Presentaciones. El stock mínimo será 5000 g por defecto.'],
        ['3. Presentaciones'], ['Solo aplican a Alimentos, se vinculan por SKU producto base y sus cantidades se expresan en gramos.'],
        ['4. Errores comunes'], ['No escribas “10 kg”, “10k” ni decimales. No repitas SKU ni uses marcas o categorías inexistentes.'],
        ['5. Revisión'], ['Sube el archivo y revisa la previsualización antes de confirmar la importación.'],
    ], 'row_styles' => [1 => 1, 2 => 2, 4 => 3, 5 => 4, 6 => 3, 7 => 4, 8 => 3, 9 => 4, 10 => 3, 11 => 6, 12 => 3, 13 => 5], 'row_heights' => [1 => 28, 2 => 28, 7 => 42, 9 => 34, 11 => 36], 'widths' => [110], 'merges' => []],
];

$path = tempnam(sys_get_temp_dir(), 'coratto_xlsx_');
if ($path === false) { http_response_code(500); exit; }
@unlink($path);
$path .= '.xlsx';
try {
    crearLibroXlsx($path, $sheets);
    enviarDescargaXlsx($path, 'Plantilla_Carga_Catalogo_CorattoPet.xlsx');
} catch (Throwable $exception) {
    @unlink($path);
    error_log('Template XLSX generation error: ' . $exception->getMessage());
    http_response_code(500);
    exit('No fue posible generar la plantilla.');
}
