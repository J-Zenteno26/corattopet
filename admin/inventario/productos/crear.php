<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once __DIR__ . '/includes/funciones-producto.php';

requireAuthentication();

$state = consumirEstadoFormularioProducto();
$values = array_merge(valoresInicialesProducto(), $state['valores'] ?? []);
$errors = is_array($state['errores'] ?? null) ? $state['errores'] : [];
$generalError = is_string($state['error_general'] ?? null) ? $state['error_general'] : null;
$options = ['categorias' => [], 'marcas' => []];
$optionsError = false;

try {
    $options = obtenerOpcionesProducto(database());
} catch (Throwable $exception) {
    $optionsError = true;
    error_log('Product form options error: ' . $exception->getMessage());
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
            <a class="admin-back-link" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">← Volver al inventario</a>
            <h1 class="admin-page-title admin-page-title--paw">Agregar producto</h1>
            <p>Registra la información comercial y el stock inicial del producto.</p>
        </div>
    </header>

    <?php if ($errors !== [] || $generalError !== null): ?>
        <div class="admin-alert admin-alert--error" role="alert" tabindex="-1">
            <strong>No fue posible guardar el producto.</strong>
            <?php if ($generalError !== null): ?>
                <p><?= escape($generalError) ?></p>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape((string) $error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$canSubmit): ?>
        <div class="admin-alert admin-alert--warning" role="status">
            <strong>No es posible registrar productos todavía.</strong>
            <p>Debe existir al menos una categoría activa y una marca activa.</p>
        </div>
    <?php endif; ?>

    <form class="admin-product-form" method="post" action="<?= escape(appUrl('admin/inventario/productos/guardar.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

        <section class="admin-panel" aria-labelledby="main-information-title">
            <div class="admin-panel__header">
                <h2 id="main-information-title">Información principal</h2>
                <p class="admin-field__help">Los campos marcados con <span class="admin-required">*</span> son obligatorios.</p>
            </div>

            <div class="admin-form-grid">
                <div class="admin-field admin-field--full<?= isset($errors['nombre']) ? ' admin-field--invalid' : '' ?>">
                    <label for="nombre">Nombre del producto <span class="admin-required">*</span></label>
                    <input id="nombre" name="nombre" type="text" maxlength="180" required value="<?= escape($values['nombre']) ?>" <?= isset($errors['nombre']) ? 'aria-invalid="true" aria-describedby="nombre-error"' : '' ?>>
                    <?php if (isset($errors['nombre'])): ?><span class="admin-field__error" id="nombre-error"><?= escape($errors['nombre']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['sku']) ? ' admin-field--invalid' : '' ?>">
                    <label for="sku">SKU</label>
                    <input id="sku" name="sku" type="text" maxlength="100" value="<?= escape($values['sku']) ?>" <?= isset($errors['sku']) ? 'aria-invalid="true" aria-describedby="sku-error"' : '' ?>>
                    <?php if (isset($errors['sku'])): ?><span class="admin-field__error" id="sku-error"><?= escape($errors['sku']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['codigo_barras']) ? ' admin-field--invalid' : '' ?>">
                    <label for="codigo_barras">Código de barras</label>
                    <input id="codigo_barras" name="codigo_barras" type="text" maxlength="100" value="<?= escape($values['codigo_barras']) ?>" <?= isset($errors['codigo_barras']) ? 'aria-invalid="true" aria-describedby="codigo-barras-error"' : '' ?>>
                    <?php if (isset($errors['codigo_barras'])): ?><span class="admin-field__error" id="codigo-barras-error"><?= escape($errors['codigo_barras']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['id_categoria']) ? ' admin-field--invalid' : '' ?>">
                    <label for="id_categoria">Categoría <span class="admin-required">*</span></label>
                    <select id="id_categoria" name="id_categoria" required <?= isset($errors['id_categoria']) ? 'aria-invalid="true" aria-describedby="categoria-error"' : '' ?>>
                        <option value="">Selecciona una categoría</option>
                        <?php foreach ($options['categorias'] as $category): ?>
                            <option value="<?= escape((string) $category['id_categoria']) ?>" <?= (string) $values['id_categoria'] === (string) $category['id_categoria'] ? 'selected' : '' ?>><?= escape((string) $category['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['id_categoria'])): ?><span class="admin-field__error" id="categoria-error"><?= escape($errors['id_categoria']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['id_marca']) ? ' admin-field--invalid' : '' ?>">
                    <label for="id_marca">Marca <span class="admin-required">*</span></label>
                    <select id="id_marca" name="id_marca" required <?= isset($errors['id_marca']) ? 'aria-invalid="true" aria-describedby="marca-error"' : '' ?>>
                        <option value="">Selecciona una marca</option>
                        <?php foreach ($options['marcas'] as $brand): ?>
                            <option value="<?= escape((string) $brand['id_marca']) ?>" <?= (string) $values['id_marca'] === (string) $brand['id_marca'] ? 'selected' : '' ?>><?= escape((string) $brand['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['id_marca'])): ?><span class="admin-field__error" id="marca-error"><?= escape($errors['id_marca']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['tipo_mascota']) ? ' admin-field--invalid' : '' ?>">
                    <label for="tipo_mascota">Tipo de mascota <span class="admin-required">*</span></label>
                    <select id="tipo_mascota" name="tipo_mascota" required <?= isset($errors['tipo_mascota']) ? 'aria-invalid="true" aria-describedby="mascota-error"' : '' ?>>
                        <option value="">Selecciona un tipo</option>
                        <?php foreach (['perro' => 'Perro', 'gato' => 'Gato', 'ambos' => 'Perro y gato', 'otro' => 'Otro'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $values['tipo_mascota'] === $value ? 'selected' : '' ?>><?= escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['tipo_mascota'])): ?><span class="admin-field__error" id="mascota-error"><?= escape($errors['tipo_mascota']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['precio_venta']) ? ' admin-field--invalid' : '' ?>">
                    <label for="precio_venta">Precio de venta <span class="admin-required">*</span></label>
                    <input id="precio_venta" name="precio_venta" type="text" inputmode="numeric" required value="<?= escape($values['precio_venta']) ?>" <?= isset($errors['precio_venta']) ? 'aria-invalid="true" aria-describedby="precio-error precio-help"' : 'aria-describedby="precio-help"' ?>>
                    <span class="admin-field__help" id="precio-help">Ingresa el valor en pesos, sin decimales.</span>
                    <?php if (isset($errors['precio_venta'])): ?><span class="admin-field__error" id="precio-error"><?= escape($errors['precio_venta']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['stock_inicial']) ? ' admin-field--invalid' : '' ?>">
                    <label for="stock_inicial">Stock inicial <span class="admin-required">*</span></label>
                    <input id="stock_inicial" name="stock_inicial" type="number" min="0" step="1" required value="<?= escape($values['stock_inicial']) ?>" <?= isset($errors['stock_inicial']) ? 'aria-invalid="true" aria-describedby="stock-error"' : '' ?>>
                    <?php if (isset($errors['stock_inicial'])): ?><span class="admin-field__error" id="stock-error"><?= escape($errors['stock_inicial']) ?></span><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-panel" aria-labelledby="optional-information-title">
            <div class="admin-panel__header">
                <h2 id="optional-information-title">Datos opcionales</h2>
                <p class="admin-field__help">Completa únicamente la información disponible para este producto.</p>
            </div>

            <div class="admin-form-grid">
                <?php foreach (['subcategoria' => 'Subcategoría', 'formato' => 'Formato'] as $field => $label): ?>
                    <div class="admin-field<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>">
                        <label for="<?= $field ?>"><?= escape($label) ?></label>
                        <input id="<?= $field ?>" name="<?= $field ?>" type="text" value="<?= escape($values[$field]) ?>" <?= isset($errors[$field]) ? 'aria-invalid="true" aria-describedby="' . $field . '-error"' : '' ?>>
                        <?php if (isset($errors[$field])): ?><span class="admin-field__error" id="<?= $field ?>-error"><?= escape($errors[$field]) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="admin-field<?= isset($errors['peso_contenido']) ? ' admin-field--invalid' : '' ?>">
                    <label for="peso_contenido">Peso o contenido</label>
                    <input id="peso_contenido" name="peso_contenido" type="text" inputmode="decimal" value="<?= escape($values['peso_contenido']) ?>" <?= isset($errors['peso_contenido']) ? 'aria-invalid="true" aria-describedby="peso-error"' : '' ?>>
                    <?php if (isset($errors['peso_contenido'])): ?><span class="admin-field__error" id="peso-error"><?= escape($errors['peso_contenido']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['unidad']) ? ' admin-field--invalid' : '' ?>">
                    <label for="unidad">Unidad</label>
                    <select id="unidad" name="unidad" <?= isset($errors['unidad']) ? 'aria-invalid="true" aria-describedby="unidad-error"' : '' ?>>
                        <option value="">Selecciona una unidad</option>
                        <?php foreach (['g', 'kg', 'ml', 'l', 'unidad', 'pack', 'otro'] as $unit): ?>
                            <option value="<?= $unit ?>" <?= $values['unidad'] === $unit ? 'selected' : '' ?>><?= escape($unit) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['unidad'])): ?><span class="admin-field__error" id="unidad-error"><?= escape($errors['unidad']) ?></span><?php endif; ?>
                </div>

                <div class="admin-field<?= isset($errors['stock_minimo']) ? ' admin-field--invalid' : '' ?>">
                    <label for="stock_minimo">Stock mínimo</label>
                    <input id="stock_minimo" name="stock_minimo" type="number" min="0" step="1" value="<?= escape($values['stock_minimo']) ?>" <?= isset($errors['stock_minimo']) ? 'aria-invalid="true" aria-describedby="stock-minimo-error"' : '' ?>>
                    <?php if (isset($errors['stock_minimo'])): ?><span class="admin-field__error" id="stock-minimo-error"><?= escape($errors['stock_minimo']) ?></span><?php endif; ?>
                </div>

                <?php
                $shortOptionalFields = [
                    'etapa_vida_tamano' => 'Etapa de vida o tamaño',
                    'pais_origen' => 'País de origen',
                    'fraccionadora_importador' => 'Fraccionadora o importador',
                ];
                foreach ($shortOptionalFields as $field => $label):
                ?>
                    <div class="admin-field<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>">
                        <label for="<?= $field ?>"><?= escape($label) ?></label>
                        <input id="<?= $field ?>" name="<?= $field ?>" type="text" value="<?= escape($values[$field]) ?>" <?= isset($errors[$field]) ? 'aria-invalid="true" aria-describedby="' . $field . '-error"' : '' ?>>
                        <?php if (isset($errors[$field])): ?><span class="admin-field__error" id="<?= $field ?>-error"><?= escape($errors[$field]) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php
                $longOptionalFields = [
                    'descripcion' => 'Descripción',
                    'ingredientes_materiales' => 'Ingredientes o materiales',
                    'analisis_caracteristicas' => 'Análisis o características',
                    'datos_reglamentarios' => 'Datos reglamentarios',
                ];
                foreach ($longOptionalFields as $field => $label):
                ?>
                    <div class="admin-field admin-field--full<?= isset($errors[$field]) ? ' admin-field--invalid' : '' ?>">
                        <label for="<?= $field ?>"><?= escape($label) ?></label>
                        <textarea id="<?= $field ?>" name="<?= $field ?>" rows="4" <?= isset($errors[$field]) ? 'aria-invalid="true" aria-describedby="' . $field . '-error"' : '' ?>><?= escape($values[$field]) ?></textarea>
                        <?php if (isset($errors[$field])): ?><span class="admin-field__error" id="<?= $field ?>-error"><?= escape($errors[$field]) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="admin-panel admin-form-actions" aria-label="Acciones del formulario">
            <a class="admin-button" href="<?= escape(appUrl('admin/inventario/index.php')) ?>">Cancelar</a>
            <button class="admin-button admin-button--primary" type="submit" <?= $canSubmit ? '' : 'disabled' ?>>Guardar producto</button>
        </section>
    </form>
<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
