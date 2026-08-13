<?php

declare(strict_types=1);

const DRIVE_FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';
const DRIVE_API_BASE_URL = 'https://www.googleapis.com/drive/v3';
const DRIVE_IMAGE_MAX_DOWNLOAD_BYTES = 20971520;
const DRIVE_IMAGE_MAX_FINAL_BYTES = 2097152;
const DRIVE_IMAGE_MAX_DIMENSION = 12000;
const DRIVE_IMAGE_MAX_PIXELS = 40000000;

final class DriveImportException extends RuntimeException
{
    public function __construct(public readonly string $errorType, string $message)
    {
        parent::__construct($message);
    }
}

final class DriveImageException extends RuntimeException
{
}

function idCarpetaDriveValido(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);

    return $value === 'root' || preg_match('/^[A-Za-z0-9_-]{10,200}$/', $value) === 1 ? $value : null;
}

function tokenTemporalDriveValido(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);

    return strlen($value) >= 20
        && strlen($value) <= 4096
        && preg_match('/[\s\x00-\x1F\x7F]/', $value) !== 1
            ? $value
            : null;
}

function idArchivoDriveValido(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    return preg_match('/^[A-Za-z0-9_-]{10,200}$/', $value) === 1 ? $value : null;
}

function construirQueryDrive(array $parameters): string
{
    return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
}

function solicitarDrive(string $path, string $accessToken, array $parameters): array
{
    $url = DRIVE_API_BASE_URL . '/' . ltrim($path, '/');
    $query = construirQueryDrive($parameters);
    if ($query !== '') {
        $url .= '?' . $query;
    }

    $handle = curl_init($url);
    if ($handle === false) {
        throw new DriveImportException('unavailable', 'Drive API no está disponible en este momento.');
    }
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $failed = $body === false;
    curl_close($handle);

    if ($failed || $status === 0) {
        throw new DriveImportException('unavailable', 'Drive API no respondió. Intenta nuevamente.');
    }

    $decoded = json_decode((string) $body, true);
    if ($status < 200 || $status >= 300) {
        throw normalizarErrorDrive($status, is_array($decoded) ? $decoded : []);
    }
    if (!is_array($decoded)) {
        throw new DriveImportException('unavailable', 'Drive API devolvió una respuesta no válida.');
    }

    return $decoded;
}

function normalizarErrorDrive(int $status, array $payload): DriveImportException
{
    $googleStatus = strtoupper((string) ($payload['error']['status'] ?? ''));
    if ($status === 401 || $googleStatus === 'UNAUTHENTICATED') {
        return new DriveImportException('expired_token', 'La autorización de Google venció. Autoriza nuevamente.');
    }
    if ($status === 403 || $googleStatus === 'PERMISSION_DENIED') {
        return new DriveImportException('access_denied', 'Google Drive denegó el acceso a la carpeta seleccionada.');
    }
    if ($status === 404 || $googleStatus === 'NOT_FOUND') {
        return new DriveImportException('invalid_folder', 'La carpeta seleccionada no existe o ya no está disponible.');
    }

    return new DriveImportException('google_error', 'Google Drive no pudo completar la solicitud. Intenta nuevamente.');
}

function obtenerCarpetaDrive(string $folderId, string $accessToken): array
{
    $folder = solicitarDrive(
        'files/' . rawurlencode($folderId),
        $accessToken,
        ['supportsAllDrives' => 'true', 'fields' => 'id,name,mimeType']
    );
    if (($folder['mimeType'] ?? null) !== DRIVE_FOLDER_MIME_TYPE) {
        throw new DriveImportException('invalid_folder', 'El elemento seleccionado no es una carpeta de Drive.');
    }

    return [
        'id' => (string) ($folder['id'] ?? $folderId),
        'nombre' => (string) ($folder['name'] ?? 'Carpeta sin nombre'),
        'mime_type' => DRIVE_FOLDER_MIME_TYPE,
    ];
}

