<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once __DIR__ . '/includes/funciones-drive.php';
require_once dirname(__DIR__, 2) . '/inventario/productos/imagenes/includes/funciones-imagenes-producto.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderImportacionDrive(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

function imagenDriveYaRegistrada(PDO $connection, int $productId, string $originalName): bool
{
    $statement = $connection->prepare(
        'SELECT 1
         FROM imagenes_producto
         WHERE id_producto = :id_producto
           AND activo = TRUE
           AND LOWER(TRIM(nombre_original)) = LOWER(TRIM(:nombre_original))
         LIMIT 1'
    );
    $statement->execute([
        'id_producto' => $productId,
        'nombre_original' => mb_substr(basename($originalName), 0, 255),
    ]);

    return $statement->fetchColumn() !== false;
}

requireAuthentication();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderImportacionDrive(405, [
        'ok' => false,
        'message' => 'Método no permitido.',
    ]);
}

if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    responderImportacionDrive(403, [
        'ok' => false,
        'message' => 'La solicitud no es válida. Recarga la página.',
    ]);
}

$folderId = idCarpetaDriveValido($_POST['folder_id'] ?? null);
$accessToken = tokenTemporalDriveValido($_POST['access_token'] ?? null);

if ($folderId === null) {
    responderImportacionDrive(422, [
        'ok' => false,
        'message' => 'La carpeta seleccionada no es válida.',
    ]);
}

if ($accessToken === null) {
    responderImportacionDrive(401, [
        'ok' => false,
        'message' => 'La autorización de Google no es válida o venció.',
    ]);
}

$temporaries = [];

