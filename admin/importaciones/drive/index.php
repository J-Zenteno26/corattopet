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
$pageTitle = 'Imágenes desde Drive';
$activeSection = 'importaciones';

$driveCssPath = dirname(__DIR__, 3) . '/public/css/admin-drive-import.css';
$driveCssVersion = is_file($driveCssPath) ? (string) filemtime($driveCssPath) : '1';

$driveJsPath = dirname(__DIR__, 3) . '/public/js/admin-drive-picker.js';
$driveJsVersion = is_file($driveJsPath) ? (string) filemtime($driveJsPath) : '1';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<link
    rel="stylesheet"
    href="<?= escape(appUrl('public/css/admin-drive-import.css') . '?v=' . $driveCssVersion) ?>"
>

<main class="admin-main admin-drive-page" id="contenido-principal">
    <header class="admin-page-header admin-drive-page__header">
        <div>
            <a
                class="admin-back-link"
                href="<?= escape(appUrl('admin/importaciones/index.php')) ?>"
            >
                ← Volver a importaciones
            </a>

            <span class="admin-drive-page__eyebrow">
                Importación de imágenes
            </span>

            <h1 class="admin-page-title">
                Imágenes de productos desde Google Drive
            </h1>

            <p>
                Selecciona una carpeta por SKU o una carpeta raíz que contenga
                subcarpetas nombradas con uno o varios SKU separados por coma.
            </p>
        </div>
    </header>

    <section
        class="admin-drive-shell"
        data-drive-picker
        data-client-id="<?= escape($clientId) ?>"
        data-api-key="<?= escape($apiKey) ?>"
        data-app-id="<?= escape($appId) ?>"
        data-analyze-url="<?= escape(appUrl('admin/importaciones/drive/analizar.php')) ?>"
        data-process-url="<?= escape(appUrl('admin/importaciones/drive/procesar-archivo.php')) ?>"
        data-import-url="<?= escape(appUrl('admin/importaciones/drive/importar.php')) ?>"
        data-csrf-token="<?= escape($csrfToken) ?>"
        aria-busy="false"
    >
        <section
            class="admin-panel admin-drive-start"
            aria-labelledby="drive-start-title"
        >
            <div class="admin-drive-start__content">
                <span class="admin-drive-start__icon" aria-hidden="true">
                    <i class="bi bi-google"></i>
                </span>

                <div>
                    <h2 id="drive-start-title">Seleccionar carpeta</h2>
                    <p>
                        La autorización es temporal. Las imágenes HEIC/HEIF se
                        convertirán a WebP antes de guardarse en el catálogo.
                    </p>
                </div>
            </div>

            <button
                class="admin-button admin-button--primary admin-drive-start__button"
                type="button"
                data-drive-select
                <?= $googleConfigured ? '' : 'disabled' ?>
            >
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                Seleccionar carpeta de Drive
            </button>
        </section>

        <?php if (!$googleConfigured): ?>
            <div class="admin-alert admin-alert--warning" role="alert">
                Configuración de Google Drive incompleta. Revisa las variables
                de entorno requeridas.
            </div>
        <?php endif; ?>

        <div class="admin-drive-feedback" aria-live="polite">
            <p
                class="admin-drive-feedback__status"
                data-drive-status
                role="status"
            ></p>

            <p
                class="admin-drive-feedback__error"
                data-drive-error
                role="alert"
                hidden
            ></p>
        </div>

        <section
            class="admin-drive-result"
            data-drive-result
            hidden
            aria-labelledby="drive-result-title"
        >
            <div class="admin-drive-result__heading">
                <div>
                    <span class="admin-drive-page__eyebrow">Vista previa</span>
                    <h2 id="drive-result-title">Resumen del análisis</h2>
                </div>

                <p>
                    Revisa los productos y archivos detectados antes de importar.
                </p>
            </div>

            <div class="admin-drive-summary-grid">
                <article class="admin-drive-summary-card">
                    <span>Carpeta seleccionada</span>
                    <strong data-drive-folder-name></strong>
                </article>

                <article class="admin-drive-summary-card">
                    <span>Carpetas de productos</span>
                    <strong data-drive-folder-count></strong>
                </article>

                <article class="admin-drive-summary-card">
                    <span>SKU detectados</span>
                    <strong data-drive-sku-count></strong>
                </article>

                <article class="admin-drive-summary-card">
                    <span>Productos encontrados</span>
                    <strong data-drive-product-count></strong>
                </article>
            </div>

            <p
                class="admin-alert admin-alert--warning"
                data-drive-root-ignored
                hidden
            ></p>

            <section
                class="admin-drive-server"
                aria-labelledby="drive-server-title"
            >
                <div>
                    <span class="admin-drive-page__eyebrow">Servidor</span>
                    <h3 id="drive-server-title">
                        Compatibilidad de imágenes
                    </h3>
                </div>

                <dl class="admin-drive-server__grid">
                    <div>
                        <dt>Imagick</dt>
                        <dd data-drive-imagick></dd>
                    </div>

                    <div>
                        <dt>Lectura HEIC/HEIF</dt>
                        <dd data-drive-heic-read></dd>
                    </div>

                    <div>
                        <dt>Escritura WebP</dt>
                        <dd data-drive-webp-write></dd>
                    </div>
                </dl>
            </section>

            <p
                class="admin-alert admin-alert--warning"
                data-drive-heic-warning
                role="alert"
                hidden
            ></p>

            <div class="admin-drive-folders" data-drive-folders></div>

            <div class="admin-drive-empty" data-drive-empty hidden>
                <i class="bi bi-folder-x" aria-hidden="true"></i>
                <strong>No se encontraron carpetas de productos</strong>
                <span>
                    Revisa la carpeta seleccionada y vuelve a intentarlo.
                </span>
            </div>
        </section>

        <div class="admin-drive-busy" data-drive-busy hidden>
            <div
                class="admin-drive-busy__card"
                role="status"
                aria-live="assertive"
            >
                <span
                    class="admin-drive-busy__spinner"
                    aria-hidden="true"
                ></span>

                <strong data-drive-busy-title>
                    Procesando imágenes
                </strong>

                <p data-drive-busy-message>
                    No cierres la página ni presiones otros botones hasta finalizar.
                </p>
            </div>
        </div>
    </section>
</main>

<script
    id="google-api-loader"
    src="https://apis.google.com/js/api.js"
    async
    defer
></script>

<script
    id="google-identity-services"
    src="https://accounts.google.com/gsi/client"
    async
    defer
></script>

<script
    src="<?= escape(appUrl('public/js/admin-drive-picker.js') . '?v=' . $driveJsVersion) ?>"
    defer
></script>

<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
