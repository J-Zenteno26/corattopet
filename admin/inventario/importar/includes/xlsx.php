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
    crearLibroXlsx($path, $sheets);
}

function crearLibroXlsx(string $path, array $sheets): void
{
    if ($sheets === []) {
        throw new InvalidArgumentException('El libro debe contener al menos una hoja.');
    }
    $entries = [];
    $sheetRelations = [];
    $sheetDefinitions = [];
    foreach (array_values($sheets) as $index => $sheet) {
        $number = $index + 1;
        $entries['xl/worksheets/sheet' . $number . '.xml'] = xmlHojaXlsx($sheet);
        $sheetRelations[] = '<Relationship Id="rId' . $number . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $number . '.xml"/>';
        $sheetDefinitions[] = '<sheet name="' . xmlEscapeXlsx($sheet['name']) . '" sheetId="' . $number . '" r:id="rId' . $number . '"/>';
    }
    $entries = [
        '[Content_Types].xml' => xmlTiposContenidoXlsx(count($sheets)),
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>' . implode('', $sheetDefinitions) . '</sheets><calcPr calcId="191029"/></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . implode('', $sheetRelations) . '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => xmlEstilosXlsx(),
        'docProps/core.xml' => xmlPropiedadesCoreXlsx(),
        'docProps/app.xml' => xmlPropiedadesAppXlsx(array_column($sheets, 'name')),
    ] + $entries;
    escribirZipXlsx($path, $entries);
}

/**
 * Empaqueta OOXML sin PharData. Algunas versiones de Phar generan ZIP con
 * "version needed" igual a cero, encabezado que Excel considera corrupto.
 */
function escribirZipXlsx(string $path, array $entries): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException('No fue posible crear el archivo XLSX.');
    }
    $centralDirectory = '';
    $offset = 0;
    $count = 0;
    [$dosTime, $dosDate] = fechaDosXlsx();
    try {
        foreach ($entries as $name => $content) {
            $name = str_replace('\\', '/', (string) $name);
            $content = (string) $content;
            $compressed = gzdeflate($content, 6);
            if ($compressed === false) {
                throw new RuntimeException('No fue posible comprimir el archivo XLSX.');
            }
            $crc = crc32($content);
            $nameLength = strlen($name);
            $compressedLength = strlen($compressed);
            $contentLength = strlen($content);
            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50, 20, 0x0800, 8, $dosTime, $dosDate,
                $crc, $compressedLength, $contentLength, $nameLength, 0
            );
            escribirBytesXlsx($handle, $localHeader . $name . $compressed);
            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0x0800, 8, $dosTime, $dosDate,
                $crc, $compressedLength, $contentLength, $nameLength,
                0, 0, 0, 0, 0, $offset
            ) . $name;
            $offset += strlen($localHeader) + $nameLength + $compressedLength;
            $count++;
        }
        escribirBytesXlsx($handle, $centralDirectory);
        escribirBytesXlsx($handle, pack(
            'VvvvvVVv',
            0x06054b50, 0, 0, $count, $count,
            strlen($centralDirectory), $offset, 0
        ));
    } catch (Throwable $exception) {
        fclose($handle);
        @unlink($path);
        throw $exception;
    }
    fclose($handle);
}

/** @return array{0:int,1:int} */
function fechaDosXlsx(): array
{
    $now = getdate();
    $year = max(1980, (int) $now['year']);
    $time = ((int) $now['hours'] << 11) | ((int) $now['minutes'] << 5) | intdiv((int) $now['seconds'], 2);
    $date = (($year - 1980) << 9) | ((int) $now['mon'] << 5) | (int) $now['mday'];

    return [$time, $date];
}

/** @param resource $handle */
function escribirBytesXlsx($handle, string $bytes): void
{
    $length = strlen($bytes);
    $written = 0;
    while ($written < $length) {
        $result = fwrite($handle, substr($bytes, $written));
        if ($result === false || $result === 0) {
            throw new RuntimeException('No fue posible escribir el archivo XLSX.');
        }
        $written += $result;
    }
}

