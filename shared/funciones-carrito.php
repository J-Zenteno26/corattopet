<?php

declare(strict_types=1);

/**
 * Nombre de la clave utilizada para guardar el carrito en la sesión.
 */
const CARRITO_SESION_CLAVE = 'carrito';

/**
 * Cantidad máxima permitida por línea.
 *
 * Esto evita valores absurdos o manipulaciones accidentales.
 * El stock real se validará posteriormente contra la base de datos.
 */
const CARRITO_CANTIDAD_MAXIMA = 99;

/**
 * Devuelve las líneas válidas actualmente guardadas en el carrito.
 *
 * Cada línea contiene únicamente:
 * - id_producto
 * - id_presentacion
 * - cantidad
 */
function obtenerCarritoSesion(): array
{
    $carrito = $_SESSION[CARRITO_SESION_CLAVE] ?? [];

    if (!is_array($carrito)) {
        $_SESSION[CARRITO_SESION_CLAVE] = [];

        return [];
    }

    $lineasValidas = [];

    foreach ($carrito as $clave => $linea) {
        if (!is_string($clave) || !is_array($linea)) {
            continue;
        }

        $idProducto = filter_var(
            $linea['id_producto'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $cantidad = filter_var(
            $linea['cantidad'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $idPresentacion = $linea['id_presentacion'] ?? null;

        if ($idPresentacion !== null) {
            $idPresentacion = filter_var(
                $idPresentacion,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($idPresentacion === false) {
                continue;
            }
        }

        if ($idProducto === false || $cantidad === false) {
            continue;
        }

        $cantidadNormalizada = min(
            CARRITO_CANTIDAD_MAXIMA,
            (int) $cantidad
        );

        $claveCorrecta = generarClaveItemCarrito(
            (int) $idProducto,
            $idPresentacion !== null ? (int) $idPresentacion : null
        );

        $lineasValidas[$claveCorrecta] = [
            'id_producto' => (int) $idProducto,
            'id_presentacion' => $idPresentacion !== null
                ? (int) $idPresentacion
                : null,
            'cantidad' => $cantidadNormalizada,
        ];
    }

    if ($lineasValidas !== $carrito) {
        $_SESSION[CARRITO_SESION_CLAVE] = $lineasValidas;
    }

    return $lineasValidas;
}

/**
 * Genera una firma estable del contenido actual del carrito.
 *
 * Cambia si cambia:
 * - producto
 * - presentación
 * - cantidad
 */
function obtenerFirmaCarritoSesion(): string
{
    $carrito = obtenerCarritoSesion();

    if ($carrito === []) {
        return hash('sha256', '[]');
    }

    ksort($carrito, SORT_STRING);

    $contenido = [];

    foreach ($carrito as $clave => $linea) {
        $contenido[$clave] = [
            'id_producto' => (int) $linea['id_producto'],
            'id_presentacion' => $linea['id_presentacion'] !== null
                ? (int) $linea['id_presentacion']
                : null,
            'cantidad' => (int) $linea['cantidad'],
        ];
    }

    return hash(
        'sha256',
        json_encode(
            $contenido,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        )
    );
}

function generarClaveItemCarrito(
    int $idProducto,
    ?int $idPresentacion = null
): string {
    if ($idProducto <= 0) {
        throw new InvalidArgumentException(
            'El identificador del producto no es válido.'
        );
    }

    if ($idPresentacion !== null && $idPresentacion <= 0) {
        throw new InvalidArgumentException(
            'El identificador de la presentación no es válido.'
        );
    }

    return $idPresentacion === null
        ? 'producto_' . $idProducto
        : 'producto_' . $idProducto . '_presentacion_' . $idPresentacion;
}

/**
 * Normaliza una cantidad recibida desde un formulario.
 */
function normalizarCantidadCarrito(mixed $cantidad): int
{
    $cantidadValidada = filter_var(
        $cantidad,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($cantidadValidada === false) {
        throw new InvalidArgumentException(
            'La cantidad seleccionada no es válida.'
        );
    }

    return min(
        CARRITO_CANTIDAD_MAXIMA,
        (int) $cantidadValidada
    );
}

/**
 * Agrega un producto o una presentación al carrito.
 *
 * Si la línea ya existe, acumula la cantidad.
 * Devuelve la nueva cantidad de esa línea.
 */
function agregarItemCarritoSesion(
    int $idProducto,
    ?int $idPresentacion,
    int $cantidad
): int {
    if ($idProducto <= 0) {
        throw new InvalidArgumentException(
            'El producto seleccionado no es válido.'
        );
    }

    if ($idPresentacion !== null && $idPresentacion <= 0) {
        throw new InvalidArgumentException(
            'La presentación seleccionada no es válida.'
        );
    }

    $cantidad = normalizarCantidadCarrito($cantidad);
    $carrito = obtenerCarritoSesion();
    $clave = generarClaveItemCarrito($idProducto, $idPresentacion);

    $cantidadActual = isset($carrito[$clave])
        ? (int) $carrito[$clave]['cantidad']
        : 0;

    $nuevaCantidad = min(
        CARRITO_CANTIDAD_MAXIMA,
        $cantidadActual + $cantidad
    );

    $carrito[$clave] = [
        'id_producto' => $idProducto,
        'id_presentacion' => $idPresentacion,
        'cantidad' => $nuevaCantidad,
    ];

    $_SESSION[CARRITO_SESION_CLAVE] = $carrito;

    return $nuevaCantidad;
}

/**
 * Reemplaza la cantidad de una línea existente.
 *
 * La validación del stock real se realizará antes de llamar esta función.
 */
function actualizarCantidadItemCarritoSesion(
    string $clave,
    int $cantidad
): void {
    $carrito = obtenerCarritoSesion();

    if (!isset($carrito[$clave])) {
        throw new InvalidArgumentException(
            'El producto no existe en el carrito.'
        );
    }

    $carrito[$clave]['cantidad'] = normalizarCantidadCarrito($cantidad);

    $_SESSION[CARRITO_SESION_CLAVE] = $carrito;
}

/**
 * Elimina una línea específica del carrito.
 */
function eliminarItemCarritoSesion(string $clave): void
{
    $carrito = obtenerCarritoSesion();

    if (!isset($carrito[$clave])) {
        return;
    }

    unset($carrito[$clave]);

    $_SESSION[CARRITO_SESION_CLAVE] = $carrito;
}

/**
 * Elimina todos los productos del carrito.
 */
function vaciarCarritoSesion(): void
{
    $_SESSION[CARRITO_SESION_CLAVE] = [];
}

/**
 * Devuelve la cantidad total de unidades agregadas.
 *
 * Ejemplo:
 * - 2 unidades de un producto
 * - 3 unidades de otro
 * Resultado: 5
 */
function contarUnidadesCarritoSesion(): int
{
    $total = 0;

    foreach (obtenerCarritoSesion() as $linea) {
        $total += (int) $linea['cantidad'];
    }

    return $total;
}

/**
 * Devuelve la cantidad de líneas diferentes del carrito.
 */
function contarLineasCarritoSesion(): int
{
    return count(obtenerCarritoSesion());
}

/**
 * Indica si el carrito no contiene productos.
 */
function carritoSesionEstaVacio(): bool
{
    return obtenerCarritoSesion() === [];
}