<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/shared/seguridad.php';
require_once dirname(__DIR__, 4) . '/config/database.php';
require_once dirname(__DIR__, 4) . '/shared/funciones-mantenedores.php';
require_once dirname(__DIR__, 4) . '/shared/admin-flash.php';
require_once __DIR__ . '/includes/funciones-imagenes-producto.php';

requireAuthentication();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

/**
 * Convierte la estructura múltiple de $_FILES en una lista de archivos individuales.
 *
 * @return array<int, array{name:mixed,type:mixed,tmp_name:mixed,error:mixed,size:mixed}>
 */
function normalizarArchivosImagenProducto(mixed $files): array
{
    if (!is_array($files) || !is_array($files['name'] ?? null)) {
        return [];
    }

    $normalized = [];

    foreach (array_keys($files['name']) as $index) {
        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

$productId = idPositivoImagenProducto($_POST['id_producto'] ?? null);

$destination = $productId === null
    ? appUrl('admin/inventario/index.php')
    : appUrl('admin/inventario/productos/editar.php?id=' . $productId);

if (!validateCsrfToken($_POST['csrf_token'] ?? null) || $productId === null) {
    guardarModalAdmin(
        'error',
        'No fue posible subir las imágenes',
        'La solicitud no es válida. Recarga la página e intenta nuevamente.'
    );
    header('Location: ' . $destination, true, 303);
    exit;
}

$files = normalizarArchivosImagenProducto($_FILES['imagenes'] ?? null);

if ($files === []) {
    guardarModalAdmin(
        'error',
        'No fue posible subir las imágenes',
        'Selecciona al menos una imagen para continuar.'
    );
    header('Location: ' . $destination, true, 303);
    exit;
}

$connection = null;

try {
    $connection = database();
    $currentImages = listarImagenesProducto($connection, $productId);
    $availableSlots = IMAGEN_PRODUCTO_MAX_CANTIDAD - count($currentImages);

    if ($availableSlots < 1) {
        throw new ImagenProductoException(
            'El producto ya alcanzó el máximo de 5 imágenes activas.'
        );
    }

    if (count($files) > $availableSlots) {
        throw new ImagenProductoException(
            sprintf(
                'Solo puedes subir %d imagen(es) adicional(es) porque el producto admite un máximo de 5.',
                $availableSlots
            )
        );
    }

    $saved = 0;
    $failed = [];

    foreach ($files as $file) {
        $originalName = basename(
            is_string($file['name'] ?? null)
                ? trim((string) $file['name'])
                : 'Imagen'
        );

        try {
            guardarImagenProducto(
                $connection,
                $productId,
                $file,
                $_POST['alt_text'] ?? null
            );
            $saved++;
        } catch (ImagenProductoException $exception) {
            $failed[] = ($originalName !== '' ? $originalName : 'Imagen')
                . ': '
                . $exception->getMessage();
        } catch (Throwable $exception) {
            $reference = registrarExcepcionAdmin(
                'Product image upload error',
                $exception
            );
            $failed[] = ($originalName !== '' ? $originalName : 'Imagen')
                . ': error interno'
                . ($reference !== '' ? ' (ref. ' . $reference . ')' : '');
        }
    }

    if ($saved > 0 && $failed === []) {
        guardarModalAdmin(
            'success',
            'Imágenes subidas',
            sprintf(
                '%d imagen(es) fueron registradas correctamente.',
                $saved
            )
        );
    } elseif ($saved > 0) {
        guardarModalAdmin(
            'warning',
            'Carga completada con observaciones',
            sprintf(
                '%d imagen(es) fueron registradas y %d no pudieron guardarse.',
                $saved,
                count($failed)
            ),
            ['detail' => implode("\n", $failed)]
        );
    } else {
        guardarModalAdmin(
            'error',
            'No fue posible subir las imágenes',
            'Ninguna imagen pudo ser registrada.',
            ['detail' => implode("\n", $failed)]
        );
    }
} catch (ImagenProductoException $exception) {
    guardarModalAdmin(
        'error',
        'No fue posible subir las imágenes',
        'Revisa los archivos seleccionados e intenta nuevamente.',
        ['detail' => $exception->getMessage()]
    );
} catch (Throwable $exception) {
    $reference = registrarExcepcionAdmin(
        'Multiple product image upload error',
        $exception
    );
    guardarModalAdmin(
        'error',
        'No fue posible subir las imágenes',
        'Intenta nuevamente. Si el problema continúa, revisa el registro del sistema.',
        ['reference' => $reference]
    );
}

header('Location: ' . $destination, true, 303);
exit;