try {
    $connection = database();
    $folder = obtenerCarpetaDrive($folderId, $accessToken);
    $items = listarHijosDirectosDrive($folderId, $accessToken);
    $analysis = analizarSubcarpetaProductoDrive(
        [
            'id' => $folder['id'],
            'nombre' => $folder['nombre'],
        ],
        $items
    );

    $products = obtenerProductosDrivePorSkus(
        $connection,
        $analysis['sku_detectados']
    );
    $associated = asociarProductosAnalisisDrive([$analysis], $products);
    $analysis = $associated[0];

    if ($analysis['sku_detectados'] === []) {
        responderImportacionDrive(422, [
            'ok' => false,
            'message' => 'El nombre de la carpeta no contiene SKU válidos.',
        ]);
    }

    if ($analysis['productos_encontrados'] === []) {
        responderImportacionDrive(422, [
            'ok' => false,
            'message' => 'No se encontró ningún producto para los SKU de esta carpeta.',
            'sku_inexistentes' => $analysis['sku_inexistentes'],
        ]);
    }

    $images = array_merge(
        $analysis['imagenes_compatibles'],
        $analysis['imagenes_heic_heif']
    );

    if ($images === []) {
        responderImportacionDrive(422, [
            'ok' => false,
            'message' => 'La carpeta no contiene imágenes compatibles.',
        ]);
    }

    $primaryFileId = (string) ($analysis['portada_detectada']['id'] ?? '');
    $results = [];
    $downloaded = 0;
    $converted = 0;
    $saved = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($images as $file) {
        $fileId = idArchivoDriveValido($file['id'] ?? null);
        $originalName = basename((string) ($file['nombre'] ?? 'imagen'));

        if ($fileId === null || $originalName === '') {
            $errors++;
            $results[] = [
                'archivo' => $originalName !== '' ? $originalName : 'Archivo sin nombre',
                'estado' => 'error',
                'message' => 'El archivo no tiene metadatos válidos.',
                'productos' => [],
            ];
            continue;
        }

        $fileResult = [
            'archivo' => $originalName,
            'estado' => 'procesado',
            'convertido' => false,
            'productos' => [],
        ];

        try {
            $metadata = obtenerMetadatosArchivoDrive($fileId, $accessToken);

            if (!in_array($folderId, $metadata['parents'], true)) {
                throw new DriveImageException(
                    'El archivo ya no pertenece directamente a la carpeta seleccionada.'
                );
            }

            $downloadPath = crearTemporalSeguroDrive();
            $temporaries[] = $downloadPath;

            descargarArchivoDrive($fileId, $accessToken, $downloadPath);
            $downloaded++;

            $validated = validarDescargaImagenDrive($downloadPath, $metadata);

            if (str_starts_with($validated['mime'], 'image/hei')) {
                $processed = convertirHeicAWebpDrive($downloadPath, $temporaries);
            } else {
                $processed = normalizarImagenDrive(
                    $downloadPath,
                    $validated,
                    $temporaries
                );
            }

            $fileResult['convertido'] = (bool) $processed['convertido'];
            $fileResult['extension_final'] = $processed['extension_final'];
            $fileResult['tamano_final'] = $processed['tamano_final'];
            $fileResult['dimensiones'] = $processed['dimensiones'];

            if ($processed['convertido']) {
                $converted++;
            }

            foreach ($analysis['productos_encontrados'] as $product) {
                $productId = (int) $product['id_producto'];
                $productResult = [
                    'id_producto' => $productId,
                    'sku' => (string) $product['sku'],
                    'nombre' => (string) $product['nombre'],
                ];

                try {
                    if (imagenDriveYaRegistrada(
                        $connection,
                        $productId,
                        $originalName
                    )) {
                        $skipped++;
                        $productResult['estado'] = 'omitida';
                        $productResult['message'] = 'La imagen ya está registrada para este producto.';
                        $fileResult['productos'][] = $productResult;
                        continue;
                    }

                    $stored = guardarImagenProductoDesdeArchivoLocal(
                        $connection,
                        $productId,
                        (string) $processed['temporal_final'],
                        $originalName,
                        (string) $product['nombre'],
                        $fileId === $primaryFileId,
                        (string) $processed['mime_final'],
                        (string) $processed['extension_final']
                    );

                    $saved++;
                    $productResult['estado'] = 'guardada';
                    $productResult['id_imagen'] = $stored['id_imagen'];
                    $productResult['archivo'] = $stored['archivo'];
                    $productResult['es_principal'] = $stored['es_principal'];
                } catch (ImagenProductoException $exception) {
                    $skipped++;
                    $productResult['estado'] = 'omitida';
                    $productResult['message'] = $exception->getMessage();
                } catch (Throwable $exception) {
                    $errors++;
                    error_log(
                        'Drive image persistence error: ' . $exception->getMessage()
                    );
                    $productResult['estado'] = 'error';
                    $productResult['message'] = 'No fue posible guardar la imagen para este producto.';
                }

                $fileResult['productos'][] = $productResult;
            }
        } catch (DriveImportException $exception) {
            throw $exception;
        } catch (DriveImageException $exception) {
            $errors++;
            $fileResult['estado'] = 'error';
            $fileResult['message'] = $exception->getMessage();
        } catch (Throwable $exception) {
            $errors++;
            error_log('Drive folder import error: ' . $exception->getMessage());
            $fileResult['estado'] = 'error';
            $fileResult['message'] = 'No fue posible procesar este archivo.';
        }

        $results[] = $fileResult;
    }

    responderImportacionDrive(200, [
        'ok' => true,
        'message' => $saved > 0
            ? 'Importación de imágenes completada.'
            : 'La importación terminó sin imágenes nuevas.',
        'carpeta' => [
            'id' => $folder['id'],
            'nombre' => $folder['nombre'],
        ],
        'sku_detectados' => $analysis['sku_detectados'],
        'sku_inexistentes' => $analysis['sku_inexistentes'],
        'resumen' => [
            'archivos_detectados' => count($images),
            'archivos_descargados' => $downloaded,
            'archivos_convertidos' => $converted,
            'imagenes_guardadas' => $saved,
            'imagenes_omitidas' => $skipped,
            'errores' => $errors,
        ],
        'resultados' => $results,
    ]);
} catch (DriveImportException $exception) {
    $status = match ($exception->errorType) {
        'expired_token' => 401,
        'access_denied' => 403,
        'invalid_folder' => 422,
        default => 502,
    };

    responderImportacionDrive($status, [
        'ok' => false,
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log('Drive import fatal error: ' . $exception->getMessage());

    responderImportacionDrive(500, [
        'ok' => false,
        'message' => 'No fue posible importar las imágenes. Intenta nuevamente.',
    ]);
} finally {
    limpiarTemporalesDrive($temporaries);
}
