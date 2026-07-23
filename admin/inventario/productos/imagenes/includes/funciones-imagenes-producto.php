<?php

declare(strict_types=1);

const IMAGEN_PRODUCTO_MAX_BYTES = 2097152;
const IMAGEN_PRODUCTO_MAX_CANTIDAD = 5;
const IMAGEN_PRODUCTO_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

final class ImagenProductoException extends RuntimeException
{
}

function idPositivoImagenProducto(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function listarImagenesProducto(PDO $connection, int $productId): array
{
    $statement = $connection->prepare(
        'SELECT id_imagen, id_producto, archivo, nombre_original, texto_alternativo,
            orden, es_principal, creado_en
         FROM imagenes_producto
         WHERE id_producto = :id_producto AND activo = TRUE
         ORDER BY es_principal DESC, orden, id_imagen'
    );
    $statement->execute(['id_producto' => $productId]);

    return $statement->fetchAll();
}

function productoExisteParaImagen(PDO $connection, int $productId, bool $lock = false): bool
{
    $statement = $connection->prepare(
        'SELECT 1 FROM productos WHERE id_producto = :id_producto' . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['id_producto' => $productId]);

    return $statement->fetchColumn() !== false;
}

/**
 * @return array{temporal:string,extension:string,mime:string,nombre_original:string}
 */
function validarArchivoImagenProducto(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido de 2 MB.',
            UPLOAD_ERR_NO_FILE => 'Selecciona una imagen para continuar.',
            default => 'La imagen no pudo recibirse correctamente. Intenta nuevamente.',
        };
        throw new ImagenProductoException($message);
    }

    $temporary = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
    $original = basename(is_string($file['name'] ?? null) ? trim($file['name']) : '');
    $size = (int) ($file['size'] ?? 0);
    if ($temporary === '' || $original === '' || $size <= 0 || !is_file($temporary)) {
        throw new ImagenProductoException('El archivo seleccionado no es válido.');
    }
    if ($size > IMAGEN_PRODUCTO_MAX_BYTES) {
        throw new ImagenProductoException('El archivo supera el tamaño máximo permitido de 2 MB.');
    }
    if (preg_match('/\.(php\d*|phtml|phar|cgi|pl|py|sh|exe|com|bat|cmd)(\.|$)/i', $original) === 1) {
        throw new ImagenProductoException('El nombre del archivo contiene una extensión no permitida.');
    }

    $extension = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new ImagenProductoException('Usa una imagen JPG, PNG o WEBP.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporary);
    if (!is_string($mime) || !isset(IMAGEN_PRODUCTO_MIMES[$mime])) {
        throw new ImagenProductoException('El contenido del archivo no corresponde a una imagen JPG, PNG o WEBP.');
    }
    if (($extension === 'jpg' || $extension === 'jpeg') !== ($mime === 'image/jpeg')) {
        throw new ImagenProductoException('La extensión del archivo no coincide con su contenido.');
    }
    if (in_array($extension, ['png', 'webp'], true) && IMAGEN_PRODUCTO_MIMES[$mime] !== $extension) {
        throw new ImagenProductoException('La extensión del archivo no coincide con su contenido.');
    }
    $imageInfo = @getimagesize($temporary);
    if (!is_array($imageInfo) || ($imageInfo[0] ?? 0) < 1 || ($imageInfo[1] ?? 0) < 1 || ($imageInfo['mime'] ?? '') !== $mime) {
        throw new ImagenProductoException('No fue posible validar la estructura de la imagen.');
    }

    return [
        'temporal' => $temporary,
        'extension' => IMAGEN_PRODUCTO_MIMES[$mime],
        'mime' => $mime,
        'nombre_original' => mb_substr($original, 0, 255),
    ];
}

function normalizarTextoAlternativoImagen(mixed $value): ?string
{
    $text = is_string($value) ? trim($value) : '';
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
    $text = trim($text);

    return $text === '' ? null : mb_substr($text, 0, 180);
}

function directorioImagenProducto(int $productId): string
{
    return dirname(__DIR__, 5) . '/public/uploads/productos/' . $productId;
}

function rutaPublicaImagenProducto(int $productId, string $filename): string
{
    return 'uploads/productos/' . $productId . '/' . $filename;
}

function guardarImagenProducto(PDO $connection, int $productId, array $file, mixed $altText): void
{
    $validated = validarArchivoImagenProducto($file);
    guardarImagenProductoValidada($connection, $productId, $validated, $altText);
}

/**
 * @param array{temporal:string,extension:string,mime:string,nombre_original:string} $validated
 */
