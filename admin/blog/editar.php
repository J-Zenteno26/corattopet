<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/seguridad.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-blog.php';
require_once __DIR__ . '/includes/consultas-blog.php';

requireRoles(['administrador', 'Blog']);

$articleId = normalizarIdBlog($_GET['id'] ?? null);
if ($articleId === null) {
    http_response_code(404);
    exit;
}

try {
    $connection = database();
    $article = obtenerArticuloEdicionBlog($connection, $articleId);
    if ($article === null) {
        http_response_code(404);
        exit;
    }
    $article['contenido_html'] = sanitizarContenidoBlog($article['contenido_html'] ?? '');
    $categories = listarCategoriasEdicionBlog($connection, (int) $article['id_categoria_blog']);
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Blog article edit load error', $exception);
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No fue posible cargar el artículo. Referencia: ' . escape($reference);
    exit;
}

$state = consumirEstadoMantenedor('blog_editar_' . $articleId);
$savedValues = is_array($state['valores'] ?? null) ? $state['valores'] : [];
$values = array_merge($article, $savedValues);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';

if ($errors !== [] || $generalError !== null) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible actualizar el artículo',
        'message' => $errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.',
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
}

$csrfToken = csrfToken();
$pageTitle = 'Editar artículo';
$activeSection = 'blog';
$blogCssPath = dirname(__DIR__, 2) . '/public/css/admin-blog.css';
$blogCssVersion = is_file($blogCssPath) ? (string) filemtime($blogCssPath) : '1';
$blogEditorJsPath = dirname(__DIR__, 2) . '/public/js/admin-blog-editor.js';
$blogEditorJsVersion = is_file($blogEditorJsPath) ? (string) filemtime($blogEditorJsPath) : '1';