function obtenerMetadatosArchivoDrive(string $fileId, string $accessToken): array
{
    $file = solicitarDrive(
        'files/' . rawurlencode($fileId),
        $accessToken,
        ['supportsAllDrives' => 'true', 'fields' => 'id,name,mimeType,size,fileExtension,parents']
    );
    return [
        'id' => (string) ($file['id'] ?? $fileId),
        'nombre' => basename((string) ($file['name'] ?? 'archivo')),
        'mime_type_declarado' => (string) ($file['mimeType'] ?? 'application/octet-stream'),
        'tamano_declarado' => isset($file['size']) && is_numeric($file['size']) ? (int) $file['size'] : null,
        'extension' => extensionNormalizadaDrive($file['fileExtension'] ?? null, (string) ($file['name'] ?? '')),
        'parents' => array_values(array_filter(is_array($file['parents'] ?? null) ? $file['parents'] : [], 'is_string')),
    ];
}

function crearTemporalSeguroDrive(): string
{
    $path = tempnam(sys_get_temp_dir(), 'coratto_drive_');
    if (!is_string($path) || $path === '') {
        throw new DriveImageException('No fue posible crear un archivo temporal seguro.');
    }
    @chmod($path, 0600);
    return $path;
}

function limpiarTemporalesDrive(array $paths): void
{
    foreach (array_unique($paths) as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}

function descargarArchivoDrive(string $fileId, string $accessToken, string $destination): array
{
    $handle = curl_init(DRIVE_API_BASE_URL . '/files/' . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true');
    $stream = @fopen($destination, 'wb');
    if ($handle === false || $stream === false) {
        if (is_resource($stream)) fclose($stream);
        if ($handle !== false) curl_close($handle);
        throw new DriveImageException('No fue posible preparar la descarga temporal.');
    }
    $responseMime = null;
    $declaredLength = null;
    $tooLarge = false;
    curl_setopt_array($handle, [
        CURLOPT_FILE => $stream,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Accept: application/octet-stream', 'Authorization: Bearer ' . $accessToken],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseMime, &$declaredLength): int {
            $length = strlen($header);
            if (str_contains($header, ':')) {
                [$name, $value] = array_map('trim', explode(':', $header, 2));
                if (strcasecmp($name, 'Content-Type') === 0) $responseMime = strtolower(explode(';', $value, 2)[0]);
                if (strcasecmp($name, 'Content-Length') === 0 && ctype_digit($value)) $declaredLength = (int) $value;
            }
            return $length;
        },
        CURLOPT_NOPROGRESS => false,
        CURLOPT_XFERINFOFUNCTION => static function ($curl, float $downloadTotal, float $downloaded) use (&$tooLarge): int {
            if ($downloadTotal > DRIVE_IMAGE_MAX_DOWNLOAD_BYTES || $downloaded > DRIVE_IMAGE_MAX_DOWNLOAD_BYTES) {
                $tooLarge = true;
                return 1;
            }
            return 0;
        },
    ]);
    $success = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    fclose($stream);
    if ($tooLarge || ($declaredLength !== null && $declaredLength > DRIVE_IMAGE_MAX_DOWNLOAD_BYTES)) {
        throw new DriveImageException('El archivo supera el límite de descarga de 20 MB.');
    }
    if ($success === false || $status === 0) {
        throw new DriveImportException('unavailable', 'Drive API no respondió durante la descarga.');
    }
    if ($status < 200 || $status >= 300) {
        $payload = json_decode((string) @file_get_contents($destination), true);
        throw normalizarErrorDrive($status, is_array($payload) ? $payload : []);
    }
    return ['mime_respuesta' => $responseMime, 'tamano_declarado' => $declaredLength];
}

function validarDimensionesImagenDrive(string $path, ?string $expectedMime = null): array
{
    $info = @getimagesize($path);
    if (!is_array($info) || !isset($info[0], $info[1]) || $info[0] < 1 || $info[1] < 1) {
        throw new DriveImageException('El archivo no contiene una imagen válida.');
    }
    $width = (int) $info[0];
    $height = (int) $info[1];
    $mime = strtolower((string) ($info['mime'] ?? ''));
    if ($expectedMime !== null && $mime !== $expectedMime) {
        throw new DriveImageException('El MIME de la imagen no coincide con el formato esperado.');
    }
    if ($width > DRIVE_IMAGE_MAX_DIMENSION || $height > DRIVE_IMAGE_MAX_DIMENSION || $width * $height > DRIVE_IMAGE_MAX_PIXELS) {
        throw new DriveImageException('Las dimensiones de la imagen superan el límite permitido.');
    }
    return ['ancho' => $width, 'alto' => $height, 'mime' => $mime];
}

function validarDescargaImagenDrive(string $path, array $metadata): array
{
    $size = is_file($path) ? filesize($path) : false;
    if (!is_int($size) || $size < 1 || $size > DRIVE_IMAGE_MAX_DOWNLOAD_BYTES) {
        throw new DriveImageException('La descarga está vacía o supera el límite permitido.');
    }
    $name = basename((string) ($metadata['nombre'] ?? 'archivo'));
    if (preg_match('/\.(php\d*|phtml|phar|cgi|pl|py|sh|exe|com|bat|cmd)(\.|$)/i', $name) === 1) {
        throw new DriveImageException('El nombre del archivo contiene una extensión no permitida.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif', 'image/heic-sequence' => 'heic', 'image/heif-sequence' => 'heif'];
    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new DriveImageException('El contenido descargado no corresponde a un formato de imagen permitido.');
    }
    $extension = strtolower((string) ($metadata['extension'] ?? ''));
    $extensionGroup = $extension === 'jpeg' ? 'jpg' : $extension;
    $extensionMatches = str_starts_with($mime, 'image/hei')
        ? in_array($extensionGroup, ['heic', 'heif'], true)
        : $extensionGroup === $allowed[$mime];
    if (!$extensionMatches) {
        throw new DriveImageException('La extensión del archivo no coincide con su MIME real.');
    }
    if (isset($metadata['tamano_declarado']) && is_int($metadata['tamano_declarado']) && $metadata['tamano_declarado'] !== $size) {
        throw new DriveImageException('El tamaño descargado no coincide con el informado por Drive.');
    }
    return ['mime' => $mime, 'extension' => $allowed[$mime], 'tamano' => $size, 'nombre_original' => mb_substr($name, 0, 255)];
}

function orientacionJpegDrive(string $path): int
{
    if (!function_exists('exif_read_data')) return 1;
    try {
        $exif = @exif_read_data($path, 'IFD0', true, false);
        return is_array($exif) ? (int) ($exif['IFD0']['Orientation'] ?? 1) : 1;
    } catch (Throwable) {
        return 1;
    }
}

function normalizarImagenDrive(string $inputPath, array $validated, array &$temporaries): array
{
    $outputPath = $inputPath;
    if ($validated['mime'] === 'image/jpeg' && orientacionJpegDrive($inputPath) > 1) {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            throw new DriveImageException('La imagen requiere corregir orientación, pero Imagick no está disponible.');
        }
        $outputPath = crearTemporalSeguroDrive();
        $temporaries[] = $outputPath;
        try {
            $image = new Imagick($inputPath);
            $image->setIteratorIndex(0);
            $image->autoOrientImage();
            $image->stripImage();
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(88);
            if (!$image->writeImage($outputPath)) throw new RuntimeException();
            $image->clear();
            $image->destroy();
        } catch (Throwable) {
            throw new DriveImageException('No fue posible corregir la orientación de la imagen JPG.');
        }
    }
    return validarResultadoImagenDrive($outputPath, $validated['extension'], false);
}

