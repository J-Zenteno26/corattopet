<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once __DIR__ . '/includes/funciones-drive.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderProcesamientoDrive(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function errorArchivoDrive(?array $metadata, string $message, ?string $realMime = null): array
{
    return [
        'ok' => false,
        'error' => $message,
        'archivo' => [
            'nombre_original' => $metadata['nombre'] ?? null,
            'mime_original' => $realMime ?? ($metadata['mime_type_declarado'] ?? null),
            'extension_final' => null,
            'tamano_final' => null,
            'dimensiones' => null,
            'convertido' => false,
            'error' => $message,
        ],
    ];
}

requireAuthentication();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderProcesamientoDrive(405, ['ok' => false, 'error' => 'Método no permitido.']);
}
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    responderProcesamientoDrive(403, ['ok' => false, 'error' => 'La solicitud no es válida. Recarga la página.']);
}

$folderId = idCarpetaDriveValido($_POST['folder_id'] ?? null);
$fileId = idArchivoDriveValido($_POST['file_id'] ?? null);
$accessToken = tokenTemporalDriveValido($_POST['access_token'] ?? null);
if ($folderId === null || $fileId === null) {
    responderProcesamientoDrive(422, ['ok' => false, 'error' => 'La carpeta o el archivo indicado no es válido.']);
}
if ($accessToken === null) {
    responderProcesamientoDrive(401, ['ok' => false, 'error' => 'La autorización de Google no es válida o venció.']);
}

$temporaries = [];
$status = 200;
$payload = [];
$metadata = null;
$validated = null;
try {
    $metadata = obtenerMetadatosArchivoDrive($fileId, $accessToken);
    if (!in_array($folderId, $metadata['parents'], true)) {
        throw new DriveImageException('El archivo no pertenece directamente a la carpeta indicada.');
    }
    if ($metadata['tamano_declarado'] !== null && $metadata['tamano_declarado'] > DRIVE_IMAGE_MAX_DOWNLOAD_BYTES) {
        throw new DriveImageException('El archivo supera el límite de descarga de 20 MB.');
    }
    $inputPath = crearTemporalSeguroDrive();
    $temporaries[] = $inputPath;
    descargarArchivoDrive($fileId, $accessToken, $inputPath);
    $validated = validarDescargaImagenDrive($inputPath, $metadata);
    $processed = str_starts_with($validated['mime'], 'image/hei')
        ? convertirHeicAWebpDrive($inputPath, $temporaries)
        : normalizarImagenDrive($inputPath, $validated, $temporaries);
    $payload = [
        'ok' => true,
        'archivo' => [
            'nombre_original' => $validated['nombre_original'],
            'mime_original' => $validated['mime'],
            'extension_final' => $processed['extension_final'],
            'tamano_final' => $processed['tamano_final'],
            'dimensiones' => $processed['dimensiones'],
            'convertido' => $processed['convertido'],
            'error' => null,
        ],
    ];
} catch (DriveImportException $exception) {
    $status = match ($exception->errorType) {
        'expired_token' => 401,
        'access_denied' => 403,
        'invalid_folder' => 404,
        default => 502,
    };
    $payload = errorArchivoDrive($metadata, $exception->getMessage(), $validated['mime'] ?? null);
} catch (DriveImageException $exception) {
    $status = 422;
    $payload = errorArchivoDrive($metadata, $exception->getMessage(), $validated['mime'] ?? null);
} catch (Throwable) {
    $status = 500;
    $payload = errorArchivoDrive($metadata, 'No fue posible procesar la imagen.', $validated['mime'] ?? null);
} finally {
    limpiarTemporalesDrive($temporaries);
}

responderProcesamientoDrive($status, $payload);
