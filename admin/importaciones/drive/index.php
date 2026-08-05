<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once dirname(__DIR__, 3) . '/shared/seguridad.php';

requireAuthentication();

$clientId = trim((string) env('GOOGLE_DRIVE_CLIENT_ID', ''));
$apiKey = trim((string) env('GOOGLE_PICKER_API_KEY', ''));
$appId = trim((string) env('GOOGLE_DRIVE_APP_ID', ''));

$googleConfigured = $clientId !== '' && $apiKey !== '' && $appId !== '';
$csrfToken = csrfToken();
$pageTitle = 'Analizar imágenes de Drive';
$activeSection = 'importaciones';
$driveJsPath = dirname(__DIR__, 3) . '/public/js/admin-drive-picker.js';
$driveJsVersion = is_file($driveJsPath) ? (string) filemtime($driveJsPath) : '1';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="<?= escape(appUrl('admin/importaciones/index.php')) ?>">← Volver a importaciones</a>
            <h1 class="admin-page-title">Analizar imágenes de productos en Drive</h1>
            <p>Selecciona la carpeta raíz cuyas subcarpetas están nombradas con uno o varios SKU.</p>
        </div>
    </header>

    <section
        class="admin-panel"
        data-drive-picker
        data-client-id="<?= escape($clientId) ?>"
        data-api-key="<?= escape($apiKey) ?>"
        data-app-id="<?= escape($appId) ?>"
        data-analyze-url="<?= escape(appUrl('admin/importaciones/drive/analizar.php')) ?>"
        data-process-url="<?= escape(appUrl('admin/importaciones/drive/procesar-archivo.php')) ?>"
        data-csrf-token="<?= escape($csrfToken) ?>"
    >
        <div class="admin-panel__header">
            <div><h2>Seleccionar carpeta</h2><p>La autorización temporal se mantendrá únicamente en esta página. Puedes probar la descarga y conversión sin guardar imágenes.</p></div>
            <button class="admin-button admin-button--primary" type="button" data-drive-select <?= $googleConfigured ? '' : 'disabled' ?>>Seleccionar carpeta de Drive</button>
        </div>

        <?php if (!$googleConfigured): ?>
            <div class="admin-alert" role="alert">Configuración de Google Drive incompleta. Revisa las variables de entorno requeridas.</div>
        <?php endif; ?>
        <p data-drive-status role="status" aria-live="polite"></p>
        <p data-drive-error role="alert" hidden></p>

        <section data-drive-result hidden aria-labelledby="drive-result-title">
            <h2 id="drive-result-title">Resumen del análisis</h2>
            <dl>
                <div><dt>Carpeta raíz</dt><dd data-drive-folder-name></dd></div>
                <div><dt>Subcarpetas de productos</dt><dd data-drive-folder-count></dd></div>
                <div><dt>SKU detectados</dt><dd data-drive-sku-count></dd></div>
                <div><dt>Productos encontrados</dt><dd data-drive-product-count></dd></div>
            </dl>
            <p data-drive-root-ignored hidden></p>
            <h3>Estado del servidor para HEIC/HEIF</h3>
            <dl>
                <div><dt>Imagick</dt><dd data-drive-imagick></dd></div>
                <div><dt>Lectura HEIC/HEIF</dt><dd data-drive-heic-read></dd></div>
                <div><dt>Escritura WEBP</dt><dd data-drive-webp-write></dd></div>
            </dl>
            <p data-drive-heic-warning role="alert" hidden></p>
            <div data-drive-folders></div>
            <p data-drive-empty hidden>La carpeta raíz no contiene subcarpetas de productos.</p>
        </section>
    </section>
</main>
<script id="google-api-loader" src="https://apis.google.com/js/api.js" async defer></script>
<script id="google-identity-services" src="https://accounts.google.com/gsi/client" async defer></script>
<script src="<?= escape(appUrl('public/js/admin-drive-picker.js') . '?v=' . $driveJsVersion) ?>" defer></script>
<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
