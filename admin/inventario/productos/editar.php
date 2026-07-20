<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/shared/seguridad.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/shared/funciones-stock-fraccionado.php';
require_once __DIR__ . '/includes/funciones-producto.php';
require_once __DIR__ . '/consultas/buscar-producto.php';

requireAuthentication();

$productId = idPositivoProducto($_GET['id'] ?? null);

if ($productId === null) {
    header(
        'Location: ' . appUrl(
            'admin/inventario/index.php?mensaje=no_encontrado'
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
        header(
            'Location: ' . appUrl(
                'admin/inventario/index.php?mensaje=no_encontrado'
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

    $databaseValues = valoresEdicionProducto($product);
} catch (Throwable $exception) {
    error_log(
        'Product edit load error: '
        . $exception->getMessage()
    );

    header(
        'Location: ' . appUrl(
            'admin/inventario/index.php?mensaje=error'
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

$errors = is_array($state['errores'] ?? null)
    ? $state['errores']
    : [];

$generalError = is_string($state['error_general'] ?? null)
    ? $state['error_general']
    : null;

$csrfToken = csrfToken();
$pageTitle = 'Editar producto';
$activeSection = 'inventario';

require dirname(__DIR__, 3) . '/shared/admin-header.php';
require dirname(__DIR__, 3) . '/shared/admin-sidebar.php';
?>

<main class="admin-main" id="contenido-principal">

    <header class="admin-page-header">
        <div>
            <a
                class="admin-back-link"
                href="<?= escape(
                    appUrl('admin/inventario/index.php')
                ) ?>"
            >
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

    <?php if ($errors !== [] || $generalError !== null): ?>
        <div
            class="admin-alert admin-alert--error"
            role="alert"
            tabindex="-1"
        >
            <strong>
                No fue posible actualizar el producto.
            </strong>

            <?php if ($generalError !== null): ?>
                <p><?= escape($generalError) ?></p>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li>
                            <?= escape((string) $error) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="admin-form-layout">

        <form
            class="admin-product-form admin-form-layout__form"
            method="post"
            action="<?= escape(
                appUrl(
                    'admin/inventario/productos/actualizar.php'
                )
            ) ?>"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="id_producto"
                value="<?= (int) $productId ?>"
            >

            <section
                class="admin-panel admin-form-panel"
                aria-labelledby="main-information-title"
            >
                <div class="admin-panel__header">
                    <h2 id="main-information-title">
                        Información principal
                    </h2>

                    <p class="admin-panel__intro">
                        Los campos marcados con
                        <span class="admin-required">*</span>
                        son obligatorios.
                    </p>
                </div>

                <div class="admin-form-grid">

                    <div
                        class="admin-field admin-field--full<?= isset($errors['nombre'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="nombre">
                            Nombre del producto
                            <span class="admin-required">*</span>
                        </label>

                        <input
                            id="nombre"
                            name="nombre"
                            type="text"
                            maxlength="180"
                            required
                            value="<?= escape(
                                (string) $values['nombre']
                            ) ?>"
                            <?= isset($errors['nombre'])
                                ? 'aria-invalid="true" aria-describedby="nombre-error"'
                                : '' ?>
                        >

                        <?php if (isset($errors['nombre'])): ?>
                            <span
                                class="admin-field__error"
                                id="nombre-error"
                            >
                                <?= escape(
                                    (string) $errors['nombre']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div
                        class="admin-field<?= isset($errors['id_categoria'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="id_categoria">
                            Categoría
                            <span class="admin-required">*</span>
                        </label>

                        <select
                            id="id_categoria"
                            name="id_categoria"
                            required
                            <?= isset($errors['id_categoria'])
                                ? 'aria-invalid="true" aria-describedby="categoria-error"'
                                : '' ?>
                        >
                            <?php foreach ($options['categorias'] as $category): ?>
                                <?php
                                $categoryActive = valorBooleanoPostgres(
                                    $category['activo']
                                );
                                ?>

                                <option
                                    value="<?= (int) $category['id_categoria'] ?>"
                                    <?= ((string) $values['id_categoria'] === (string) $category['id_categoria']) ? 'selected' : '' ?>>
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
                            <span
                                class="admin-field__error"
                                id="categoria-error"
                            >
                                <?= escape(
                                    (string) $errors['id_categoria']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div
                        class="admin-field<?= isset($errors['id_marca'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="id_marca">
                            Marca
                            <span class="admin-required">*</span>
                        </label>

                        <select
                            id="id_marca"
                            name="id_marca"
                            required
                            <?= isset($errors['id_marca'])
                                ? 'aria-invalid="true" aria-describedby="marca-error"'
                                : '' ?>
                        >
                            <?php foreach ($options['marcas'] as $brand): ?>
                                <?php
                                $brandActive = valorBooleanoPostgres(
                                    $brand['activo']
                                );
                                ?>

                                <option
                                    value="<?= (int) $brand['id_marca'] ?>"
                                    <?= ((string) $values['id_marca'] === (string) $brand['id_marca']) ? 'selected' : '' ?>
                                >
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
                            <span
                                class="admin-field__error"
                                id="marca-error"
                            >
                                <?= escape(
                                    (string) $errors['id_marca']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div
                        class="admin-field<?= isset($errors['tipo_mascota'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="tipo_mascota">
                            Tipo de mascota
                            <span class="admin-required">*</span>
                        </label>

                        <select
                            id="tipo_mascota"
                            name="tipo_mascota"
                            required
                        >
                            <?php
                            $petTypes = [
                                'perro' => 'Perro',
                                'gato' => 'Gato',
                                'ambos' => 'Perro y gato',
                                'otro' => 'Otro',
                            ];
                            ?>

                            <?php foreach ($petTypes as $value => $label): ?>
                                <option
                                    value="<?= escape($value) ?>"
                                    <?= $values['tipo_mascota'] === $value
                                        ? 'selected'
                                        : '' ?>
                                >
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

                    <?php if (!$fractionable): ?>
                    <div
                        class="admin-field<?= isset($errors['precio_venta'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="precio_venta">
                            Precio de venta
                            <span class="admin-required">*</span>
                        </label>

                        <input
                            id="precio_venta"
                            name="precio_venta"
                            type="text"
                            inputmode="numeric"
                            required
                            value="<?= escape(
                                (string) $values['precio_venta']
                            ) ?>"
                        >

                        <?php if (isset($errors['precio_venta'])): ?>
                            <span class="admin-field__error">
                                <?= escape(
                                    (string) $errors['precio_venta']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="admin-field admin-field--full">
                        <strong>Producto fraccionable</strong>
                        <span class="admin-field__help">Stock administrado en gramos. El precio de venta se gestiona en las presentaciones.</span>
                    </div>
                    <?php endif; ?>

                    <?php
                    $identifierFields = [
                        'sku' => 'SKU',
                        'codigo_barras' => 'Código de barras',
                    ];
                    ?>

                    <?php foreach ($identifierFields as $field => $label): ?>
                        <div
                            class="admin-field<?= isset($errors[$field])
                                ? ' admin-field--invalid'
                                : '' ?>"
                        >
                            <label for="<?= escape($field) ?>">
                                <?= escape($label) ?>
                            </label>

                            <input
                                id="<?= escape($field) ?>"
                                name="<?= escape($field) ?>"
                                type="text"
                                maxlength="100"
                                value="<?= escape(
                                    (string) $values[$field]
                                ) ?>"
                            >

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
                        class="admin-field admin-field--full<?= isset($errors['descripcion'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="descripcion">
                            Descripción
                        </label>

                        <textarea
                            id="descripcion"
                            name="descripcion"
                            rows="4"
                        ><?= escape(
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

            <section
                class="admin-panel admin-form-panel"
                aria-labelledby="additional-information-title"
            >
                <div class="admin-panel__header">
                    <h2 id="additional-information-title">
                        Información adicional
                    </h2>

                    <p class="admin-panel__intro">
                        Completa únicamente la información disponible para este producto.
                    </p>
                </div>

                <div class="admin-form-grid">

                    <?php
                    $additionalFields = [
                        'subcategoria' => 'Subcategoría',
                        'formato' => 'Formato',
                        'etapa_vida_tamano' => 'Etapa de vida o tamaño',
                        'pais_origen' => 'País de origen',
                        'fraccionadora_importador' => 'Fraccionadora o importador',
                    ];
                    ?>

                    <?php foreach ($additionalFields as $field => $label): ?>
                        <div
                            class="admin-field<?= isset($errors[$field])
                                ? ' admin-field--invalid'
                                : '' ?>"
                        >
                            <label for="<?= escape($field) ?>">
                                <?= escape($label) ?>
                            </label>

                            <input
                                id="<?= escape($field) ?>"
                                name="<?= escape($field) ?>"
                                type="text"
                                value="<?= escape(
                                    (string) $values[$field]
                                ) ?>"
                            >

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
                        class="admin-field<?= isset($errors['peso_contenido'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="peso_contenido">
                            Peso o contenido
                        </label>

                        <input
                            id="peso_contenido"
                            name="peso_contenido"
                            type="text"
                            inputmode="decimal"
                            value="<?= escape(
                                (string) $values['peso_contenido']
                            ) ?>"
                        >

                        <?php if (isset($errors['peso_contenido'])): ?>
                            <span class="admin-field__error">
                                <?= escape(
                                    (string) $errors['peso_contenido']
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div
                        class="admin-field<?= isset($errors['unidad'])
                            ? ' admin-field--invalid'
                            : '' ?>"
                    >
                        <label for="unidad">
                            Unidad
                        </label>

                        <select
                            id="unidad"
                            name="unidad"
                        >
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
                                <option
                                    value="<?= escape($unit) ?>"
                                    <?= $values['unidad'] === $unit
                                        ? 'selected'
                                        : '' ?>
                                >
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
                        <div
                            class="admin-field admin-field--full<?= isset($errors[$field])
                                ? ' admin-field--invalid'
                                : '' ?>"
                        >
                            <label for="<?= escape($field) ?>">
                                <?= escape($label) ?>
                            </label>

                            <textarea
                                id="<?= escape($field) ?>"
                                name="<?= escape($field) ?>"
                                rows="4"
                            ><?= escape(
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

            <section
                class="admin-panel admin-form-panel"
                aria-labelledby="inventory-status-title"
            >
                <div class="admin-panel__header">
                    <h2 id="inventory-status-title">
                        Inventario y estado
                    </h2>

                    <p class="admin-panel__intro">
                        La cantidad disponible se administra mediante movimientos de inventario para mantener su trazabilidad.
                    </p>
                </div>

                <div class="admin-form-grid">

                    <div class="admin-field">
                        <label for="cantidad_actual">
                            Cantidad actual
                        </label>

                        <input id="cantidad_actual" type="text" value="<?= escape(formatearCantidadStock((int) $values['cantidad_actual'], $fractionable)) ?>" readonly aria-describedby="stock-trace-help">

                        <span
                            class="admin-field__help"
                            id="stock-trace-help"
                        >
                            Este valor es informativo y no se modifica desde esta pantalla.
                        </span>
                    </div>

                   <div
    class="admin-field<?= isset($errors['stock_minimo'])
        ? ' admin-field--invalid'
        : '' ?>"
>
    <label for="stock_minimo">
        <?= $fractionable ? 'Stock mínimo en gramos' : 'Stock mínimo en unidades' ?>
    </label>

    <input
        id="stock_minimo"
        name="stock_minimo"
        type="number"
        min="0"
        step="1"
        value="<?= escape(
            (string) $values['stock_minimo']
        ) ?>"
        aria-describedby="stock-minimo-help"
    >

    <span
        class="admin-field__help"
        id="stock-minimo-help"
    >
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
                                Los productos inactivos permanecen registrados, pero no deben ofrecerse para la venta.
                            </span>
                        </div>

                        <label class="admin-switch" for="activo">
                            <input
                                id="activo"
                                name="activo"
                                type="checkbox"
                                value="1"
                                <?= $values['activo']
                                    ? 'checked'
                                    : '' ?>
                            >

                            <span
                                class="admin-switch__track"
                                aria-hidden="true"
                            ></span>

                            <span class="admin-switch__label">
                                <?= $values['activo']
                                    ? 'Activo'
                                    : 'Inactivo' ?>
                            </span>
                        </label>
                    </div>

                </div>
                <div class="admin-form-actions admin-form-actions--inside">
                    <a
                        class="admin-button"
                        href="<?= escape(
                            appUrl(
                                'admin/inventario/index.php'
                            )
                        ) ?>"
                    >
                        Cancelar
                    </a>

                    <button
                        class="admin-button admin-button--primary"
                        type="submit"
                    >
                        Actualizar producto
                    </button>
                </div>

            </section>

        </form>

    </div>

<?php require dirname(__DIR__, 3) . '/shared/admin-footer.php'; ?>