require dirname(__DIR__, 2) . '/shared/admin-header.php';
require dirname(__DIR__, 2) . '/shared/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= escape(appUrl('public/css/admin-blog.css') . '?v=' . $blogCssVersion) ?>">
<main class="admin-main admin-blog blog-edit" id="contenido-principal">
    <header class="admin-page-header blog-edit__header">
        <div>
            <a class="admin-back-link" href="<?= escape(appUrl('admin/blog/index.php')) ?>">← Volver al blog</a>
            <h1 class="admin-page-title admin-page-title--paw">Editar artículo</h1>
            <p>Actualiza el contenido y la clasificación del artículo.</p>
        </div>
    </header>

    <div class="admin-form-layout admin-product-edit-shell">
        <form class="admin-form-layout__form admin-product-edit-form blog-edit__form" method="post"
            enctype="multipart/form-data" action="<?= escape(appUrl('admin/blog/actualizar.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= escape((string) $articleId) ?>">

            <section class="admin-panel admin-form-panel blog-edit__fields" aria-labelledby="blog-edit-data-title">
                <div class="admin-panel__header">
                    <h2 id="blog-edit-data-title">Datos del artículo</h2>
                    <p class="admin-panel__intro">Modifica la identificación, clasificación y resumen.</p>
                </div>
                <div class="admin-form-grid admin-blog-data-grid">
                    <div
                        class="admin-field admin-blog-field--title<?= isset($errors['titulo']) ? ' admin-field--invalid' : '' ?>">
                        <label for="titulo">Título <span class="admin-required" aria-hidden="true">*</span></label>
                        <input id="titulo" name="titulo" type="text" maxlength="180" required
                            value="<?= escape((string) $values['titulo']) ?>" <?= isset($errors['titulo']) ? 'aria-invalid="true" aria-describedby="titulo-error"' : '' ?>>
                        <?php if (isset($errors['titulo'])): ?><span class="admin-field__error"
                                id="titulo-error"><?= escape((string) $errors['titulo']) ?></span><?php endif; ?>
                    </div>

                    <div
                        class="admin-field admin-blog-field--category<?= isset($errors['id_categoria_blog']) ? ' admin-field--invalid' : '' ?>">
                        <label for="id_categoria_blog">Categoría <span class="admin-required"
                                aria-hidden="true">*</span></label>
                        <select id="id_categoria_blog" name="id_categoria_blog" required
                            <?= isset($errors['id_categoria_blog']) ? 'aria-invalid="true" aria-describedby="categoria-error"' : '' ?>>
                            <option value="">Seleccionar categoría</option>
                            <?php foreach ($categories as $category): ?>
                                <?php $categoryIsActive = booleanoPostgresMantenedor($category['activo']); ?>
                                <option value="<?= escape((string) $category['id_categoria_blog']) ?>" <?= (int) $values['id_categoria_blog'] === (int) $category['id_categoria_blog'] ? 'selected' : '' ?>>
                                    <?= escape((string) $category['nombre']) ?>    <?= $categoryIsActive ? '' : ' (inactiva)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['id_categoria_blog'])): ?><span class="admin-field__error"
                                id="categoria-error"><?= escape((string) $errors['id_categoria_blog']) ?></span><?php endif; ?>
                    </div>

                    <div
                        class="admin-field admin-blog-field--slug<?= isset($errors['slug']) ? ' admin-field--invalid' : '' ?>">
                        <label for="slug">Slug</label>
                        <input id="slug" name="slug" type="text" maxlength="210" autocomplete="off"
                            value="<?= escape((string) $values['slug']) ?>" <?= isset($errors['slug']) ? 'aria-invalid="true" aria-describedby="slug-help slug-error"' : 'aria-describedby="slug-help"' ?>>
                        <p class="admin-field__help" id="slug-help">Si lo dejas vacío, se generará desde el título.</p>
                        <?php if (isset($errors['slug'])): ?><span class="admin-field__error"
                                id="slug-error"><?= escape((string) $errors['slug']) ?></span><?php endif; ?>
                    </div>

                    <div
                        class="admin-field admin-blog-field--author<?= isset($errors['autor_publico']) ? ' admin-field--invalid' : '' ?>">
                        <label for="autor_publico">Autor público <span class="admin-required"
                                aria-hidden="true">*</span></label>
                        <input id="autor_publico" name="autor_publico" type="text" maxlength="120" required
                            value="<?= escape((string) $values['autor_publico']) ?>" <?= isset($errors['autor_publico']) ? 'aria-invalid="true" aria-describedby="autor-publico-error"' : '' ?>>
                        <?php if (isset($errors['autor_publico'])): ?><span class="admin-field__error"
                                id="autor-publico-error"><?= escape((string) $errors['autor_publico']) ?></span><?php endif; ?>
                    </div>

                    <div
                        class="admin-field admin-blog-field--excerpt<?= isset($errors['extracto']) ? ' admin-field--invalid' : '' ?>">
                        <label for="extracto">Extracto <span class="admin-required" aria-hidden="true">*</span></label>
                        <textarea id="extracto" name="extracto" rows="4" maxlength="360" required
                            <?= isset($errors['extracto']) ? 'aria-invalid="true" aria-describedby="extracto-help extracto-error"' : 'aria-describedby="extracto-help"' ?>><?= escape((string) $values['extracto']) ?></textarea>
                        <p class="admin-field__help" id="extracto-help">Resumen breve del contenido, máximo 360
                            caracteres.</p>
                        <?php if (isset($errors['extracto'])): ?><span class="admin-field__error"
                                id="extracto-error"><?= escape((string) $errors['extracto']) ?></span><?php endif; ?>
                    </div>

                    <div class="admin-alert admin-blog-draft-notice" role="status">
                        <span
                            class="admin-status-badge <?= escape(claseEstadoBlog((string) $article['estado'])) ?>"><?= escape(etiquetaEstadoBlog((string) $article['estado'])) ?></span>
                        <p>El estado actual es informativo y no se modificará desde este formulario.</p>
                    </div>
                </div>
            </section>

            <section class="admin-panel admin-form-panel blog-edit__content" aria-labelledby="blog-edit-content-title">
                <div class="admin-panel__header">
                    <h2 id="blog-edit-content-title">Contenido</h2>
                    <p class="admin-panel__intro">Edita el cuerpo del artículo con formato editorial controlado.</p>
                </div>
                <div class="admin-form-grid">
                    <?php $currentCoverUrl = urlPortadaBlog($article['imagen_portada'] ?? null); ?>
                    <?php $currentComplementaryUrl = urlPortadaBlog($article['imagen_complementaria'] ?? null); ?>

                    <div class="admin-blog-media-grid">
                        <div class="admin-blog-media-card">
                            <div class="admin-blog-media-card__header">
                                <p>Portada actual</p>
                            </div>

                            <?php if ($currentCoverUrl !== null): ?>
                                <div class="admin-blog-media-card__preview">
                                    <img src="<?= escape($currentCoverUrl) ?>"
                                        alt="Portada actual de <?= escape((string) $article['titulo']) ?>">
                                </div>
                            <?php else: ?>
                                <div class="admin-blog-media-card__empty">
                                    Sin portada actual
                                </div>
                            <?php endif; ?>

                            <div
                                class="admin-field<?= isset($errors['imagen_portada']) ? ' admin-field--invalid' : '' ?>">
                                <label for="imagen_portada">Reemplazar portada (opcional)</label>

                                <input id="imagen_portada" name="imagen_portada" type="file"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    <?= isset($errors['imagen_portada'])
                                        ? 'aria-invalid="true" aria-describedby="imagen-portada-help imagen-portada-error"'
                                        : 'aria-describedby="imagen-portada-help"' ?>>

                                <p class="admin-field__help" id="imagen-portada-help">
                                    JPG, PNG o WEBP. Máximo 2 MB.
                                </p>

                                <?php if (isset($errors['imagen_portada'])): ?>
                                    <span class="admin-field__error" id="imagen-portada-error">
                                        <?= escape((string) $errors['imagen_portada']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>


                        <div class="admin-blog-media-card">
                            <div class="admin-blog-media-card__header">
                                <p>Imagen complementaria</p>
                            </div>

                            <?php if ($currentComplementaryUrl !== null): ?>
                                <div class="admin-blog-media-card__preview">
                                    <img src="<?= escape($currentComplementaryUrl) ?>"
                                        alt="Imagen complementaria actual de <?= escape((string) $article['titulo']) ?>">
                                </div>
                            <?php else: ?>
                                <div class="admin-blog-media-card__empty">
                                    Sin imagen complementaria
                                </div>
                            <?php endif; ?>

                            <div
                                class="admin-field<?= isset($errors['imagen_complementaria']) ? ' admin-field--invalid' : '' ?>">
                                <label for="imagen_complementaria">
                                    <?= $currentComplementaryUrl !== null
                                        ? 'Reemplazar imagen (opcional)'
                                        : 'Agregar imagen (opcional)' ?>
                                </label>

                                <input id="imagen_complementaria" name="imagen_complementaria" type="file"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    <?= isset($errors['imagen_complementaria'])
                                        ? 'aria-invalid="true" aria-describedby="imagen-complementaria-help imagen-complementaria-error"'
                                        : 'aria-describedby="imagen-complementaria-help"' ?>>

                                <p class="admin-field__help" id="imagen-complementaria-help">
                                    JPG, PNG o WEBP. Máximo 2 MB.
                                </p>

                                <?php if ($currentComplementaryUrl !== null): ?>
                                    <label class="admin-blog-media-card__remove">
                                        <input type="checkbox" name="eliminar_imagen_complementaria" value="1">
                                        Eliminar imagen complementaria
                                    </label>
                                <?php endif; ?>

                                <?php if (isset($errors['imagen_complementaria'])): ?>
                                    <span class="admin-field__error" id="imagen-complementaria-error">
                                        <?= escape((string) $errors['imagen_complementaria']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <div
                        class="admin-field admin-field--full<?= isset($errors['video_url']) ? ' admin-field--invalid' : '' ?>">
                        <label for="video_url">Enlace de video (opcional)</label>
                        <input id="video_url" name="video_url" type="url" maxlength="500" placeholder="https://..."
                            value="<?= escape((string) ($values['video_url'] ?? '')) ?>" <?= isset($errors['video_url']) ? 'aria-invalid="true" aria-describedby="video-url-help video-url-error"' : 'aria-describedby="video-url-help"' ?>>
                        <p class="admin-field__help" id="video-url-help">Puedes enlazar YouTube, Instagram, TikTok,
                            Vimeo u otra plataforma.</p>
                        <?php if (isset($errors['video_url'])): ?><span class="admin-field__error"
                                id="video-url-error"><?= escape((string) $errors['video_url']) ?></span><?php endif; ?>
                    </div>

                    <div
                        class="admin-field admin-field--full<?= isset($errors['contenido_html']) ? ' admin-field--invalid' : '' ?>">
                        <label for="contenido_html">Contenido del artículo <span class="admin-required"
                                aria-hidden="true">*</span></label>
                        <div class="admin-blog-editor" data-blog-editor>
                            <div class="admin-blog-editor__toolbar" role="toolbar" aria-label="Formato del contenido">
                                <button type="button" data-editor-command="formatBlock"
                                    data-editor-value="p">Párrafo</button>
                                <button type="button" data-editor-command="formatBlock"
                                    data-editor-value="h2">H2</button>
                                <button type="button" data-editor-command="formatBlock"
                                    data-editor-value="h3">H3</button>
                                <button type="button" data-editor-command="bold"
                                    aria-label="Negrita"><strong>B</strong></button>
                                <button type="button" data-editor-command="italic"
                                    aria-label="Cursiva"><em>I</em></button>
                                <button type="button" data-editor-command="insertUnorderedList">Viñetas</button>
                                <button type="button" data-editor-command="insertOrderedList">Numeración</button>
                                <button type="button" data-editor-command="formatBlock"
                                    data-editor-value="blockquote">Cita</button>
                                <button type="button" data-editor-command="createLink">Enlace</button>
                                <button type="button" data-editor-command="undo" aria-label="Deshacer">↶</button>
                                <button type="button" data-editor-command="redo" aria-label="Rehacer">↷</button>
                            </div>
                            <div class="admin-blog-editor__surface" contenteditable="true" role="textbox"
                                aria-multiline="true" aria-label="Contenido del artículo" data-blog-editor-surface
                                data-placeholder="Comienza a escribir el artículo..."></div>
                            <textarea id="contenido_html" class="admin-blog-content admin-blog-editor__source"
                                name="contenido_html" maxlength="100000" required data-blog-editor-source
                                <?= isset($errors['contenido_html']) ? 'aria-invalid="true" aria-describedby="contenido-help contenido-error"' : 'aria-describedby="contenido-help"' ?>><?= escape((string) $values['contenido_html']) ?></textarea>
                            <p class="admin-field__help" id="contenido-help">Usa subtítulos, listas, citas y enlaces. El
                                sistema eliminará formatos no permitidos.</p>
                            <p class="admin-field__error admin-blog-editor__client-error" data-blog-editor-error hidden>
                            </p>
                        </div>
                        <?php if (isset($errors['contenido_html'])): ?><span class="admin-field__error"
                                id="contenido-error"><?= escape((string) $errors['contenido_html']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="admin-form-actions admin-form-actions--inside blog-edit__actions">
                    <a class="admin-button" href="<?= escape(appUrl('admin/blog/index.php')) ?>">Cancelar</a>
                    <button class="admin-button admin-button--primary" type="submit">Guardar cambios</button>
                </div>
            </section>
        </form>
    </div>
</main>
<script src="<?= escape(appUrl('public/js/admin-blog-editor.js') . '?v=' . $blogEditorJsVersion) ?>" defer></script>
<?php require dirname(__DIR__, 2) . '/shared/admin-footer.php'; ?>