function xmlHojaXlsx(array $sheet): string
{
    $rows = $sheet['rows'] ?? [];
    $rowStyles = $sheet['row_styles'] ?? [];
    $columnStyles = $sheet['column_styles'] ?? [];
    $xmlRows = [];
    foreach ($rows as $rowIndex => $row) {
        $cells = [];
        foreach (array_values($row) as $columnIndex => $value) {
            $reference = letrasColumnaXlsx($columnIndex) . ($rowIndex + 1);
            $cell = is_array($value) && array_key_exists('value', $value) ? $value : ['value' => $value];
            $style = (int) ($cell['style'] ?? $columnStyles[$columnIndex] ?? $rowStyles[$rowIndex + 1] ?? 0);
            $styleAttribute = $style > 0 ? ' s="' . $style . '"' : '';
            if (($cell['type'] ?? 'string') === 'number' && is_numeric($cell['value'])) {
                $cells[] = '<c r="' . $reference . '"' . $styleAttribute . '><v>' . xmlEscapeXlsx((string) $cell['value']) . '</v></c>';
            } else {
                $cells[] = '<c r="' . $reference . '"' . $styleAttribute . ' t="inlineStr"><is><t xml:space="preserve">' . xmlEscapeXlsx((string) $cell['value']) . '</t></is></c>';
            }
        }
        $height = $sheet['row_heights'][$rowIndex + 1] ?? null;
        $heightAttribute = $height !== null ? ' ht="' . (float) $height . '" customHeight="1"' : '';
        $xmlRows[] = '<row r="' . ($rowIndex + 1) . '"' . $heightAttribute . '>' . implode('', $cells) . '</row>';
    }
    $maxColumns = max(1, ...array_map('count', $rows ?: [[]]));
    $maxRows = max(1, count($rows));
    $dimension = 'A1:' . letrasColumnaXlsx($maxColumns - 1) . $maxRows;
    $columns = [];
    foreach ($sheet['widths'] ?? [] as $index => $width) {
        $column = (int) $index + 1;
        $columns[] = '<col min="' . $column . '" max="' . $column . '" width="' . (float) $width . '" customWidth="1"/>';
    }
    $freezeRow = max(0, (int) ($sheet['freeze_row'] ?? 0));
    $pane = $freezeRow > 0 ? '<pane ySplit="' . $freezeRow . '" topLeftCell="A' . ($freezeRow + 1) . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft"/>' : '<selection activeCell="A1" sqref="A1"/>';
    $merges = array_values(array_filter($sheet['merges'] ?? [], 'is_string'));
    $mergeXml = $merges === [] ? '' : '<mergeCells count="' . count($merges) . '">' . implode('', array_map(static fn (string $range): string => '<mergeCell ref="' . xmlEscapeXlsx($range) . '"/>', $merges)) . '</mergeCells>';
    $filter = isset($sheet['auto_filter']) ? '<autoFilter ref="' . xmlEscapeXlsx((string) $sheet['auto_filter']) . '"/>' : '';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="' . $dimension . '"/><sheetViews><sheetView showGridLines="0" workbookViewId="0">' . $pane . '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/>' . ($columns === [] ? '' : '<cols>' . implode('', $columns) . '</cols>') . '<sheetData>' . implode('', $xmlRows) . '</sheetData>' . $filter . $mergeXml . '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';
}

function xmlTiposContenidoXlsx(int $sheetCount): string
{
    $sheets = implode('', array_map(static fn (int $number): string => '<Override PartName="/xl/worksheets/sheet' . $number . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', range(1, $sheetCount)));
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . $sheets . '</Types>';
}

function xmlEstilosXlsx(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="2"><numFmt numFmtId="164" formatCode="$#,##0"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm"/></numFmts><fonts count="5"><font><sz val="10"/><name val="Aptos"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="16"/><name val="Aptos Display"/></font><font><b/><color rgb="FF211F1B"/><sz val="11"/><name val="Aptos"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Aptos"/></font><font><color rgb="FF575147"/><sz val="10"/><name val="Aptos"/></font></fonts><fills count="7"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF211F1B"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0D9AA"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFC58B32"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF5EE"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFAECEA"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE4D8C6"/></left><right style="thin"><color rgb="FFE4D8C6"/></right><top style="thin"><color rgb="FFE4D8C6"/></top><bottom style="thin"><color rgb="FFE4D8C6"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="11"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment wrapText="1" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="center"/></xf><xf numFmtId="0" fontId="4" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf><xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf><xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment wrapText="1" vertical="top"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center"/></xf><xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf><xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}

function xmlPropiedadesCoreXlsx(): string
{
    $date = gmdate('Y-m-d\TH:i:s\Z');
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>Coratto Pet</dc:creator><cp:lastModifiedBy>Coratto Pet</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:modified></cp:coreProperties>';
}

function xmlPropiedadesAppXlsx(array $sheetNames): string
{
    $titles = implode('', array_map(static fn (string $name): string => '<vt:lpstr>' . xmlEscapeXlsx($name) . '</vt:lpstr>', $sheetNames));
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Coratto Pet</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Hojas de cálculo</vt:lpstr></vt:variant><vt:variant><vt:i4>' . count($sheetNames) . '</vt:i4></vt:variant></vt:vector></HeadingPairs><TitlesOfParts><vt:vector size="' . count($sheetNames) . '" baseType="lpstr">' . $titles . '</vt:vector></TitlesOfParts><Company>Coratto Pet</Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>16.0300</AppVersion></Properties>';
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

function enviarDescargaXlsx(string $path, string $filename): never
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('El archivo XLSX no está disponible para descarga.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"');
    header('Cache-Control: max-age=0, no-store, no-cache, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . (string) filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    @unlink($path);
    exit;
}