function convertirHeicAWebpDrive(string $inputPath, array &$temporaries): array
{
    $diagnosis = diagnosticarSoporteHeic();
    if (!$diagnosis['imagick_instalado']) {
        throw new DriveImageException('Imagick no está instalado en el servidor.');
    }
    if (!$diagnosis['heic_lectura_disponible']) {
        throw new DriveImageException('Imagick no dispone de lectura HEIC/HEIF.');
    }
    if (!$diagnosis['webp_escritura_disponible']) {
        throw new DriveImageException('Imagick no dispone de escritura WEBP.');
    }

    $outputPath = crearTemporalSeguroDrive();
    $temporaries[] = $outputPath;

    try {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 512 * 1024 * 1024);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024);

        $blob = @file_get_contents($inputPath);
        if (!is_string($blob) || $blob === '') {
            throw new RuntimeException('No fue posible leer el temporal HEIC descargado.');
        }

        /*
         * readImageBlob evita fallos de detección en algunos servidores cuando
         * el archivo temporal no conserva la extensión .heic/.heif.
         */
        $source = new Imagick();
        $source->readImageBlob($blob);
        $source->setIteratorIndex(0);

        $image = $source->getImage();
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        if ($width < 1 || $height < 1
            || $width > DRIVE_IMAGE_MAX_DIMENSION
            || $height > DRIVE_IMAGE_MAX_DIMENSION
            || $width * $height > DRIVE_IMAGE_MAX_PIXELS) {
            throw new DriveImageException('Las dimensiones de la imagen superan el límite permitido.');
        }

        if (method_exists($image, 'autoOrientImage')) {
            $image->autoOrientImage();
        }
        $image->setImagePage(0, 0, 0, 0);
        $image->stripImage();
        $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
        $image->setImageFormat('webp');
        $image->setOption('webp:method', '6');
        $image->setImageCompressionQuality(82);

        if (!$image->writeImage($outputPath)) {
            throw new RuntimeException('Imagick no pudo escribir el archivo WEBP final.');
        }

        $image->clear();
        $image->destroy();
        $source->clear();
        $source->destroy();
    } catch (DriveImageException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        /*
         * No se expone la ruta temporal ni el token, pero sí se deja la causa
         * técnica en el log del servidor para diagnosticar delegates/policies.
         */
        error_log('Drive HEIC conversion error: ' . $exception->getMessage());
        throw new DriveImageException('El archivo HEIC/HEIF no pudo convertirse a WEBP. Revisa el registro del servidor.');
    }

    return validarResultadoImagenDrive($outputPath, 'webp', true);
}

