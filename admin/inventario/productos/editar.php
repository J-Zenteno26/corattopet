<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 3) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-producto.php';
require_once __DIR__ . '/consultas/buscar-producto.php';
require_once __DIR__ . '/imagenes/includes/funciones-imagenes-producto.php';

requireAuthentication();

$productId = idPositivoProducto($_GET['id'] ?? null);

if ($productId === null) {
    guardarModalAdmin('error', 'No fue posible abrir el producto', 'El producto indicado no es válido.');
    header(
        'Location: ' . appUrl(
            'admin/inventario/index.php'
        ),
        true,
        302
    );
    exit;
}

try {
    $connection = database();

    $product = buscarProductoParaEditar(
        $connection,
        $productId
    );

    if ($product === null) {
        guardarModalAdmin('error', 'No fue posible abrir el producto', 'El producto indicado no existe.');
        header(
            'Location: ' . appUrl(
                'admin/inventario/index.php'
            ),
            true,
            302
        );
        exit;
    }

    $options = obtenerOpcionesProducto(
        $connection,
        (int) $product['id_categoria'],
        (int) $product['id_marca']
    );
    $productImages = listarImagenesProducto($connection, $productId);

    $databaseValues = valoresEdicionProducto($product);
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin('Product edit load error', $exception);
    guardarModalAdmin('error', 'No fue posible abrir el producto', 'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.', ['reference' => $reference]);

    header(
        'Location: ' . appUrl(
            'admin/inventario/index.php'
        ),
        true,
        302
    );
    exit;
}

$stateKey = 'producto_editar_' . $productId;
$state = consumirEstadoFormularioProducto($stateKey);

$values = array_merge(
    $databaseValues,
    $state['valores'] ?? []
);
$fractionable = esProductoFraccionable($product);
$primaryImage = $productImages[0] ?? null;
$primaryImageUrl = is_array($primaryImage) ? urlPublicaImagenProducto($primaryImage['archivo'] ?? null) : null;

$errors = is_array($state['errores'] ?? null)
    ? $state['errores']
    : [];

$generalError = is_string($state['error_general'] ?? null)
    ? $state['error_general']
    : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';