function guardarImagenProductoValidada(PDO $connection, int $productId, array $validated, mixed $altText): void
{
    $alt = normalizarTextoAlternativoImagen($altText);
    $directory = directorioImagenProducto($productId);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('No fue posible preparar el almacenamiento de imágenes.');
    }
    $filename = sprintf(
        'producto_%d_%s_%s.%s',
        $productId,
        gmdate('Ymd_His'),
        bin2hex(random_bytes(6)),
        $validated['extension']
    );
    $destination = $directory . '/' . $filename;
    if (!move_uploaded_file($validated['temporal'], $destination)) {
        throw new ImagenProductoException('No fue posible guardar la imagen seleccionada.');
    }
    $relativePath = rutaPublicaImagenProducto($productId, $filename);

    try {
        $connection->beginTransaction();
        if (!productoExisteParaImagen($connection, $productId, true)) {
            throw new ImagenProductoException('El producto indicado no existe.');
        }
        $countStatement = $connection->prepare(
            'SELECT COUNT(*) FROM imagenes_producto WHERE id_producto = :id_producto AND activo = TRUE'
        );
        $countStatement->execute(['id_producto' => $productId]);
        $count = (int) $countStatement->fetchColumn();
        if ($count >= IMAGEN_PRODUCTO_MAX_CANTIDAD) {
            throw new ImagenProductoException('Cada producto puede tener un máximo de 5 imágenes activas.');
        }
        $isPrimary = $count === 0;
        $insert = $connection->prepare(
            'INSERT INTO imagenes_producto
                (id_producto, archivo, nombre_original, texto_alternativo, orden, es_principal, activo, actualizado_en)
             VALUES
                (:id_producto, :archivo, :nombre_original, :texto_alternativo, :orden, :es_principal, TRUE, CURRENT_TIMESTAMP)'
        );
        $insert->bindValue(':id_producto', $productId, PDO::PARAM_INT);
        $insert->bindValue(':archivo', $relativePath);
        $insert->bindValue(':nombre_original', $validated['nombre_original']);
        $insert->bindValue(':texto_alternativo', $alt);
        $insert->bindValue(':orden', $count, PDO::PARAM_INT);
        $insert->bindValue(':es_principal', $isPrimary, PDO::PARAM_BOOL);
        $insert->execute();
        if ($isPrimary) {
            sincronizarImagenPrincipalProducto($connection, $productId, $relativePath);
        }
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        @unlink($destination);
        throw $exception;
    }
}

function marcarImagenPrincipalProducto(PDO $connection, int $productId, int $imageId): bool
{
    $connection->beginTransaction();
    try {
        if (!productoExisteParaImagen($connection, $productId, true)) {
            $connection->rollBack();
            return false;
        }
        $statement = $connection->prepare(
            'SELECT archivo FROM imagenes_producto
             WHERE id_imagen = :id_imagen AND id_producto = :id_producto AND activo = TRUE
             FOR UPDATE'
        );
        $statement->execute(['id_imagen' => $imageId, 'id_producto' => $productId]);
        $path = $statement->fetchColumn();
        if (!is_string($path)) {
            $connection->rollBack();
            return false;
        }
        $connection->prepare(
            'UPDATE imagenes_producto SET es_principal = FALSE, actualizado_en = CURRENT_TIMESTAMP
             WHERE id_producto = :id_producto AND activo = TRUE'
        )->execute(['id_producto' => $productId]);
        $connection->prepare(
            'UPDATE imagenes_producto SET es_principal = TRUE, actualizado_en = CURRENT_TIMESTAMP
             WHERE id_imagen = :id_imagen AND id_producto = :id_producto AND activo = TRUE'
        )->execute(['id_imagen' => $imageId, 'id_producto' => $productId]);
        sincronizarImagenPrincipalProducto($connection, $productId, $path);
        $connection->commit();

        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function desactivarImagenProducto(PDO $connection, int $productId, int $imageId): bool
{
    $connection->beginTransaction();
    try {
        if (!productoExisteParaImagen($connection, $productId, true)) {
            $connection->rollBack();
            return false;
        }
        $statement = $connection->prepare(
            'SELECT es_principal FROM imagenes_producto
             WHERE id_imagen = :id_imagen AND id_producto = :id_producto AND activo = TRUE
             FOR UPDATE'
        );
        $statement->execute(['id_imagen' => $imageId, 'id_producto' => $productId]);
        $image = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($image)) {
            $connection->rollBack();
            return false;
        }
        $connection->prepare(
            'UPDATE imagenes_producto
             SET activo = FALSE, es_principal = FALSE, actualizado_en = CURRENT_TIMESTAMP
             WHERE id_imagen = :id_imagen AND id_producto = :id_producto'
        )->execute(['id_imagen' => $imageId, 'id_producto' => $productId]);

        if (in_array($image['es_principal'], [true, 1, '1', 't', 'true'], true)) {
            $next = $connection->prepare(
                'SELECT id_imagen, archivo FROM imagenes_producto
                 WHERE id_producto = :id_producto AND activo = TRUE
                 ORDER BY orden, id_imagen LIMIT 1 FOR UPDATE'
            );
            $next->execute(['id_producto' => $productId]);
            $replacement = $next->fetch();
            if (is_array($replacement)) {
                $connection->prepare(
                    'UPDATE imagenes_producto SET es_principal = TRUE, actualizado_en = CURRENT_TIMESTAMP
                     WHERE id_imagen = :id_imagen'
                )->execute(['id_imagen' => (int) $replacement['id_imagen']]);
                sincronizarImagenPrincipalProducto($connection, $productId, (string) $replacement['archivo']);
            } else {
                sincronizarImagenPrincipalProducto($connection, $productId, null);
            }
        }
        $connection->commit();

        return true;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $exception;
    }
}

function sincronizarImagenPrincipalProducto(PDO $connection, int $productId, ?string $path): void
{
    $statement = $connection->prepare(
        'UPDATE productos
         SET imagen_principal = :imagen_principal, actualizado_en = CURRENT_TIMESTAMP
         WHERE id_producto = :id_producto'
    );
    $statement->bindValue(':imagen_principal', $path, $path === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':id_producto', $productId, PDO::PARAM_INT);
    $statement->execute();
}

function urlPublicaImagenProducto(mixed $path): ?string
{
    if (!is_string($path) || trim($path) === '') {
        return null;
    }
    $relative = ltrim(trim($path), '/');
    if (!str_starts_with($relative, 'uploads/productos/')) {
        return null;
    }

    return appUrl('public/' . $relative);
}
