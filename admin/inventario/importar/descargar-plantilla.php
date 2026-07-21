<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once __DIR__ . '/includes/xlsx.php';

requireAuthentication();

$productHeaders = ['SKU', 'Nombre del producto', 'Código de barras', 'Categoría', 'Marca', 'Tipo de mascota', 'Precio de venta (CLP)', 'Stock inicial', 'Subcategoría', 'Descripción comercial', 'Ingredientes / materiales', 'Análisis garantizado / características', 'Etapa de vida / tamaño', 'País de origen', 'Fraccionadora / importador', 'Datos reglamentarios', 'Estado', 'Nombre archivo imagen', 'Notas internas'];
$presentationHeaders = ['SKU producto base', 'Nombre presentación', 'Cantidad gramos', 'Precio venta (CLP)', 'SKU presentación', 'Orden', 'Activo', 'Notas internas'];
$sheets = [
    ['name' => 'Productos', 'rows' => [
        ['PLANTILLA DE CARGA DE PRODUCTOS'],
        ['No cambies los nombres de las hojas ni de los encabezados.'],
        [],
        $productHeaders,
        ['CO_PE_1', 'Collar con corbata', '111111115', 'Vestuario', 'Pett', 'perro', '5000', '50', '', 'Collar decorativo para perro', 'Tela y herrajes', '', 'Adulto', 'Chile', '', '', 'activo', '', 'Ejemplo de producto normal'],
        ['BRIT-K-U-10K', 'BritCare Urinary', '1141111977', 'Alimentos', 'Brit Care', 'gato', '', '10000', '', 'Alimento para gatos', '', 'Análisis según envase', 'Adulto', '', 'Importador de ejemplo', '', 'activo', '', 'Stock expresado en gramos'],
    ]],
    ['name' => 'Presentaciones', 'rows' => [
        ['PLANTILLA DE PRESENTACIONES'],
        ['Solo se permiten presentaciones para productos fraccionables.'],
        [],
        $presentationHeaders,
        ['BRIT-K-U-10K', 'Bolsa 250 g', '250', '2990', 'BRIT-K-U-250G', '1', 'Sí', ''],
        ['BRIT-K-U-10K', 'Bolsa 1 kg', '1000', '8990', 'BRIT-K-U-1KG', '2', 'Sí', ''],
    ]],
    ['name' => 'Listas', 'rows' => [['Tipo mascota', 'Estado producto', 'Activo'], ['perro', 'activo', 'Sí'], ['gato', 'inactivo', 'No'], ['ambos', '', ''], ['otro', '', '']]],
    ['name' => 'Instrucciones', 'rows' => [['INSTRUCCIONES'], ['1. Completa Productos y Presentaciones.'], ['2. Para alimentos, ingresa stock en gramos enteros: 10000 equivale a 10 kg.'], ['3. No uses textos como 10 kg, 10k, 12,5 o 12.5.'], ['4. Sube el archivo y revisa la previsualización antes de confirmar.']]],
];
$path = tempnam(sys_get_temp_dir(), 'coratto_xlsx_');
if ($path === false) {
    http_response_code(500);
    exit('No fue posible generar la plantilla.');
}
@unlink($path);
$path .= '.xlsx';
try {
    crearPlantillaXlsx($path, $sheets);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Plantilla_Carga_Catalogo_CorattoPet.xlsx"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} finally {
    if (is_file($path)) @unlink($path);
}