function validarResultadoImagenDrive(string $path, string $extension, bool $converted): array
{
    $size = is_file($path) ? filesize($path) : false;
    if (!is_int($size) || $size < 1 || $size > DRIVE_IMAGE_MAX_FINAL_BYTES) {
        throw new DriveImageException('La imagen procesada supera el límite final de 2 MB.');
    }
    $expectedMime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension] ?? null;
    if ($expectedMime === null || (new finfo(FILEINFO_MIME_TYPE))->file($path) !== $expectedMime) {
        throw new DriveImageException('El formato final de la imagen no es válido.');
    }
    $dimensions = validarDimensionesImagenDrive($path, $expectedMime);
    return ['temporal_final' => $path, 'extension_final' => $extension, 'mime_final' => $expectedMime, 'tamano_final' => $size, 'dimensiones' => ['ancho' => $dimensions['ancho'], 'alto' => $dimensions['alto']], 'convertido' => $converted];
}

function listarHijosDirectosDrive(string $folderId, string $accessToken, ?callable $requester = null): array
{
    $items = [];
    $pageToken = null;
    $request = $requester ?? 'solicitarDrive';
    do {
        $parameters = [
            'q' => sprintf("'%s' in parents and trashed = false", $folderId),
            'pageSize' => '1000',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
            'fields' => 'nextPageToken,files(id,name,mimeType,size,fileExtension)',
        ];
        if ($pageToken !== null) {
            $parameters['pageToken'] = $pageToken;
        }
        $response = $request('files', $accessToken, $parameters);
        foreach (is_array($response['files'] ?? null) ? $response['files'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mimeType = (string) ($item['mimeType'] ?? 'application/octet-stream');
            $name = (string) ($item['name'] ?? 'Elemento sin nombre');
            $extension = extensionNormalizadaDrive($item['fileExtension'] ?? null, $name);
            $items[] = [
                'id' => (string) ($item['id'] ?? ''),
                'nombre' => $name,
                'mime_type' => $mimeType,
                'tipo' => $mimeType === DRIVE_FOLDER_MIME_TYPE ? 'Carpeta' : 'Archivo',
                'tamano' => isset($item['size']) && is_numeric($item['size']) ? (int) $item['size'] : null,
                'extension' => $extension,
                'clasificacion' => clasificarElementoDrive($extension, $mimeType),
            ];
        }
        $next = $response['nextPageToken'] ?? null;
        $pageToken = is_string($next) && $next !== '' ? $next : null;
    } while ($pageToken !== null);

    usort($items, static fn (array $left, array $right): int => strnatcasecmp($left['nombre'], $right['nombre']));

    return $items;
}

function extensionNormalizadaDrive(mixed $fileExtension, string $name): string
{
    $extension = is_string($fileExtension) ? trim($fileExtension) : '';
    if ($extension === '') {
        $extension = (string) pathinfo($name, PATHINFO_EXTENSION);
    }

    return strtolower(ltrim($extension, '.'));
}

function clasificarElementoDrive(string $extension, string $mimeType): string
{
    $extension = strtolower($extension);
    $mimeType = strtolower(trim($mimeType));
    if (in_array($extension, ['heic', 'heif'], true) || in_array($mimeType, ['image/heic', 'image/heif'], true)) {
        return 'heic';
    }
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
        || in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'compatible';
    }

    return 'no compatible';
}