if ($errors !== [] || $generalError !== null) {
    $adminModal = [
        'type' => 'error',
        'title' => 'No fue posible actualizar el producto',
        'message' => $errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.',
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
}

$selectedCategoryHasSubcategories = array_filter(
    $options['subcategorias'],
    static fn (array $subcategory): bool =>
        (string) ($subcategory['id_categoria'] ?? '') === (string) ($values['id_categoria'] ?? '')
) !== [];
$selectedCategoryIsDryFood =
    array_filter(
        $options['categorias'],
        static fn (array $category): bool =>
            (string) ($category['id_categoria'] ?? '') === (string) ($values['id_categoria'] ?? '')
            && (string) ($category['slug'] ?? '') === CATEGORIA_ALIMENTOS_SLUG
    ) !== []
    && codigoSubcategoriaProducto(
        (string) (
            ($values['subcategoria_codigo'] ?? '') !== ''
                ? $values['subcategoria_codigo']
                : ($values['subcategoria'] ?? '')
        )
    ) === 'alimento-seco';

$csrfToken = csrfToken();
$pageTitle = 'Editar producto';
$activeSection = 'inventario';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>

<main class="admin-main" id="contenido-principal">

    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="<?= escape(
                appUrl('admin/inventario/index.php')
            ) ?>">
                ← Volver al inventario
            </a>

            <h1 class="admin-page-title admin-page-title--paw">
                Editar producto
            </h1>

            <p>
                Actualiza la información comercial y descriptiva del producto.
            </p>
        </div>
    </header>

    <div class="admin-form-layout admin-product-edit-shell">
        <div class="admin-product-edit-layout">
            <aside class="admin-product-edit-media">
                <section
                    class="admin-panel admin-product-images admin-product-media-panel admin-product-edit-media-card"
                    aria-labelledby="product-images-title">
                    <div class="admin-panel__header">
                        <h2 id="product-images-title">GESTIÓN IMÁGENES</h2>
                        <p class="admin-panel__intro">Administra la imagen principal y galería. Puedes mantener hasta 10
                            imágenes activas.</p>
                    </div>

                    <div class="admin-product-media-hero">
                        <div class="admin-product-media-hero__image admin-product-edit-main-image">
                            <?php if ($primaryImageUrl !== null): ?>
                                <img src="<?= escape($primaryImageUrl) ?>"
                                    alt="<?= escape((string) (($primaryImage['texto_alternativo'] ?? '') ?: $product['nombre'])) ?>">
                                <span class="admin-product-media-hero__badge">Imagen principal</span>
                            <?php else: ?>
                                <div class="admin-product-media-hero__placeholder">
                                    <span aria-hidden="true">🐾</span>
                                    <strong>Este producto aún no tiene imagen principal</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="admin-product-media-info">
                            <span class="admin-product-media-info__eyebrow">Ficha de catálogo</span>
                            <h3 class="admin-product-media-info__title"><?= escape((string) $product['nombre']) ?></h3>
                            <dl class="admin-product-media-info__meta admin-product-edit-summary">
                                <div>
                                    <dt>SKU</dt>
                                    <dd><?= escape((string) ($product['sku'] ?: 'Sin SKU')) ?></dd>
                                </div>
                                <div>
                                    <dt>Tipo</dt>
                                    <dd><?= $fractionable ? 'Alimento fraccionable' : 'Producto por unidad' ?></dd>
                                </div>
                                <div>
                                    <dt>Estado</dt>
                                    <dd><span
                                            class="admin-status-badge <?= (string) $product['estado'] === 'activo' ? 'is-active' : 'is-inactive' ?>"><?= (string) $product['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?></span>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Stock actual</dt>
                                    <dd><?= escape(formatearCantidadStock((int) $product['cantidad_actual'], $fractionable)) ?>
                                    </dd>
                                </div>
                            </dl>
                            <?php if (is_array($primaryImage)): ?>
                                <p class="admin-product-media-info__alt"><strong>Texto alternativo:</strong>
                                    <?= escape((string) (($primaryImage['texto_alternativo'] ?? '') ?: 'Sin texto alternativo')) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form class="admin-product-images__upload admin-product-edit-upload" method="post"
                        enctype="multipart/form-data"
                        action="<?= escape(appUrl('admin/inventario/productos/imagenes/subir.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                        <input type="hidden" name="id_producto" value="<?= (int) $productId ?>">
                        <div class="admin-field admin-product-image-upload__file">
                            <label for="imagen-producto">Archivos de imagen <span class="admin-required">*</span></label>
                            <input
                                id="imagen-producto"
                                name="imagenes[]"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                multiple
                                required
                                data-max-files="<?= max(0, IMAGEN_PRODUCTO_MAX_CANTIDAD - count($productImages)) ?>"
                            >
                            <span class="admin-field__help" id="imagenes-producto-ayuda">
                                Puedes seleccionar hasta <?= max(0, IMAGEN_PRODUCTO_MAX_CANTIDAD - count($productImages)) ?>
                                imagen(es). JPG, PNG o WEBP; máximo 2 MB por archivo.
                            </span>
                        </div>
                        <div class="admin-field admin-product-image-upload__alt">
                            <label for="alt-text-imagen">Texto alternativo</label>
                            <input id="alt-text-imagen" name="alt_text" type="text" maxlength="180"
                                placeholder="Describe brevemente la imagen">
                            <span class="admin-field__help">Opcional para accesibilidad.</span>
                        </div>
                        <button class="admin-button admin-button--primary" type="submit" <?= count($productImages) >= IMAGEN_PRODUCTO_MAX_CANTIDAD ? 'disabled' : '' ?>>Subir imágenes</button>
                    </form>

                    <?php if ($productImages !== []): ?>
                        <div>
                            <h3 class="admin-product-media-gallery__title">Galería de imágenes</h3>
                            <div class="admin-product-media-gallery admin-product-edit-gallery">
                                <?php foreach ($productImages as $image): ?>
                                    <?php
                                    $imageId = (int) $image['id_imagen'];
                                    $isPrimaryImage = in_array($image['es_principal'], [true, 1, '1', 't', 'true'], true);
                                    $imageUrl = urlPublicaImagenProducto($image['archivo']);
                                    $deleteFormId = 'eliminar-imagen-' . $imageId;
                                    ?>
                                    <article class="admin-product-image-card admin-product-media-card">
                                        <div class="admin-product-image-card__thumb admin-product-media-card__thumb">
                                            <?php if ($imageUrl !== null): ?>
                                                <img src="<?= escape($imageUrl) ?>"
                                                    alt="<?= escape((string) ($image['texto_alternativo'] ?: $product['nombre'])) ?>"
                                                    loading="lazy">
                                            <?php endif; ?>
                                            <?php if ($isPrimaryImage): ?><span
                                                    class="admin-product-image-card__badge">Principal</span><?php endif; ?>
                                        </div>
                                        <div class="admin-product-image-card__meta">
                                            <strong><?= escape((string) ($image['nombre_original'] ?: 'Imagen del producto')) ?></strong>
                                            <span><?= escape((string) ($image['texto_alternativo'] ?: 'Sin texto alternativo')) ?></span>
                                        </div>
                                        <div class="admin-product-image-card__actions admin-product-media-card__actions">
                                            <?php if (!$isPrimaryImage): ?>
                                                <form method="post"
                                                    action="<?= escape(appUrl('admin/inventario/productos/imagenes/marcar-principal.php')) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                                    <input type="hidden" name="id_producto" value="<?= (int) $productId ?>">
                                                    <input type="hidden" name="id_imagen" value="<?= $imageId ?>">
                                                    <button class="admin-button admin-button--small" type="submit">Marcar
                                                        principal</button>
                                                </form>
                                            <?php endif; ?>
                                            <form id="<?= escape($deleteFormId) ?>" method="post"
                                                action="<?= escape(appUrl('admin/inventario/productos/imagenes/eliminar.php')) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                                <input type="hidden" name="id_producto" value="<?= (int) $productId ?>">
                                                <input type="hidden" name="id_imagen" value="<?= $imageId ?>">
                                            </form>
                                            <button class="admin-button admin-button--small admin-button--danger" type="button"
                                                data-admin-confirm-form="<?= escape($deleteFormId) ?>"
                                                data-modal-title="Eliminar imagen"
                                                data-modal-message="La imagen dejará de mostrarse en el inventario y catálogo. No se eliminará el producto."
                                                data-modal-primary="Eliminar imagen" data-modal-secondary="Cancelar"
                                                data-modal-destructive="true">Eliminar</button>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
            <form class="admin-product-form admin-form-layout__form admin-product-edit-form" method="post" action="<?= escape(
                appUrl(
                    'admin/inventario/productos/actualizar.php'
                )
            ) ?>">
                <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

                <input type="hidden" name="id_producto" value="<?= (int) $productId ?>">

                <section class="admin-panel admin-form-panel" aria-labelledby="main-information-title">
                    <div class="admin-panel__header">
                        <h2 id="main-information-title">
                            INFORMACIÓN PRINCIPAL
                        </h2>

                        <p class="admin-panel__intro">
                            Los campos marcados con
                            <span class="admin-required">*</span>
                            son obligatorios.
                        </p>
                    </div>

                    <div class="admin-form-grid">

                        <div class="admin-field admin-field--full<?= isset($errors['nombre'])
                            ? ' admin-field--invalid'
                            : '' ?>">
                            <label for="nombre">
                                Nombre del producto
                                <span class="admin-required">*</span>
                            </label>

                            <input id="nombre" name="nombre" type="text" maxlength="180" required value="<?= escape(
                                (string) $values['nombre']
                            ) ?>" <?= isset($errors['nombre'])
                                 ? 'aria-invalid="true" aria-describedby="nombre-error"'
                                 : '' ?>>

                            <?php if (isset($errors['nombre'])): ?>
                                <span class="admin-field__error" id="nombre-error">
                                    <?= escape(
                                        (string) $errors['nombre']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-field<?= isset($errors['id_categoria'])
                            ? ' admin-field--invalid'
                            : '' ?>">
                            <label for="id_categoria">
                                Categoría
                                <span class="admin-required">*</span>
                            </label>

                            <select id="id_categoria" name="id_categoria" required <?= isset($errors['id_categoria'])
                                ? 'aria-invalid="true" aria-describedby="categoria-error"'
                                : '' ?>>
                                <?php foreach ($options['categorias'] as $category): ?>
                                    <?php
                                    $categoryActive = valorBooleanoPostgres(
                                        $category['activo']
                                    );
                                    ?>

                                    <option value="<?= (int) $category['id_categoria'] ?>"
                                        data-categoria-slug="<?= escape((string) $category['slug']) ?>" <?php if ((string) $values['id_categoria'] === (string) $category['id_categoria']): ?> selected <?php endif; ?>>
                                        <?= escape(
                                            (string) $category['nombre']
                                            . ($categoryActive
                                                ? ''
                                                : ' (inactiva)')
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (isset($errors['id_categoria'])): ?>
                                <span class="admin-field__error" id="categoria-error">
                                    <?= escape(
                                        (string) $errors['id_categoria']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php
                            $storedSubcategoryCode = trim(
                                (string) ($values['subcategoria_codigo'] ?? '')
                            );

                            $storedSubcategoryName = trim(
                                (string) ($values['subcategoria'] ?? '')
                            );

                            $currentSubcategoryCode = $storedSubcategoryCode !== ''
                                ? $storedSubcategoryCode
                                : codigoSubcategoriaProducto($storedSubcategoryName);
                        ?>
                        <div
                            id="subcategoria-field"
                            class="admin-field<?= isset($errors['subcategoria']) ? ' admin-field--invalid' : '' ?>"
                            <?= $selectedCategoryHasSubcategories ? '' : 'hidden inert aria-hidden="true" style="display:none !important"' ?>
                        >
                            <label for="subcategoria">
                                Subcategoría
                                <span class="admin-required">*</span>
                            </label>

                            <select
                                id="subcategoria"
                                name="subcategoria"
                                <?= $selectedCategoryHasSubcategories ? 'required' : 'disabled' ?>
                                <?= isset($errors['subcategoria']) ? 'aria-invalid="true" aria-describedby="subcategoria-error"' : '' ?>
                            >
                                <option value="">Selecciona una subcategoría</option>

                                <?php foreach ($options['subcategorias'] as $subcategory): ?>
                                    <?php
                                    $belongsToSelectedCategory =
                                        (string) $values['id_categoria'] === (string) $subcategory['id_categoria'];

                                    $matchesStoredCode =
                                        $currentSubcategoryCode === (string) $subcategory['slug'];

                                    $matchesStoredName =
                                        $storedSubcategoryName !== ''
                                        && codigoSubcategoriaProducto($storedSubcategoryName)
                                            === codigoSubcategoriaProducto((string) $subcategory['nombre']);

                                    $subcategorySelected =
                                        $belongsToSelectedCategory
                                        && ($matchesStoredCode || $matchesStoredName);
                                    ?>

                                    <option
                                        value="<?= escape((string) $subcategory['slug']) ?>"
                                        data-category-id="<?= (int) $subcategory['id_categoria'] ?>"
                                        <?= $subcategorySelected ? 'selected' : '' ?>
                                    >
                                        <?= escape((string) $subcategory['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (isset($errors['subcategoria'])): ?>
                                <span class="admin-field__error" id="subcategoria-error">
                                    <?= escape((string) $errors['subcategoria']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-field<?= isset($errors['id_marca'])
                            ? ' admin-field--invalid'
                            : '' ?>">
                            <label for="id_marca">
                                Marca
                                <span class="admin-required">*</span>
                            </label>

                            <select id="id_marca" name="id_marca" required <?= isset($errors['id_marca'])
                                ? 'aria-invalid="true" aria-describedby="marca-error"'
                                : '' ?>>
                                <?php foreach ($options['marcas'] as $brand): ?>
                                    <?php
                                    $brandActive = valorBooleanoPostgres(
                                        $brand['activo']
                                    );
                                    ?>

                                    <option value="<?= (int) $brand['id_marca'] ?>" <?php if ((string) $values['id_marca'] === (string) $brand['id_marca']): ?> selected <?php endif; ?>>
                                        <?= escape(
                                            (string) $brand['nombre']
                                            . ($brandActive
                                                ? ''
                                                : ' (inactiva)')
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (isset($errors['id_marca'])): ?>
                                <span class="admin-field__error" id="marca-error">
                                    <?= escape(
                                        (string) $errors['id_marca']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-field<?= isset($errors['tipo_mascota'])
                            ? ' admin-field--invalid'
                            : '' ?>">
                            <label for="tipo_mascota">
                                Tipo de mascota
                                <span class="admin-required">*</span>
                            </label>

                            <select id="tipo_mascota" name="tipo_mascota" required>
                                <?php
                                $petTypes = [
                                    'perro' => 'Perro',
                                    'gato' => 'Gato',
                                    'ambos' => 'Perro y gato',
                                    'otro' => 'Otro',
                                ];
                                ?>

                                <?php foreach ($petTypes as $value => $label): ?>
                                    <option value="<?= escape($value) ?>" <?= $values['tipo_mascota'] === $value
                                          ? 'selected'
                                          : '' ?>>
                                        <?= escape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (isset($errors['tipo_mascota'])): ?>
                                <span class="admin-field__error">
                                    <?= escape(
                                        (string) $errors['tipo_mascota']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div id="precio-venta-field" class="admin-field<?= isset($errors['precio_venta'])
                            ? ' admin-field--invalid'
                            : '' ?>" <?= $fractionable ? 'hidden' : '' ?>>
                            <label for="precio_venta">
                                Precio de venta
                                <span class="admin-required">*</span>
                            </label>

                            <input id="precio_venta" name="precio_venta" type="text" inputmode="numeric"
                                <?= $fractionable ? 'disabled' : 'required' ?> value="<?= escape(
                                           (string) $values['precio_venta']
                                       ) ?>">

                            <?php if (isset($errors['precio_venta'])): ?>
                                <span class="admin-field__error">
                                    <?= escape(
                                        (string) $errors['precio_venta']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div id="fractionable-info" class="admin-field admin-field--full" <?= $fractionable ? '' : 'hidden' ?>>
                            <strong>Producto fraccionable</strong>
                            <span class="admin-field__help">Stock administrado en gramos. El precio de venta se gestiona
                                en las presentaciones.</span>
                        </div>

                        <?php
                        $identifierFields = [
                            'sku' => 'SKU',
                            'codigo_barras' => 'Código de barras',
                        ];
                        ?>

                        <?php foreach ($identifierFields as $field => $label): ?>
                            <div class="admin-field<?= isset($errors[$field])
                                ? ' admin-field--invalid'
                                : '' ?>">
                                <label for="<?= escape($field) ?>">
                                    <?= escape($label) ?>
                                </label>

                                <input id="<?= escape($field) ?>" name="<?= escape($field) ?>" type="text" maxlength="100"
                                    value="<?= escape(
                                        (string) $values[$field]
                                    ) ?>">

                                <?php if (isset($errors[$field])): ?>
                                    <span class="admin-field__error">
                                        <?= escape(
                                            (string) $errors[$field]
                                        ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="admin-field admin-field--full<?= isset($errors['descripcion'])
                            ? ' admin-field--invalid'
                            : '' ?>">
                            <label for="descripcion">
                                Descripción
                            </label>

                            <textarea id="descripcion" name="descripcion" rows="4"><?= escape(
                                (string) $values['descripcion']
                            ) ?></textarea>

                            <?php if (isset($errors['descripcion'])): ?>
                                <span class="admin-field__error">
                                    <?= escape(
                                        (string) $errors['descripcion']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                    </div>
                </section>

                <section class="admin-panel admin-form-panel" aria-labelledby="additional-information-title">
                    <div class="admin-panel__header">
                        <h2 id="additional-information-title">
                            INFORMACIÓN ADICIONAL
                        </h2>

                        <p class="admin-panel__intro">
                            Completa únicamente la información disponible para este producto.
                        </p>
                    </div>

                    <div class="admin-form-grid">

                        <?php
                        $additionalFields = [
                            'formato' => 'Formato',
                            'etapa_vida_tamano' => 'Etapa de vida o tamaño',
                            'pais_origen' => 'País de origen',
                            'fraccionadora_importador' => 'Fraccionadora o importador',
                        ];
                        ?>

                        <?php foreach ($additionalFields as $field => $label): ?>
                            <div <?= $field === 'formato' ? 'data-presentation-field="1"' : '' ?> class="admin-field<?= isset($errors[$field])
                                         ? ' admin-field--invalid'
                                         : '' ?>">
                                <label for="<?= escape($field) ?>">
                                    <?= escape($label) ?>
                                </label>

                                <input id="<?= escape($field) ?>" name="<?= escape($field) ?>" type="text"
                                    maxlength="<?= $field === 'subcategoria' ? '120' : '255' ?>" value="<?= escape(
                                                (string) $values[$field]
                                            ) ?>">

                                <?php if (isset($errors[$field])): ?>
                                    <span class="admin-field__error">
                                        <?= escape(
                                            (string) $errors[$field]
                                        ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div
                            id="energia-metabolizable-field"
                            class="admin-field<?= isset($errors['energia_metabolizable_kcal_kg']) ? ' admin-field--invalid' : '' ?>"
                            <?= $selectedCategoryIsDryFood ? '' : 'hidden style="display:none"' ?>
                        >
                            <label for="energia_metabolizable_kcal_kg">
                                Energía metabolizable
                                <span class="admin-required">*</span>
                            </label>

                            <div class="admin-input-with-unit">
                                <input
                                    id="energia_metabolizable_kcal_kg"
                                    name="energia_metabolizable_kcal_kg"
                                    type="number"
                                    min="0.01"
                                    step="any"
                                    inputmode="decimal"
                                    placeholder="Ej.: 3800"
                                    value="<?= escape((string) $values['energia_metabolizable_kcal_kg']) ?>"
                                    <?= $selectedCategoryIsDryFood ? 'required' : 'disabled' ?>
                                    <?= isset($errors['energia_metabolizable_kcal_kg']) ? 'aria-invalid="true" aria-describedby="energia-metabolizable-error"' : '' ?>
                                >
                                <span>kcal/kg</span>
                            </div>

                            <span class="admin-field__help">
                                Ingresa las kcal por kilogramo indicadas por el fabricante. Este dato se utiliza en la calculadora de alimentación.
                            </span>

                            <?php if (isset($errors['energia_metabolizable_kcal_kg'])): ?>
                                <span class="admin-field__error" id="energia-metabolizable-error">
                                    <?= escape((string) $errors['energia_metabolizable_kcal_kg']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div data-presentation-field="1" class="admin-field<?= isset($errors['peso_contenido'])
                            ? ' admin-field--invalid'
                            : '' ?>">
                            <label for="peso_contenido">
                                Peso o contenido
                            </label>

                            <input id="peso_contenido" name="peso_contenido" type="text" inputmode="decimal" value="<?= escape(
                                (string) $values['peso_contenido']
                            ) ?>">

                            <?php if (isset($errors['peso_contenido'])): ?>
                                <span class="admin-field__error">
                                    <?= escape(
                                        (string) $errors['peso_contenido']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-field<?= isset($errors['unidad'])
                            ? ' admin-field--invalid'
                            : '' ?>">
                            <label for="unidad">
                                Unidad
                            </label>

                            <select id="unidad" name="unidad">
                                <option value="">
                                    Selecciona una unidad
                                </option>

                                <?php
                                $units = [
                                    'g',
                                    'kg',
                                    'ml',
                                    'l',
                                    'unidad',
                                    'pack',
                                    'otro',
                                ];
                                ?>

                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= escape($unit) ?>" <?= $values['unidad'] === $unit
                                          ? 'selected'
                                          : '' ?>>
                                        <?= escape($unit) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (isset($errors['unidad'])): ?>
                                <span class="admin-field__error">
                                    <?= escape(
                                        (string) $errors['unidad']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php
                        $additionalTextareas = [
                            'ingredientes_materiales' => 'Ingredientes o materiales',
                            'analisis_caracteristicas' => 'Análisis o características',
                            'datos_reglamentarios' => 'Datos reglamentarios',
                        ];
                        ?>

                        <?php foreach ($additionalTextareas as $field => $label): ?>
                            <div class="admin-field admin-field--full<?= isset($errors[$field])
                                ? ' admin-field--invalid'
                                : '' ?>">
                                <label for="<?= escape($field) ?>">
                                    <?= escape($label) ?>
                                </label>

                                <textarea id="<?= escape($field) ?>" name="<?= escape($field) ?>" rows="4"><?= escape(
                                        (string) $values[$field]
                                    ) ?></textarea>

                                <?php if (isset($errors[$field])): ?>
                                    <span class="admin-field__error">
                                        <?= escape(
                                            (string) $errors[$field]
                                        ) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </section>

                <section class="admin-panel admin-form-panel" aria-labelledby="inventory-status-title">
                    <div class="admin-panel__header">
                        <h2 id="inventory-status-title">
                            INVENTARIO Y ESTADO
                        </h2>

                        <p class="admin-panel__intro">
                            La cantidad disponible se administra mediante movimientos de inventario para mantener su
                            trazabilidad.
                        </p>
                    </div>

                    <div class="admin-form-grid">

                        <div class="admin-field">
                            <label for="cantidad_actual">Cantidad actual</label>
                            <input id="cantidad_actual" type="text" value="<?= escape(
                                formatearCantidadStock((int) $values['cantidad_actual'], $fractionable)
                            ) ?>" readonly aria-describedby="stock-trace-help">
                            <span class="admin-field__help" id="stock-trace-help">
                                Este valor es informativo y se modifica desde la gestión de stock.
                            </span>
                        </div>

                        <div class="admin-field<?= isset($errors['stock_minimo'])
                            ? ' admin-field--invalid'
                            : '' ?>">
                            <label for="stock_minimo">
                                <?= $fractionable ? 'Stock mínimo en gramos' : 'Stock mínimo en unidades' ?>
                            </label>

                            <input id="stock_minimo" name="stock_minimo" type="number" min="0" step="1" value="<?= escape(
                                (string) $values['stock_minimo']
                            ) ?>" aria-describedby="stock-minimo-help">

                            <span class="admin-field__help" id="stock-minimo-help">
                                Define desde qué cantidad el producto será considerado con stock bajo.
                            </span>

                            <?php if (isset($errors['stock_minimo'])): ?>
                                <span class="admin-field__error">
                                    <?= escape(
                                        (string) $errors['stock_minimo']
                                    ) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="admin-status-control admin-field--full">
                            <div class="admin-status-control__copy">
                                <strong>
                                    Producto activo
                                </strong>

                                <span>
                                    Los productos inactivos permanecen registrados, pero no deben ofrecerse para la
                                    venta.
                                </span>
                            </div>

                            <label class="admin-switch" for="activo">
                                <input id="activo" name="activo" type="checkbox" value="1" <?= $values['activo']
                                    ? 'checked'
                                    : '' ?>>

                                <span class="admin-switch__track" aria-hidden="true"></span>

                                <span class="admin-switch__label">
                                    <?= $values['activo']
                                        ? 'Activo'
                                        : 'Inactivo' ?>
                                </span>
                            </label>
                        </div>

                    </div>
                    <div class="admin-form-actions admin-form-actions--inside">
                        <a class="admin-button" href="<?= escape(
                            appUrl(
                                'admin/inventario/index.php'
                            )
                        ) ?>">
                            Cancelar
                        </a>

                        <button class="admin-button admin-button--primary" type="submit">
                            Actualizar producto
                        </button>
                    </div>

                </section>

            </form>



        </div>
    </div>

    <script>
        (() => {
            const form = document.querySelector('.admin-product-images__upload');
            const input = document.getElementById('imagen-producto');
            const help = document.getElementById('imagenes-producto-ayuda');

            if (!(form instanceof HTMLFormElement)
                || !(input instanceof HTMLInputElement)
                || !(help instanceof HTMLElement)
            ) {
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            const maxFiles = Number.parseInt(input.dataset.maxFiles ?? '0', 10);

            input.addEventListener('change', () => {
                const selected = input.files?.length ?? 0;

                if (selected > maxFiles) {
                    input.value = '';
                    help.textContent = `Solo puedes seleccionar ${maxFiles} imagen(es) porque el producto admite un máximo de 5.`;
                    input.setCustomValidity('Seleccionaste más imágenes de las permitidas.');
                    input.reportValidity();
                    return;
                }

                input.setCustomValidity('');
                help.textContent = selected > 0
                    ? `${selected} imagen(es) seleccionada(s). Máximo 2 MB por archivo.`
                    : `Puedes seleccionar hasta ${maxFiles} imagen(es). JPG, PNG o WEBP; máximo 2 MB por archivo.`;
            });

            form.addEventListener('submit', (event) => {
                const selected = input.files?.length ?? 0;

                if (selected < 1 || selected > maxFiles) {
                    event.preventDefault();
                    input.setCustomValidity(
                        selected < 1
                            ? 'Selecciona al menos una imagen.'
                            : `Selecciona como máximo ${maxFiles} imagen(es).`
                    );
                    input.reportValidity();
                    return;
                }

                input.setCustomValidity('');

                if (submitButton instanceof HTMLButtonElement) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Subiendo imágenes…';
                }
            });
        })();
    </script>

    <script>
        (() => {
            const category = document.getElementById('id_categoria');
            const subcategory = document.getElementById('subcategoria');
            const subcategoryField = document.getElementById('subcategoria-field');
            const priceField = document.getElementById('precio-venta-field');
            const price = document.getElementById('precio_venta');
            const fractionableInfo = document.getElementById('fractionable-info');
            const energyField = document.getElementById('energia-metabolizable-field');
            const energy = document.getElementById('energia_metabolizable_kcal_kg');
            if (!category || !subcategory || !subcategoryField || !priceField || !price || !fractionableInfo || !energyField || !energy) return;

            let initialized = false;

            const updateProductType = () => {
                const selectedCategory = category.selectedOptions[0];
                const foods = selectedCategory?.dataset.categoriaSlug === 'alimentos';
                const categoryId = category.value;
                const hasSubcategories = [...subcategory.options].some(
                    (option) => option.value !== '' && option.dataset.categoryId === categoryId
                );

                subcategoryField.hidden = !hasSubcategories;
                subcategoryField.style.setProperty(
                    'display',
                    hasSubcategories ? '' : 'none',
                    hasSubcategories ? '' : 'important'
                );
                subcategoryField.toggleAttribute('inert', !hasSubcategories);
                subcategoryField.setAttribute('aria-hidden', hasSubcategories ? 'false' : 'true');

                subcategory.disabled = !hasSubcategories;
                subcategory.required = hasSubcategories;

                for (const option of subcategory.options) {
                    if (option.value === '') {
                        option.hidden = false;
                        option.disabled = false;
                        continue;
                    }
                    const belongsToCategory = option.dataset.categoryId === categoryId;
                    option.hidden = hasSubcategories ? !belongsToCategory : true;
                    option.disabled = hasSubcategories ? !belongsToCategory : true;
                }

                if (!hasSubcategories) {
                    subcategory.value = '';
                } else if (
                    subcategory.value !== ''
                    && subcategory.selectedOptions[0]?.dataset.categoryId !== categoryId
                ) {
                    subcategory.value = '';
                }

                const code = subcategory.value
                    .trim()
                    .toLocaleLowerCase('es')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '');

                const dryFood = foods && code === 'alimento-seco';

                energyField.hidden = !dryFood;
                energyField.style.display = dryFood ? '' : 'none';
                energy.disabled = !dryFood;
                energy.required = dryFood;

                if (initialized && !dryFood) {
                    energy.value = '';
                }
                const fractionable = dryFood;

                priceField.hidden = fractionable;
                price.disabled = fractionable;
                price.required = !fractionable;
                fractionableInfo.hidden = !fractionable;

                document
                    .querySelectorAll('[data-presentation-field="1"]')
                    .forEach((field) => {
                        field.hidden = fractionable;
                    });

                initialized = true;
            };
            category.addEventListener('change', updateProductType);
            subcategory.addEventListener('change', updateProductType);
            updateProductType();
        })();
    </script>
    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
