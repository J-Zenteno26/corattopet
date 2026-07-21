<?php

declare(strict_types=1);

/**
 * Lector XLSX acotado a hojas tabulares. No ejecuta formulas ni macros.
 */
function leerHojasXlsx(string $path): array
{
    try {
        $archive = new PharData($path, 0, null, Phar::ZIP);
        $workbookXml = contenidoEntradaXlsx($archive, 'xl/workbook.xml');
        $relationshipsXml = contenidoEntradaXlsx($archive, 'xl/_rels/workbook.xml.rels');
        $sharedStrings = cargarTextosCompartidosXlsx($archive);

        $workbook = simplexml_load_string($workbookXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        $relationships = simplexml_load_string($relationshipsXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if (!$workbook instanceof SimpleXMLElement || !$relationships instanceof SimpleXMLElement) {
            throw new RuntimeException('El libro Excel no tiene una estructura valida.');
        }

        $relationshipTargets = [];
        foreach ($relationships->Relationship as $relationship) {
            $relationshipTargets[(string) $relationship['Id']] = 'xl/' . ltrim((string) $relationship['Target'], '/');
        }

        $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = [];
        foreach ($workbook->xpath('//main:sheets/main:sheet') ?: [] as $sheet) {
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) ($attributes['id'] ?? '');
            $target = $relationshipTargets[$relationshipId] ?? '';
            if ($target === '') {
                continue;
            }
            $sheets[(string) $sheet['name']] = leerHojaXlsx(
                contenidoEntradaXlsx($archive, normalizarRutaXlsx($target)),
                $sharedStrings
            );
        }

        return $sheets;
    } catch (Throwable $exception) {
        throw new RuntimeException('No fue posible leer el archivo XLSX. Verifica que no este danado.', 0, $exception);
    }
}

function contenidoEntradaXlsx(PharData $archive, string $entry): string
{
    if (!isset($archive[$entry])) {
        throw new RuntimeException('Falta una parte requerida del archivo XLSX.');
    }

    return $archive[$entry]->getContent();
}

function normalizarRutaXlsx(string $path): string
{
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return implode('/', $parts);
}

function cargarTextosCompartidosXlsx(PharData $archive): array
{
    if (!isset($archive['xl/sharedStrings.xml'])) {
        return [];
    }
    $xml = simplexml_load_string($archive['xl/sharedStrings.xml']->getContent(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    if (!$xml instanceof SimpleXMLElement) {
        return [];
    }
    $values = [];
    foreach ($xml->si as $item) {
        $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
        $values[] = implode('', array_map(static fn (SimpleXMLElement $text): string => (string) $text, $parts));
    }

    return $values;
}

function leerHojaXlsx(string $xml, array $sharedStrings): array
{
    $sheet = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
    if (!$sheet instanceof SimpleXMLElement) {
        throw new RuntimeException('Una hoja del libro no es valida.');
    }
    $rows = [];
    foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
        $values = [];
        foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
            $reference = (string) $cell['r'];
            preg_match('/^[A-Z]+/i', $reference, $match);
            $column = indiceColumnaXlsx($match[0] ?? 'A');
            $type = (string) $cell['t'];
            if ($type === 'inlineStr') {
                $texts = $cell->xpath('.//*[local-name()="t"]') ?: [];
                $value = implode('', array_map(static fn (SimpleXMLElement $text): string => (string) $text, $texts));
            } else {
                $nodes = $cell->xpath('./*[local-name()="v"]') ?: [];
                $raw = isset($nodes[0]) ? (string) $nodes[0] : '';
                $value = $type === 's' ? ($sharedStrings[(int) $raw] ?? '') : $raw;
            }
            $values[$column] = trim($value);
        }
        if ($values !== []) {
            $max = max(array_keys($values));
            $rows[(int) $row['r']] = array_replace(array_fill(0, $max + 1, ''), $values);
        }
    }

    return $rows;
}

function indiceColumnaXlsx(string $letters): int
{
    $index = 0;
    foreach (str_split(strtoupper($letters)) as $letter) {
        $index = ($index * 26) + (ord($letter) - 64);
    }

    return max(0, $index - 1);
}

function crearPlantillaXlsx(string $path, array $sheets): void
{
    $zipPath = $path . '.zip';
    if (is_file($zipPath)) {
        unlink($zipPath);
    }
    $archive = new PharData($zipPath, 0, null, Phar::ZIP);
    $sheetEntries = [];
    $sheetRelations = [];
    $sheetDefinitions = [];
    foreach (array_values($sheets) as $index => $sheet) {
        $number = $index + 1;
        $sheetEntries['xl/worksheets/sheet' . $number . '.xml'] = xmlHojaXlsx($sheet['rows']);
        $sheetRelations[] = '<Relationship Id="rId' . $number . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $number . '.xml"/>';
        $sheetDefinitions[] = '<sheet name="' . xmlEscapeXlsx($sheet['name']) . '" sheetId="' . $number . '" r:id="rId' . $number . '"/>';
    }
    $archive['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . implode('', array_map(static fn (int $number): string => '<Override PartName="/xl/worksheets/sheet' . $number . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', range(1, count($sheets)))) . '</Types>';
    $archive['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $archive['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . implode('', $sheetDefinitions) . '</sheets></workbook>';
    $archive['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . implode('', $sheetRelations) . '</Relationships>';
    foreach ($sheetEntries as $entry => $content) {
        $archive[$entry] = $content;    
    }
    unset($archive);
    if (!rename($zipPath, $path)) {
        @unlink($zipPath);
        throw new RuntimeException('No fue posible preparar la plantilla.');
    }
}

function xmlHojaXlsx(array $rows): string
{
    $xmlRows = [];
    foreach ($rows as $rowIndex => $row) {
        $cells = [];
        foreach (array_values($row) as $columnIndex => $value) {
            $reference = letrasColumnaXlsx($columnIndex) . ($rowIndex + 1);
            $cells[] = '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">' . xmlEscapeXlsx((string) $value) . '</t></is></c>';
        }
        $xmlRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode('', $xmlRows) . '</sheetData></worksheet>';
}

function letrasColumnaXlsx(int $index): string
{
    $letters = '';
    for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
        $letters = chr((($number - 1) % 26) + 65) . $letters;
    }

    return $letters;
}

function xmlEscapeXlsx(string $value): string
{
    return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