function normalizarSkusCarpetaDrive(mixed $folderName): array
{
    if (!is_string($folderName)) {
        return [];
    }
    $skus = [];
    foreach (explode(',', $folderName) as $part) {
        $sku = mb_strtoupper(trim($part));
        if ($sku !== '') {
            $skus[$sku] = $sku;
        }
    }
    return array_values($skus);
}

function nombreBaseArchivoDrive(string $name): string
{
    return mb_strtolower(trim((string) pathinfo($name, PATHINFO_FILENAME)));
}

function metadatosArchivoDrive(array $item): array
{
    return [
        'id' => (string) ($item['id'] ?? ''),
        'nombre' => (string) ($item['nombre'] ?? ''),
        'extension' => (string) ($item['extension'] ?? ''),
        'mime_type' => (string) ($item['mime_type'] ?? ''),
        'tamano' => isset($item['tamano']) && is_int($item['tamano']) ? $item['tamano'] : null,
    ];
}

function analizarSubcarpetaProductoDrive(array $folder, array $items): array
{
    $compatible = [];
    $heic = [];
    $ignored = [];
    $principalCandidates = [];
    $incidents = [];

    foreach ($items as $item) {
        if (($item['tipo'] ?? '') === 'Archivo' && ($item['clasificacion'] ?? '') === 'compatible') {
            $file = metadatosArchivoDrive($item);
            $compatible[] = $file;
            if (nombreBaseArchivoDrive($file['nombre']) === 'principal') {
                $principalCandidates[] = $file;
            }
            continue;
        }
        if (($item['tipo'] ?? '') === 'Archivo' && ($item['clasificacion'] ?? '') === 'heic') {
            $file = metadatosArchivoDrive($item);
            $heic[] = $file;
            if (nombreBaseArchivoDrive($file['nombre']) === 'principal') {
                $principalCandidates[] = $file;
            }
            continue;
        }
        $ignored[] = [
            ...metadatosArchivoDrive($item),
            'tipo' => (string) ($item['tipo'] ?? 'Archivo'),
            'motivo' => ($item['tipo'] ?? '') === 'Carpeta'
                ? 'Las carpetas anidadas no se recorren.'
                : 'Formato no compatible.',
        ];
    }

    $allImages = array_merge($compatible, $heic);
    $cover = $principalCandidates[0] ?? $allImages[0] ?? null;
    if ($cover !== null) {
        $cover['deteccion'] = $principalCandidates !== [] ? 'nombre_principal' : 'primera_imagen';
    }
    if (count($principalCandidates) > 1) {
        $incidents[] = 'Hay más de un archivo llamado principal; se propone el primero.';
    }
    $skus = normalizarSkusCarpetaDrive($folder['nombre'] ?? null);
    if ($skus === []) {
        $incidents[] = 'El nombre de la carpeta no contiene SKU válidos separados por coma.';
    }
    if ($allImages === []) {
        $incidents[] = 'La carpeta no contiene imágenes compatibles.';
    }
    if ($ignored !== []) {
        $incidents[] = count($ignored) . ' elemento(s) fueron ignorados.';
    }

    return [
        'id_carpeta' => (string) ($folder['id'] ?? ''),
        'nombre_carpeta' => (string) ($folder['nombre'] ?? ''),
        'sku_detectados' => $skus,
        'productos_encontrados' => [],
        'sku_inexistentes' => [],
        'imagenes_compatibles' => $compatible,
        'imagenes_heic_heif' => $heic,
        'archivos_ignorados' => $ignored,
        'portada_detectada' => $cover,
        'incidencias' => $incidents,
    ];
}

