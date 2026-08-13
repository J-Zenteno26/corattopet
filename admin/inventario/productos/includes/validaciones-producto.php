<?php

declare(strict_types=1);

function validarDatosProducto(array $input, bool $editing = false): array
{
    $values = normalizarValoresProducto($input);
    $errors = [];

    validarTextoRequerido($values, $errors, 'nombre', 'Nombre del producto', 2, 180);
    validarEnteroPositivo($values, $errors, 'id_categoria', 'Selecciona una categoría activa.');
    validarEnteroPositivo($values, $errors, 'id_marca', 'Selecciona una marca activa.');

    if (!in_array($values['tipo_mascota'], ['perro', 'gato', 'ambos', 'otro'], true)) {
        $errors['tipo_mascota'] = 'Selecciona un tipo de mascota válido.';
    }

    validarTextoOpcional($values, $errors, 'sku', 'SKU', 100);
    validarTextoOpcional($values, $errors, 'codigo_barras', 'Código de barras', 100);
    validarTextoOpcional($values, $errors, 'subcategoria', 'Subcategoría', 120);
    validarTextoOpcional($values, $errors, 'descripcion', 'Descripción', 2000);
    validarTextoOpcional($values, $errors, 'ingredientes_materiales', 'Ingredientes o materiales', 3000);
    validarTextoOpcional($values, $errors, 'analisis_caracteristicas', 'Análisis o características', 3000);
    validarTextoOpcional($values, $errors, 'etapa_vida_tamano', 'Etapa de vida o tamaño', 180);
    validarTextoOpcional($values, $errors, 'pais_origen', 'País de origen', 100);
    validarTextoOpcional($values, $errors, 'fraccionadora_importador', 'Fraccionadora o importador', 255);
    validarTextoOpcional($values, $errors, 'datos_reglamentarios', 'Datos reglamentarios', 3000);

    return [$values, $errors];
}

function validarCamposFormatoProducto(array &$values, array &$errors): void
{
    validarTextoOpcional($values, $errors, 'formato', 'Formato', 100);

    if ($values['peso_contenido'] !== '') {
        $normalizedWeight = str_replace(',', '.', $values['peso_contenido']);
        if (!is_numeric($normalizedWeight) || (float) $normalizedWeight <= 0) {
            $errors['peso_contenido'] = 'Ingresa un peso o contenido decimal mayor que 0.';
        } else {
            $values['peso_contenido'] = $normalizedWeight;
        }
    }

    if ($values['unidad'] !== '' && !in_array($values['unidad'], ['g', 'kg', 'ml', 'l', 'unidad', 'pack', 'otro'], true)) {
        $errors['unidad'] = 'Selecciona una unidad válida.';
    }

}

function normalizarValoresProducto(array $input): array
{
    $fields = [
        'nombre', 'id_categoria', 'id_marca', 'tipo_mascota', 'precio_venta', 'stock_inicial', 'unidad_stock_inicial',
        'sku', 'codigo_barras', 'subcategoria', 'formato', 'peso_contenido', 'unidad',
        'energia_metabolizable_kcal_kg',
        'stock_minimo', 'unidad_stock_minimo', 'descripcion', 'ingredientes_materiales', 'analisis_caracteristicas',
        'etapa_vida_tamano', 'pais_origen', 'fraccionadora_importador', 'datos_reglamentarios',
    ];
    $values = [];

    foreach ($fields as $field) {
        $value = $input[$field] ?? '';
        $values[$field] = is_scalar($value) ? trim((string) $value) : '';
    }

    $values['activo'] = ($input['activo'] ?? null) === '1';

    return $values;
}

function validarProductoPorCategoria(array &$values, array &$errors, bool $fractionable, bool $editing): void
{
    if ($fractionable) {
        $values['_precio_venta_entero'] = 0;
        $values['_stock_minimo_entero'] = 5000;
        unset($errors['stock_inicial']);

        return;
    }

    $normalizedPrice = preg_replace('/[$.\s,]/u', '', $values['precio_venta']) ?? '';
    if ($normalizedPrice === '' || !ctype_digit($normalizedPrice)) {
        $errors['precio_venta'] = 'Ingresa un precio entero igual o mayor que 0.';
    } else {
        $values['_precio_venta_entero'] = $normalizedPrice;
    }

    foreach ($editing ? ['stock_minimo'] : ['stock_inicial', 'stock_minimo'] as $field) {
        if ($values[$field] === '' && $field === 'stock_minimo') {
            continue;
        }

        if ($field === 'stock_inicial' && $values[$field] === '') {
            $errors[$field] = 'Ingresa el stock inicial.';
            continue;
        }

        if (!ctype_digit($values[$field])) {
            $errors[$field] = 'Ingresa una cantidad entera igual o mayor que 0.';
            continue;
        }

        $values['_' . $field . '_entero'] = (int) $values[$field];
    }
}

function aplicarReglaSubcategoriaProducto(PDO $connection, array &$values, array &$errors, ?array $category): bool
{
    if ($category === null || !categoriaTieneSubcategoriasActivasProducto($connection, (int) $category['id_categoria'])) {
        $values['subcategoria'] = '';
        return false;
    }

    if ($values['subcategoria'] === '') {
        $errors['subcategoria'] = 'Selecciona una subcategoría activa para la categoría elegida.';
        return false;
    }

    $subcategory = obtenerSubcategoriaActivaProducto(
        $connection,
        (int) $category['id_categoria'],
        $values['subcategoria']
    );
    if ($subcategory === null) {
        $errors['subcategoria'] = 'Selecciona una subcategoría activa de la categoría elegida.';
        return false;
    }

    $values['subcategoria'] = (string) $subcategory['nombre'];

    return esProductoFraccionable([
        'categoria_slug' => $category['slug'] ?? '',
        'subcategoria' => $values['subcategoria'],
    ]);
}

function aplicarReglaEnergiaMetabolizableProducto(array &$values, array &$errors, ?array $category): void
{
    if ($category === null || !esCategoriaAlimentos($category)) {
        $values['energia_metabolizable_kcal_kg'] = '';
        unset($errors['energia_metabolizable_kcal_kg']);
        return;
    }

    $normalizedEnergy = str_replace(',', '.', $values['energia_metabolizable_kcal_kg']);
    if ($normalizedEnergy === '' || !is_numeric($normalizedEnergy) || (float) $normalizedEnergy <= 0) {
        $errors['energia_metabolizable_kcal_kg'] = 'Ingresa una energía metabolizable mayor que 0.';
        return;
    }

    $values['energia_metabolizable_kcal_kg'] = $normalizedEnergy;
}

function validarTextoRequerido(
    array $values,
    array &$errors,
    string $field,
    string $label,
    int $minimum,
    int $maximum
): void {
    $length = mb_strlen($values[$field]);
    if ($length < $minimum || $length > $maximum) {
        $errors[$field] = sprintf('%s debe tener entre %d y %d caracteres.', $label, $minimum, $maximum);
    }
}

function validarTextoOpcional(array $values, array &$errors, string $field, string $label, int $maximum): void
{
    if ($values[$field] !== '' && mb_strlen($values[$field]) > $maximum) {
        $errors[$field] = sprintf('%s no puede superar los %d caracteres.', $label, $maximum);
    }
}

function validarEnteroPositivo(array $values, array &$errors, string $field, string $message): void
{
    if (!ctype_digit($values[$field]) || (int) $values[$field] < 1) {
        $errors[$field] = $message;
    }
}
