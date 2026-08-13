<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-lotes.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/imagenes/includes/funciones-imagenes-producto.php';
require_once __DIR__ . '/includes/funciones-producto.php';
require_once dirname(__DIR__, 2) . '/proveedores/includes/consultas-proveedores.php';

requireAuthentication();

$state = consumirEstadoFormularioProducto();
$values = array_merge(valoresInicialesProducto(), $state['valores'] ?? []);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';
if ($errors !== [] || $generalError !== null) {
    $imageError = (
        isset($errors['imagenes_producto'])
        || isset($errors['imagen_principal'])
    ) && count($errors) === 1;
    $adminModal = [
        'type' => 'error',
        'title' => $imageError ? 'No fue posible subir la imagen' : 'No fue posible guardar el producto',
        'message' => $imageError ? 'Revisa el archivo seleccionado antes de continuar.' : ($errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.'),
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
}
$options = ['categorias' => [], 'marcas' => [], 'subcategorias' => []];
$suppliers = [];
$optionsError = false;

try {
    $options = obtenerOpcionesProducto(database());
    $suppliers = todosProveedoresActivos(database());
} catch (Throwable $exception) {
    $optionsError = true;
    $reference = registrarExcepcionAdmin('Product form options error', $exception);
    if (!isset($adminModal)) {
        $adminModal = [
            'type' => 'error',
            'title' => 'No fue posible preparar el formulario',
            'message' => 'No se pudieron cargar las categorías y marcas disponibles.',
            'reference' => $reference,
            'primaryText' => 'Aceptar',
        ];
    }
}

$canSubmit = !$optionsError && $options['categorias'] !== [] && $options['marcas'] !== [];

$selectedCategoryHasSubcategories = array_filter(
    $options['subcategorias'],
    static fn (array $subcategory): bool =>
        (string) ($subcategory['id_categoria'] ?? '') === (string) ($values['id_categoria'] ?? '')
) !== [];
$selectedCategoryIsFoods = array_filter(
    $options['categorias'],
    static fn (array $category): bool =>
        (string) ($category['id_categoria'] ?? '') === (string) ($values['id_categoria'] ?? '')
        && (string) ($category['slug'] ?? '') === CATEGORIA_ALIMENTOS_SLUG
) !== [];

$csrfToken = csrfToken();
$pageTitle = 'Agregar producto';
$activeSection = 'inventario';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>
<main class="admin-main" id="contenido-principal">
    <header class="admin-page-header">
        <div>
            <a class="admin-back-link" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">← Volver al
                inventario</a>
            <h1 class="admin-page-title admin-page-title--paw">Agregar producto</h1>
            <p>Registra la información base del producto y su stock inicial.</p>
        </div>
    </header>

    <?php if (!$canSubmit): ?>
        <div class="admin-alert admin-alert--warning" role="status">
            <strong>No es posible registrar productos todavía.</strong>
            <p>Debe existir al menos una categoría activa y una marca activa.</p>
        </div>
    <?php endif; ?>

    <div class="admin-form-layout admin-product-edit-shell admin-product-create-shell">
    <form class="admin-product-form admin-product-create-layout" method="post" enctype="multipart/form-data"
        action="<?= escape(appUrl('admin/inventario/productos/guardar.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

        <aside class="admin-product-edit-media admin-product-create-media">
            <section class="admin-panel admin-product-media-panel admin-product-create-media-card" aria-labelledby="create-catalog-preview-title">
                <div class="admin-panel__header">
                    <h2 id="create-catalog-preview-title">Vista previa de catálogo</h2>
                    <p class="admin-panel__intro">Completa los datos para visualizar la nueva ficha de producto.</p>
                </div>
                <div class="admin-product-media-hero">
                    <div class="admin-product-media-hero__image admin-product-edit-main-image admin-product-create-preview">
                        <span class="admin-product-media-hero__badge">Imagen principal</span>
                        <div class="admin-product-media-hero__placeholder" data-image-preview-placeholder>
                            <span aria-hidden="true">🐾</span>
                            <strong>Selecciona una imagen para previsualizar el producto</strong>
                        </div>
                        <img id="create-product-image-preview" alt="Vista previa de la imagen seleccionada" hidden>
                    </div>
                    <div class="admin-product-media-info">
                        <span class="admin-product-media-info__eyebrow">Nueva ficha</span>
                        <h3 class="admin-product-media-info__title" data-create-preview-name><?= escape($values['nombre'] !== '' ? $values['nombre'] : 'Nuevo producto') ?></h3>
                        <dl class="admin-product-media-info__meta admin-product-create-summary">
                            <div><dt>SKU</dt><dd data-create-preview-sku><?= escape($values['sku'] !== '' ? $values['sku'] : 'Sin SKU') ?></dd></div>
                            <div><dt>Categoría</dt><dd data-create-preview-category>Categoría pendiente</dd></div>
                            <div><dt>Marca</dt><dd data-create-preview-brand>Marca pendiente</dd></div>
                            <div><dt>Tipo</dt><dd data-create-preview-type>Producto por unidad</dd></div>
                            <div><dt>Mascota</dt><dd data-create-preview-pet>Por definir</dd></div>
                            <div><dt>Estado</dt><dd><span class="admin-status-badge is-active">Activo</span></dd></div>
                            <div><dt>Stock inicial</dt><dd data-create-preview-stock><?= escape($values['stock_inicial'] !== '' ? $values['stock_inicial'] : '0 unidades') ?></dd></div>
                            <div><dt>Precio</dt><dd data-create-preview-price><?= escape($values['precio_venta'] !== '' ? '$' . $values['precio_venta'] : 'Por definir') ?></dd></div>
                        </dl>
                    </div>
                </div>
                <div class="admin-product-create-upload">
                    <h3>Cargar imágenes del producto</h3>
                    <div class="admin-field<?= isset($errors['imagenes_producto']) || isset($errors['imagen_principal']) ? ' admin-field--invalid' : '' ?>">
                        <label for="imagenes_producto">Archivos de imagen</label>
                        <input
                            id="imagenes_producto"
                            name="imagenes_producto[]"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.heic,.heif,image/jpeg,image/png,image/webp,image/heic,image/heif"
                            multiple
                            data-image-preview-input
                            data-preview-target="create-product-image-preview"
                            data-max-files="<?= IMAGEN_PRODUCTO_MAX_CANTIDAD ?>"
                            <?= isset($errors['imagenes_producto']) || isset($errors['imagen_principal'])
                                ? 'aria-invalid="true" aria-describedby="imagenes-producto-error imagenes-producto-help"'
                                : 'aria-describedby="imagenes-producto-help"' ?>
                        >
                        <span class="admin-field__help" id="imagenes-producto-help">
                            Puedes seleccionar hasta 10 imágenes. JPG, PNG o WEBP (máximo 2 MB), o HEIC/HEIF (máximo 20 MB; se convertirá a WEBP al guardar). La primera quedará como principal.
                        </span>
                        <?php if (isset($errors['imagenes_producto']) || isset($errors['imagen_principal'])): ?>
                            <span class="admin-field__error" id="imagenes-producto-error">
                                <?= escape((string) ($errors['imagenes_producto'] ?? $errors['imagen_principal'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-field">
                        <label for="imagen_alt_text">Texto alternativo</label>
                        <input id="imagen_alt_text" name="imagen_alt_text" type="text" maxlength="180"
                            value="<?= escape((string) ($values['imagen_alt_text'] ?? '')) ?>"
                            placeholder="Describe brevemente las imágenes">
                        <span class="admin-field__help">Opcional. Se aplicará a las imágenes seleccionadas.</span>
                    </div>
                    <p>Las imágenes se subirán junto con el producto. La primera se marcará como principal.</p>
                </div>
            </section>
        </aside>

        <div class="admin-product-edit-form admin-product-create-form">
        <section class="admin-panel admin-product-create-form__main" aria-labelledby="main-information-title">
            <div class="admin-panel__header">
                <h2 id="main-information-title">Información principal</h2>
                <p class="admin-panel__intro">Los campos marcados con <span class="admin-required">*</span> son
                    obligatorios.</p>
            </div>

            <div class="admin-form-grid">
                <div
                    class="admin-field admin-field--full<?= isset($errors['nombre']) ? ' admin-field--invalid' : '' ?>">
                    <label for="nombre">Nombre del producto <span class="admin-required">*</span></label>
                    <input id="nombre" name="nombre" type="text" maxlength="180" required
                        placeholder="Ej.: Acana Adult Dog Recipe" value="<?= escape($values['nombre']) ?>"
                        <?= isset($errors['nombre']) ? 'aria-invalid="true" aria-describedby="nombre-error"' : '' ?>>
                    <?php if (isset($errors['nombre'])): ?><span class="admin-field__error"
                            id="nombre-error"><?= escape($errors['nombre']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['sku']) ? ' admin-field--invalid' : '' ?>">
                    <label for="sku">SKU</label>
                    <input id="sku" name="sku" type="text" maxlength="100" placeholder="Ej.: ACA-ADULT-10KG"
                        value="<?= escape($values['sku']) ?>" <?= isset($errors['sku']) ? 'aria-invalid="true" aria-describedby="sku-error"' : '' ?>>
                    <?php if (isset($errors['sku'])): ?><span class="admin-field__error"
                            id="sku-error"><?= escape($errors['sku']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['codigo_barras']) ? ' admin-field--invalid' : '' ?>">
                    <label for="codigo_barras">Código de barras</label>
                    <input id="codigo_barras" name="codigo_barras" type="text" maxlength="100"
                        placeholder="Ej.: 064992523109" value="<?= escape($values['codigo_barras']) ?>"
                        <?= isset($errors['codigo_barras']) ? 'aria-invalid="true" aria-describedby="codigo-barras-error"' : '' ?>>
                    <?php if (isset($errors['codigo_barras'])): ?><span class="admin-field__error"
                            id="codigo-barras-error"><?= escape($errors['codigo_barras']) ?></span><?php endif; ?>
                </div>

                <div
                    class="admin-field admin-field--select-compact<?= isset($errors['id_categoria']) ? ' admin-field--invalid' : '' ?>">
                    <label for="id_categoria">Categoría <span class="admin-required">*</span></label>
                    <select id="id_categoria" name="id_categoria" required <?= isset($errors['id_categoria']) ? 'aria-invalid="true" aria-describedby="categoria-error"' : '' ?>>
                        <option value="">Selecciona una categoría</option>
                        <?php foreach ($options['categorias'] as $category): ?>
                            <option value="<?= escape((string) $category['id_categoria']) ?>"
                                data-categoria-slug="<?= escape((string) $category['slug']) ?>"
                                <?= (string) $values['id_categoria'] === (string) $category['id_categoria'] ? 'selected' : '' ?>><?= escape((string) $category['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['id_categoria'])): ?><span class="admin-field__error"
                            id="categoria-error"><?= escape($errors['id_categoria']) ?></span><?php endif; ?>
                </div>

                <div id="subcategoria-field"
                    class="admin-field admin-field--select-compact<?= isset($errors['subcategoria']) ? ' admin-field--invalid' : '' ?>"
                    <?= $selectedCategoryHasSubcategories ? '' : 'hidden style="display:none"' ?>>
                    <label for="subcategoria">Subcategoría <span class="admin-required">*</span></label>
                    <select id="subcategoria" name="subcategoria"
                        <?= $selectedCategoryHasSubcategories ? 'required' : 'disabled' ?>
                        <?= isset($errors['subcategoria']) ? 'aria-invalid="true" aria-describedby="subcategoria-error"' : '' ?>>
                        <option value="">Selecciona una subcategoría</option>
                        <?php foreach ($options['subcategorias'] as $subcategory): ?>
                            <option value="<?= escape((string) $subcategory['slug']) ?>"
                                data-category-id="<?= (int) $subcategory['id_categoria'] ?>"
                                <?= (string) $values['id_categoria'] === (string) $subcategory['id_categoria']
                                    && codigoSubcategoriaProducto($values['subcategoria']) === codigoSubcategoriaProducto((string) $subcategory['slug'])
                                        ? 'selected'
                                        : '' ?>>
                                <?= escape((string) $subcategory['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['subcategoria'])): ?><span class="admin-field__error"
                            id="subcategoria-error"><?= escape($errors['subcategoria']) ?></span><?php endif; ?>
                </div>

                <div
                    class="admin-field admin-field--select-compact<?= isset($errors['id_marca']) ? ' admin-field--invalid' : '' ?>">
                    <label for="id_marca">Marca <span class="admin-required">*</span></label>
                    <select id="id_marca" name="id_marca" required <?= isset($errors['id_marca']) ? 'aria-invalid="true" aria-describedby="marca-error"' : '' ?>>
                        <option value="">Selecciona una marca</option>
                        <?php foreach ($options['marcas'] as $brand): ?>
                            <option value="<?= escape((string) $brand['id_marca']) ?>" <?= (string) $values['id_marca'] === (string) $brand['id_marca'] ? 'selected' : '' ?>>
                                <?= escape((string) $brand['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['id_marca'])): ?><span class="admin-field__error"
                            id="marca-error"><?= escape($errors['id_marca']) ?></span><?php endif; ?>
                </div>

                <div
                    class="admin-field admin-field--full admin-pet-selector<?= isset($errors['tipo_mascota']) ? ' admin-field--invalid' : '' ?>">
                    <span class="admin-pet-selector__label" id="tipo-mascota-label">
                        Tipo de mascota <span class="admin-required">*</span>
                    </span>

                    <div class="admin-pet-options" role="radiogroup" aria-labelledby="tipo-mascota-label">
                        <?php foreach (['perro' => 'Perro', 'gato' => 'Gato', 'ambos' => 'Perro y gato', 'otro' => 'Otro'] as $value => $label): ?>
                            <label class="admin-pet-option">
                                <input type="radio" name="tipo_mascota" value="<?= $value ?>" required
                                    <?= $values['tipo_mascota'] === $value ? 'checked' : '' ?>
                                    <?= isset($errors['tipo_mascota']) ? 'aria-invalid="true" aria-describedby="mascota-error"' : '' ?>>
                                <span><?= escape($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if (isset($errors['tipo_mascota'])): ?>
                        <span class="admin-field__error" id="mascota-error"><?= escape($errors['tipo_mascota']) ?></span>
                    <?php endif; ?>
                </div>

            </div>
        </section>

        <section class="admin-panel admin-product-create-form__sales" aria-labelledby="sales-stock-title">
            <div class="admin-panel__header">
                <h2 id="sales-stock-title">Venta e inventario</h2>
                <p class="admin-panel__intro" id="sales-stock-help">Configura el precio y las cantidades disponibles del
                    producto.</p>
            </div>
            <div id="fractionable-info" class="admin-alert admin-alert--fractionable" hidden>
                <strong>Producto fraccionable</strong>
                <p>Indica cuántos kilos trae el saco. El sistema guardará el stock internamente en gramos y el precio se
                    configurará después en Presentaciones.</p>
            </div>
            <div class="admin-form-grid">
                <div id="precio-venta-field"
                    class="admin-field<?= isset($errors['precio_venta']) ? ' admin-field--invalid' : '' ?>">
                    <label for="precio_venta">Precio de venta <span class="admin-required">*</span></label>
                    <input id="precio_venta" name="precio_venta" type="text" inputmode="numeric" required
                        placeholder="Ej.: 24990" value="<?= escape($values['precio_venta']) ?>"
                        <?= isset($errors['precio_venta']) ? 'aria-invalid="true" aria-describedby="precio-error precio-help"' : 'aria-describedby="precio-help"' ?>>
                    <span class="admin-field__help" id="precio-help">Ingresa el valor en pesos, sin decimales.</span>
                    <?php if (isset($errors['precio_venta'])): ?><span class="admin-field__error"
                            id="precio-error"><?= escape($errors['precio_venta']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['stock_inicial']) ? ' admin-field--invalid' : '' ?>">
                    <label id="stock-inicial-label" for="stock_inicial">Stock inicial en unidades <span
                            class="admin-required">*</span></label>
                    <input id="stock_inicial" name="stock_inicial" type="number" min="0" step="1" required
                        value="<?= escape($values['stock_inicial']) ?>" <?= isset($errors['stock_inicial']) ? 'aria-invalid="true" aria-describedby="stock-inicial-help stock-error"' : 'aria-describedby="stock-inicial-help"' ?>>
                    <span class="admin-field__help" id="stock-inicial-help">Ingresa la cantidad de unidades
                        disponibles.</span>
                    <?php if (isset($errors['stock_inicial'])): ?><span class="admin-field__error"
                            id="stock-error"><?= escape($errors['stock_inicial']) ?></span><?php endif; ?>
                </div>
                <div id="stock-minimo-field"
                    class="admin-field<?= isset($errors['stock_minimo']) ? ' admin-field--invalid' : '' ?>">
                    <label id="stock-minimo-label" for="stock_minimo">Stock mínimo en unidades</label>
                    <input id="stock_minimo" name="stock_minimo" type="number" min="0" step="1" placeholder="Ej.: 5"
                        value="<?= escape($values['stock_minimo']) ?>" <?= isset($errors['stock_minimo']) ? 'aria-invalid="true" aria-describedby="stock-minimo-error"' : '' ?>>
                    <?php if (isset($errors['stock_minimo'])): ?><span class="admin-field__error"
                            id="stock-minimo-error"><?= escape($errors['stock_minimo']) ?></span><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-panel admin-product-create-form__optional" aria-labelledby="optional-information-title">
            <div class="admin-panel__header">
                <h2 id="optional-information-title">Datos opcionales</h2>
                <p class="admin-panel__intro">Completa únicamente la información disponible para este producto.</p>
            </div>

            <div class="admin-form-grid">
                <?php
                $basicOptionalFields = [
                    'formato' => ['label' => 'Formato', 'placeholder' => 'Ej.: Saco, bolsa o lata'],
                ];
                foreach ($basicOptionalFields as $field => $fieldData):
                    ?>
                    <div <?= $field === 'formato' ? 'id="formato-field" data-presentation-field="1"' : '' ?>
                        class="admin-field<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>">
                        <label for="<?= $field ?>"><?= escape($fieldData['label']) ?></label>
                        <?php
                        $fieldAria = isset($errors[$field])
                            ? 'aria-invalid="true" aria-describedby="' . $field . '-error"'
                            : '';
                        ?>

                        <input id="<?= escape($field) ?>" name="<?= escape($field) ?>" type="text" maxlength="120"
                            placeholder="<?= escape($fieldData['placeholder']) ?>" value="<?= escape($values[$field]) ?>"
                            <?= $fieldAria ?>>
                        <?php if (isset($errors[$field])): ?>
                            <span class="admin-field__error" id="<?= $field ?>-error"><?= escape($errors[$field]) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div id="peso-contenido-field" data-presentation-field="1"
                    class="admin-field<?= isset($errors['peso_contenido']) ? ' admin-field--invalid' : '' ?>">
                    <label for="peso_contenido">Peso o contenido</label>
                    <input id="peso_contenido" name="peso_contenido" type="text" inputmode="decimal"
                        placeholder="Ej.: 10" value="<?= escape($values['peso_contenido']) ?>"
                        <?= isset($errors['peso_contenido']) ? 'aria-invalid="true" aria-describedby="peso-error"' : '' ?>>
                    <?php if (isset($errors['peso_contenido'])): ?><span class="admin-field__error"
                            id="peso-error"><?= escape($errors['peso_contenido']) ?></span><?php endif; ?>
                </div>

                <div id="unidad-field" data-hide-for-dry-food="1"
                    class="admin-field admin-field--select-inline<?= isset($errors['unidad']) ? ' admin-field--invalid' : '' ?>">
                    <label for="unidad">Unidad</label>
                    <select id="unidad" name="unidad" <?= isset($errors['unidad']) ? 'aria-invalid="true" aria-describedby="unidad-error"' : '' ?>>
                        <option value="">Selecciona una unidad</option>
                        <?php foreach (['g', 'kg', 'ml', 'l', 'unidad', 'pack', 'otro'] as $unit): ?>
                            <option value="<?= $unit ?>" <?= $values['unidad'] === $unit ? 'selected' : '' ?>>
                                <?= escape($unit) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['unidad'])): ?><span class="admin-field__error"
                            id="unidad-error"><?= escape($errors['unidad']) ?></span><?php endif; ?>
                </div>

                <div id="energia-metabolizable-field"
                    class="admin-field<?= isset($errors['energia_metabolizable_kcal_kg']) ? ' admin-field--invalid' : '' ?>"
                    <?= $selectedCategoryIsFoods ? '' : 'hidden style="display:none"' ?>>
                    <label for="energia_metabolizable_kcal_kg">Energía metabolizable<span class="admin-required">*</label>
                    <div class="admin-input-with-unit">
                        <input
                            id="energia_metabolizable_kcal_kg"
                            name="energia_metabolizable_kcal_kg"
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="Ej.: 3800"
                            value="<?= escape((string) $values['energia_metabolizable_kcal_kg']) ?>"
                        >
                        <span class="admin-field__help">
                            Ingresa las kcal por kilogramo indicadas por el fabricante. Este dato se utiliza en la calculadora de alimentación.
                        </span>
                    </div>
                    <?php if (isset($errors['energia_metabolizable_kcal_kg'])): ?>
                        <span class="admin-field__error" id="energia-metabolizable-error"><?= escape($errors['energia_metabolizable_kcal_kg']) ?></span>
                    <?php endif; ?>
                </div>

                <?php
                $shortOptionalFields = [
                    'etapa_vida_tamano' => ['label' => 'Etapa de vida o tamaño', 'placeholder' => 'Ej.: Adulto, razas medianas y grandes'],
                    'pais_origen' => ['label' => 'País de origen', 'placeholder' => 'Ej.: Canadá'],
                    'fraccionadora_importador' => ['label' => 'Fraccionadora o importador', 'placeholder' => 'Ej.: Nombre o razón social de la empresa'],
                ];
                foreach ($shortOptionalFields as $field => $fieldData):
                    ?>
                    <div <?= $field === 'fraccionadora_importador' ? 'id="fraccionadora-importador-field" data-hide-for-dry-food="1"' : '' ?> class="admin-field<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>">
                        <label for="<?= $field ?>"><?= escape($fieldData['label']) ?></label>
                        <input id="<?= $field ?>" name="<?= $field ?>" type="text"
                            placeholder="<?= escape($fieldData['placeholder']) ?>" value="<?= escape($values[$field]) ?>"
                            <?= isset($errors[$field]) ? 'aria-invalid="true" aria-describedby="' . $field . '-error"' : '' ?>>
                        <?php if (isset($errors[$field])): ?><span class="admin-field__error"
                                id="<?= $field ?>-error"><?= escape($errors[$field]) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php
                $longOptionalFields = [
                    'descripcion' => ['label' => 'Descripción', 'placeholder' => 'Ej.: Alimento completo para perros adultos, elaborado con ingredientes de origen animal.'],
                    'ingredientes_materiales' => ['label' => 'Ingredientes o materiales', 'placeholder' => 'Ej.: Pollo fresco, pavo, lentejas rojas, grasa de pollo y fibra de arveja.'],
                    'analisis_caracteristicas' => ['label' => 'Análisis o características', 'placeholder' => 'Ej.: Proteína 29%, grasa 17%, fibra 5% y humedad 12%.'],
                    'datos_reglamentarios' => ['label' => 'Datos reglamentarios', 'placeholder' => 'Ej.: Registro SAG, resolución sanitaria, advertencias o condiciones de conservación indicadas en el envase.'],
                ];
                foreach ($longOptionalFields as $field => $fieldData):
                    ?>
                    <div class="admin-field admin-field--full<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>">
                        <label for="<?= $field ?>"><?= escape($fieldData['label']) ?></label>
                        <textarea id="<?= $field ?>" name="<?= $field ?>" rows="4"
                            placeholder="<?= escape($fieldData['placeholder']) ?>" <?= isset($errors[$field]) ? 'aria-invalid="true" aria-describedby="' . $field . '-error"' : '' ?>><?= escape($values[$field]) ?></textarea>
                        <?php if (isset($errors[$field])): ?><span class="admin-field__error"
                                id="<?= $field ?>-error"><?= escape($errors[$field]) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="lotes-section" class="admin-panel admin-lots-panel" hidden aria-labelledby="lotes-title">
            <div class="admin-lots-panel__header"><span class="admin-lots-panel__icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span><div><span class="admin-lots-panel__badge">Solo alimento seco</span><h2 id="lotes-title">Lotes de stock</h2><p>Registra el peso real y la trazabilidad del stock inicial.</p></div></div>
            <div class="admin-lots-toolbar">
                <div class="admin-field admin-lots-provider<?= isset($errors['id_proveedor'])?' admin-field--invalid':'' ?>"><label for="id_proveedor"><i class="bi bi-truck" aria-hidden="true"></i> Proveedor</label><select id="id_proveedor" name="id_proveedor"><option value="">Asignar después</option><?php foreach($suppliers as $supplier):?><option value="<?= (int)$supplier['id_proveedor'] ?>" <?= (string)($values['id_proveedor']??'')===(string)$supplier['id_proveedor']?'selected':'' ?>><?= escape((string)$supplier['nombre'].($supplier['rut']?' · '.$supplier['rut']:'')) ?></option><?php endforeach;?></select><?php if(isset($errors['id_proveedor'])):?><span class="admin-field__error"><?= escape((string)$errors['id_proveedor']) ?></span><?php elseif($suppliers===[]):?><span class="admin-field__help"><i class="bi bi-info-circle" aria-hidden="true"></i> Aún no hay proveedores registrados.</span><?php else:?><span class="admin-field__help">Opcional. Puedes asignarlo ahora o dejarlo para después.</span><?php endif;?></div>
            <?php if (isset($errors['lotes'])): ?><p class="admin-field__error"><?= escape((string) $errors['lotes']) ?></p><?php endif; ?>
            <div class="admin-field admin-lots-count">
                <label for="cantidad_lotes">Cantidad de lotes <span class="admin-required">*</span></label>
                <input id="cantidad_lotes" type="number" min="1" step="1" value="<?= max(1, count((array) ($values['lotes'] ?? []))) ?>">
            </div></div>
            <div id="lotes-container" class="admin-lots-list"></div>
            <p class="admin-lots-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Todo el peso disponible del lote podrá venderse mediante cualquiera de las presentaciones compatibles.</span></p>
        </section>

        <template id="lote-template"><fieldset class="admin-lot-card" data-lote>
            <legend><span class="admin-lot-card__number" data-lote-numero></span><span><strong>Lote</strong><small>Identificación, fechas y peso recibido</small></span></legend>
            <div class="admin-lot-fields">
                <div class="admin-field admin-lot-code"><label>Código de lote <span class="admin-required">*</span></label><input data-lote-campo="codigo_lote" maxlength="80" required placeholder="Ej.: LT-2026-001"></div>
                <div class="admin-field"><label>Fecha elaboración</label><input data-lote-campo="fecha_elaboracion" type="date"></div>
                <div class="admin-field"><label>Fecha vencimiento <span class="admin-required">*</span></label><input data-lote-campo="fecha_vencimiento" type="date" required></div>
                <div class="admin-field admin-lot-quantity"><label>Cantidad total <span class="admin-required">*</span></label><div class="admin-input-suffix"><input data-lote-campo="cantidad_total_g" type="number" min="0.001" step="0.001" required placeholder="0"><span>g</span></div></div>
            </div>
        </fieldset></template>

        <section class="admin-panel admin-form-actions admin-product-create-form__actions" aria-label="Acciones del formulario">
            <a class="admin-button" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">Cancelar</a>
            <button class="admin-button admin-button--primary" type="submit" <?= $canSubmit ? '' : 'disabled' ?>>Guardar
                producto</button>
        </section>
        </div>
    </form>
    </div>
    <script>
        (() => {
            const productForm = document.querySelector('.admin-product-create-layout');
            const imageInput = document.getElementById('imagenes_producto');
            const imageHelp = document.getElementById('imagenes-producto-help');
            const imagePreview = document.getElementById('create-product-image-preview');
            const imagePlaceholder = document.querySelector('[data-image-preview-placeholder]');
            const submitButton = productForm?.querySelector('button[type="submit"]');

            const category = document.getElementById('id_categoria');
            const priceField = document.getElementById('precio-venta-field');
            const price = document.getElementById('precio_venta');
            const initialLabel = document.getElementById('stock-inicial-label');
            const initialHelp = document.getElementById('stock-inicial-help');
            const initialStock = document.getElementById('stock_inicial');
            const minimumField = document.getElementById('stock-minimo-field');
            const minimumStock = document.getElementById('stock_minimo');
            const panelTitle = document.getElementById('sales-stock-title');
            const panelHelp = document.getElementById('sales-stock-help');
            const optionalTitle = document.getElementById('optional-information-title');
            const fractionableInfo = document.getElementById('fractionable-info');
            const subcategory = document.getElementById('subcategoria');
            const subcategoryField = document.getElementById('subcategoria-field');
            const energyField = document.getElementById('energia-metabolizable-field');
            const energy = document.getElementById('energia_metabolizable_kcal_kg');
            const lotesSection = document.getElementById('lotes-section');
            const lotesContainer = document.getElementById('lotes-container');
            const cantidadLotes = document.getElementById('cantidad_lotes');
            const loteTemplate = document.getElementById('lote-template');
            const lotesPrevios = <?= json_encode(array_values((array) ($values['lotes'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

            const presentationFields = document.querySelectorAll('[data-presentation-field="1"]');
            const dryFoodHiddenFields = document.querySelectorAll('[data-hide-for-dry-food="1"]');

            if (
                !category ||
                !priceField ||
                !price ||
                !initialLabel ||
                !initialHelp ||
                !initialStock ||
                !minimumField ||
                !minimumStock ||
                !panelTitle ||
                !panelHelp ||
                !optionalTitle ||
                !fractionableInfo || !subcategory || !subcategoryField || !energyField || !energy
            ) {
                return;
            }

            const updateFields = () => {
                const categoryId = category.value;
                const foods = category.selectedOptions[0]?.dataset.categoriaSlug === 'alimentos';
                const hasSubcategories = [...subcategory.options].some(
                    (option) => option.value !== '' && option.dataset.categoryId === categoryId
                );

                energyField.hidden = !foods;
                energyField.style.display = foods ? '' : 'none';
                energy.disabled = !foods;
                energy.required = foods;
                if (!foods) energy.value = '';

                subcategoryField.hidden = !hasSubcategories;
                subcategoryField.style.display = hasSubcategories ? '' : 'none';
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
                } else {
                    const selectedOption = subcategory.selectedOptions[0];
                    if (
                        selectedOption
                        && selectedOption.value !== ''
                        && selectedOption.dataset.categoryId !== categoryId
                    ) {
                        subcategory.value = '';
                    }
                }
                const subcategoryCode = subcategory.value.trim().toLocaleLowerCase('es')
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                const fractionable = foods && subcategoryCode === 'alimento-seco';
                lotesSection.hidden = !fractionable;
                cantidadLotes.disabled = !fractionable;
                for (const input of lotesContainer.querySelectorAll('input')) input.disabled = !fractionable;

                priceField.hidden = fractionable;
                price.required = !fractionable;
                price.disabled = fractionable;

                minimumField.hidden = fractionable;
                minimumStock.disabled = fractionable;

                if (fractionable) {
                    minimumStock.value = '';
                }

                initialStock.closest('.admin-field').hidden = fractionable;
                initialStock.disabled = fractionable;

                for (const field of presentationFields) {
                    field.hidden = fractionable;
                }
                for (const field of dryFoodHiddenFields) {
                    field.hidden = fractionable;
                    for (const control of field.querySelectorAll('input,select,textarea')) control.disabled = fractionable;
                }

                fractionableInfo.hidden = !fractionable;

                panelTitle.textContent = fractionable ? 'Stock base del alimento' : 'Venta e inventario';
                panelHelp.textContent = fractionable
                    ? 'Administra la cantidad total disponible del alimento base.'
                    : 'Configura el precio y las cantidades disponibles del producto.';

                optionalTitle.textContent = fractionable ? 'Datos técnicos del alimento' : 'Datos opcionales';

                initialLabel.innerHTML = (fractionable ? 'Stock inicial en gramos' : 'Stock inicial en unidades') + ' <span class="admin-required">*</span>';

                initialHelp.textContent = fractionable
                    ? 'Ingresa el peso total en gramos. Ejemplo: saco de 10 kg = 10000.'
                    : 'Ingresa la cantidad de unidades disponibles.';

                initialStock.placeholder = fractionable ? 'Ej.: 10000' : 'Ej.: 30';
                initialStock.type = 'number';
                initialStock.inputMode = 'numeric';
                initialStock.step = '1';
            };

            if (
                productForm instanceof HTMLFormElement
                && imageInput instanceof HTMLInputElement
                && imageHelp instanceof HTMLElement
            ) {
                imageInput.addEventListener('change', () => {
                    const files = Array.from(imageInput.files ?? []);
                    const maxFiles = Number.parseInt(imageInput.dataset.maxFiles ?? '5', 10);

                    if (files.length > maxFiles) {
                        imageInput.value = '';
                        imageInput.setCustomValidity(`Selecciona como máximo ${maxFiles} imágenes.`);
                        imageInput.reportValidity();
                        imageHelp.textContent = `Puedes seleccionar hasta ${maxFiles} imágenes.`;
                        if (imagePreview instanceof HTMLImageElement) {
                            imagePreview.hidden = true;
                            imagePreview.removeAttribute('src');
                        }
                        if (imagePlaceholder instanceof HTMLElement) {
                            imagePlaceholder.hidden = false;
                        }
                        return;
                    }

                    imageInput.setCustomValidity('');
                    const firstExtension = files[0]?.name.split('.').pop()?.toLowerCase() ?? '';
                    const firstIsHeic = firstExtension === 'heic' || firstExtension === 'heif';
                    const hasHeic = files.some((file) => ['heic', 'heif'].includes(file.name.split('.').pop()?.toLowerCase() ?? ''));
                    imageHelp.textContent = files.length > 0
                        ? (hasHeic
                            ? `${files.length} imagen(es) seleccionada(s). Las HEIC/HEIF se convertirán a WEBP al guardar; es posible que el navegador no pueda previsualizarlas.`
                            : `${files.length} imagen(es) seleccionada(s). La primera quedará como principal.`)
                        : 'Puedes seleccionar hasta 10 imágenes. JPG, PNG o WEBP (máximo 2 MB), o HEIC/HEIF (máximo 20 MB; se convertirá a WEBP al guardar). La primera quedará como principal.';

                    if (
                        files[0]
                        && imagePreview instanceof HTMLImageElement
                        && imagePlaceholder instanceof HTMLElement
                    ) {
                        if (firstIsHeic) {
                            imagePreview.hidden = true;
                            imagePreview.removeAttribute('src');
                            imagePlaceholder.hidden = false;
                            const placeholderMessage = imagePlaceholder.querySelector('strong');
                            if (placeholderMessage instanceof HTMLElement) {
                                placeholderMessage.textContent = 'La imagen HEIC/HEIF se convertirá a WEBP al guardar';
                            }
                        } else {
                            const objectUrl = URL.createObjectURL(files[0]);
                            imagePreview.src = objectUrl;
                            imagePreview.hidden = false;
                            imagePlaceholder.hidden = true;
                            imagePreview.addEventListener('load', () => URL.revokeObjectURL(objectUrl), { once: true });
                        }
                    } else {
                        if (imagePreview instanceof HTMLImageElement) {
                            imagePreview.hidden = true;
                            imagePreview.removeAttribute('src');
                        }
                        if (imagePlaceholder instanceof HTMLElement) {
                            imagePlaceholder.hidden = false;
                        }
                    }
                });

                productForm.addEventListener('submit', () => {
                    if (submitButton instanceof HTMLButtonElement) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Guardando producto…';
                    }
                });
            }

            category.addEventListener('change', updateFields);
            subcategory.addEventListener('change', updateFields);
            const renderLotes = () => {
                const actuales = [...lotesContainer.querySelectorAll('[data-lote]')].map(lote => Object.fromEntries([...lote.querySelectorAll('[data-lote-campo]')].map(input => [input.dataset.loteCampo, input.value])));
                const cantidad = Math.max(1, Number.parseInt(cantidadLotes.value || '1', 10));
                lotesContainer.replaceChildren();
                for (let i = 0; i < cantidad; i++) {
                    const fragment = loteTemplate.content.cloneNode(true);
                    fragment.querySelector('[data-lote-numero]').textContent = String(i + 1);
                    for (const input of fragment.querySelectorAll('[data-lote-campo]')) {
                        input.name = `lotes[${i}][${input.dataset.loteCampo}]`;
                        input.value = (actuales[i] ?? lotesPrevios[i] ?? {})[input.dataset.loteCampo] ?? '';
                    }
                    lotesContainer.append(fragment);
                }
                updateFields();
            };
            cantidadLotes.addEventListener('change', renderLotes);
            renderLotes();
            updateFields();
        })();
    </script>
    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