function obtenerProductosDrivePorSkus(PDO $pdo, array $skus): array
{
    $skus = array_values(array_unique(array_filter($skus, static fn (mixed $sku): bool => is_string($sku) && $sku !== '')));
    if ($skus === []) {
        return [];
    }
    $placeholders = [];
    foreach (array_keys($skus) as $index) {
        $placeholders[] = ':sku_' . $index;
    }
    $statement = $pdo->prepare(
        'SELECT id_producto,nombre,sku
         FROM productos
         WHERE LOWER(TRIM(sku)) IN (' . implode(',', $placeholders) . ')
         ORDER BY nombre,id_producto'
    );
    foreach ($skus as $index => $sku) {
        $statement->bindValue(':sku_' . $index, mb_strtolower($sku), PDO::PARAM_STR);
    }
    $statement->execute();
    return $statement->fetchAll();
}

function asociarProductosAnalisisDrive(array $folders, array $products): array
{
    $productsBySku = [];
    foreach ($products as $product) {
        $key = mb_strtolower(trim((string) ($product['sku'] ?? '')));
        if ($key !== '') {
            $productsBySku[$key][] = [
                'id_producto' => (int) $product['id_producto'],
                'nombre' => (string) $product['nombre'],
                'sku' => (string) $product['sku'],
            ];
        }
    }
    foreach ($folders as &$folder) {
        foreach ($folder['sku_detectados'] as $sku) {
            $matches = $productsBySku[mb_strtolower($sku)] ?? [];
            if ($matches === []) {
                $folder['sku_inexistentes'][] = $sku;
            } else {
                array_push($folder['productos_encontrados'], ...$matches);
            }
        }
        if ($folder['sku_inexistentes'] !== []) {
            $folder['incidencias'][] = 'SKU sin producto: ' . implode(', ', $folder['sku_inexistentes']) . '.';
        }
    }
    unset($folder);
    return $folders;
}

function diagnosticarSoporteHeic(): array
{
    $diagnosis = [
        'imagick_instalado' => extension_loaded('imagick') && class_exists('Imagick'),
        'heic_lectura_disponible' => false,
        'webp_escritura_disponible' => false,
    ];
    if (!$diagnosis['imagick_instalado']) {
        return $diagnosis;
    }
    try {
        $diagnosis['heic_lectura_disponible'] = Imagick::queryFormats('HEIC*') !== []
            || Imagick::queryFormats('HEIF*') !== [];
        $diagnosis['webp_escritura_disponible'] = Imagick::queryFormats('WEBP*') !== [];
    } catch (Throwable) {
        // El diagnóstico es informativo y nunca debe detener el análisis de Drive.
    }

    return $diagnosis;
}
