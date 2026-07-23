<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-mantenedores.php';
require_once __DIR__ . '/includes/funciones-producto.php';

requireAuthentication();

$state = consumirEstadoFormularioProducto();
$values = array_merge(valoresInicialesProducto(), $state['valores'] ?? []);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$errorReference = is_string($state['referencia'] ?? null) ? $state['referencia'] : '';
if ($errors !== [] || $generalError !== null) {
    $imageError = isset($errors['imagen_principal']) && count($errors) === 1;
    $adminModal = [
        'type' => 'error',
        'title' => $imageError ? 'No fue posible subir la imagen' : 'No fue posible guardar el producto',
        'message' => $imageError ? 'Revisa el archivo seleccionado antes de continuar.' : ($errors !== [] ? 'Revisa los campos marcados antes de continuar.' : 'No se pudo completar la acción.'),
        'detail' => resumenErroresFormulario($errors, $generalError),
        'reference' => $errorReference,
        'primaryText' => 'Aceptar',
    ];
}
$options = ['categorias' => [], 'marcas' => []];
$optionsError = false;

try {
    $options = obtenerOpcionesProducto(database());
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
                    <h3>Cargar imagen principal</h3>
                    <div class="admin-field<?= isset($errors['imagen_principal']) ? ' admin-field--invalid' : '' ?>">
                        <label for="imagen_principal">Archivo de imagen</label>
                        <input id="imagen_principal" name="imagen_principal" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-image-preview-input data-preview-target="create-product-image-preview" <?= isset($errors['imagen_principal']) ? 'aria-invalid="true" aria-describedby="imagen-principal-error imagen-principal-help"' : 'aria-describedby="imagen-principal-help"' ?>>
                        <span class="admin-field__help" id="imagen-principal-help">JPG, PNG o WEBP. Máximo 2 MB. La imagen es opcional.</span>
                        <?php if (isset($errors['imagen_principal'])): ?><span class="admin-field__error" id="imagen-principal-error"><?= escape((string) $errors['imagen_principal']) ?></span><?php endif; ?>
                    </div>
                    <div class="admin-field">
                        <label for="imagen_alt_text">Texto alternativo</label>
                        <input id="imagen_alt_text" name="imagen_alt_text" type="text" maxlength="180" value="<?= escape((string) ($values['imagen_alt_text'] ?? '')) ?>" placeholder="Describe brevemente la imagen">
                        <span class="admin-field__help">Opcional. Describe la imagen para mejorar su accesibilidad.</span>
                    </div>
                    <p>La imagen se subirá junto con el producto y quedará marcada como principal.</p>
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
                                data-fraccionable="<?= valorBooleanoPostgres($category['maneja_fraccionamiento']) ? '1' : '0' ?>"
                                <?= (string) $values['id_categoria'] === (string) $category['id_categoria'] ? 'selected' : '' ?>><?= escape((string) $category['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['id_categoria'])): ?><span class="admin-field__error"
                            id="categoria-error"><?= escape($errors['id_categoria']) ?></span><?php endif; ?>
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
                    'subcategoria' => ['label' => 'Subcategoría', 'placeholder' => 'Ej.: Alimento seco'],
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

                        <input id="<?= escape($field) ?>" name="<?= escape($field) ?>" type="text"
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

                <div id="unidad-field" data-presentation-field="1"
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

                <?php
                $shortOptionalFields = [
                    'etapa_vida_tamano' => ['label' => 'Etapa de vida o tamaño', 'placeholder' => 'Ej.: Adulto, razas medianas y grandes'],
                    'pais_origen' => ['label' => 'País de origen', 'placeholder' => 'Ej.: Canadá'],
                    'fraccionadora_importador' => ['label' => 'Fraccionadora o importador', 'placeholder' => 'Ej.: Nombre o razón social de la empresa'],
                ];
                foreach ($shortOptionalFields as $field => $fieldData):
                    ?>
                    <div class="admin-field<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>">
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

            const presentationFields = document.querySelectorAll('[data-presentation-field="1"]');

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
                !fractionableInfo
            ) {
                return;
            }

            const updateFields = () => {
                const fractionable = category.selectedOptions[0]?.dataset.fraccionable === '1';

                priceField.hidden = fractionable;
                price.required = !fractionable;
                price.disabled = fractionable;

                minimumField.hidden = fractionable;
                minimumStock.disabled = fractionable;

                if (fractionable) {
                    minimumStock.value = '';
                }

                for (const field of presentationFields) {
                    field.hidden = fractionable;
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

            category.addEventListener('change', updateFields);
            updateFields();
        })();
    </script>
    <?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
