<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once __DIR__ . '/includes/funciones-drive.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderAnalisisDrive(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

requireAuthentication();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderAnalisisDrive(405, ['ok' => false, 'message' => 'Método no permitido.']);
}
if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
    responderAnalisisDrive(403, ['ok' => false, 'message' => 'La solicitud no es válida. Recarga la página.']);
}

$folderId = idCarpetaDriveValido($_POST['folder_id'] ?? null);
$accessToken = tokenTemporalDriveValido($_POST['access_token'] ?? null);
if ($folderId === null) {
    responderAnalisisDrive(422, ['ok' => false, 'message' => 'La carpeta seleccionada no es válida.']);
}
if ($accessToken === null) {
    responderAnalisisDrive(401, ['ok' => false, 'message' => 'La autorización de Google no es válida o venció.']);
}

try {
    $folder = obtenerCarpetaDrive($folderId, $accessToken);
    $rootItems = listarHijosDirectosDrive($folderId, $accessToken);

    $subfolders = array_values(array_filter(
        $rootItems,
        static fn (array $item): bool => ($item['tipo'] ?? '') === 'Carpeta'
    ));

    $folderAnalysis = [];
    $allSkus = [];
    $ignoredRootItems = [];
    $analysisMode = 'root';

    if ($subfolders !== []) {
        /*
         * Modo carpeta raíz:
         * la carpeta seleccionada contiene subcarpetas nombradas por SKU.
         */
        $ignoredRootItems = array_values(array_filter(
            $rootItems,
            static fn (array $item): bool => ($item['tipo'] ?? '') !== 'Carpeta'
        ));

        foreach ($subfolders as $subfolder) {
            $children = listarHijosDirectosDrive((string) $subfolder['id'], $accessToken);
            $analysis = analizarSubcarpetaProductoDrive($subfolder, $children);
            $folderAnalysis[] = $analysis;
            array_push($allSkus, ...$analysis['sku_detectados']);
        }
    } else {
        /*
         * Modo carpeta de producto:
         * la carpeta seleccionada se llama como el SKU y contiene imágenes directas.
         */
        $analysisMode = 'product_folder';
        $analysis = analizarSubcarpetaProductoDrive(
            [
                'id' => (string) $folder['id'],
                'nombre' => (string) $folder['nombre'],
            ],
            $rootItems
        );
        $folderAnalysis[] = $analysis;
        array_push($allSkus, ...$analysis['sku_detectados']);
    }
    $products = obtenerProductosDrivePorSkus(database(), $allSkus);
    $folderAnalysis = asociarProductosAnalisisDrive($folderAnalysis, $products);
    $heicDiagnosis = diagnosticarSoporteHeic();
    $hasHeic = count(array_filter($folderAnalysis, static fn (array $analysis): bool => $analysis['imagenes_heic_heif'] !== [])) > 0;
    $canConvertHeic = $heicDiagnosis['imagick_instalado']
        && $heicDiagnosis['heic_lectura_disponible']
        && $heicDiagnosis['webp_escritura_disponible'];
    responderAnalisisDrive(200, [
        'ok' => true,
        'message' => $analysisMode === 'product_folder'
            ? 'Carpeta de producto analizada correctamente.'
            : 'Estructura de carpetas analizada correctamente.',
        'modo_analisis' => $analysisMode,
        'carpeta' => $folder,
        'cantidad_subcarpetas' => count($folderAnalysis),
        'cantidad_skus' => count(array_unique($allSkus)),
        'cantidad_productos_encontrados' => count($products),
        'carpetas' => $folderAnalysis,
        'elementos_raiz_ignorados' => array_map(
            static fn (array $item): array => [
                ...metadatosArchivoDrive($item),
                'motivo' => 'Solo se analizan imágenes dentro de subcarpetas de productos.',
            ],
            $ignoredRootItems
        ),
        'diagnostico_heic' => $heicDiagnosis,
        'advertencia_heic' => $hasHeic && !$canConvertHeic
            ? 'HEIC detectado, pero este servidor todavía no dispone del conversor requerido.'
            : null,
    ]);
} catch (DriveImportException $exception) {
    $status = match ($exception->errorType) {
        'expired_token' => 401,
        'access_denied' => 403,
        'invalid_folder' => 422,
        default => 502,
    };
    responderAnalisisDrive($status, ['ok' => false, 'message' => $exception->getMessage()]);
} catch (Throwable) {
    responderAnalisisDrive(500, ['ok' => false, 'message' => 'No fue posible analizar la carpeta. Intenta nuevamente.']);
}
