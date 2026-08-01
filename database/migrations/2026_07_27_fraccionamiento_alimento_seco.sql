-- Auditar primero los productos que la regla anterior trataba como fraccionables.
SELECT
    p.id_producto,
    p.sku,
    p.nombre,
    p.detalles_opcionales->>'subcategoria' AS subcategoria,
    COUNT(pp.id_presentacion) AS presentaciones_existentes
FROM productos p
INNER JOIN categorias c ON c.id_categoria = p.id_categoria
LEFT JOIN producto_presentaciones pp ON pp.id_producto = p.id_producto
WHERE c.slug = 'alimentos'
  AND LOWER(TRIM(COALESCE(p.detalles_opcionales->>'subcategoria', ''))) <> 'alimento seco'
GROUP BY p.id_producto, p.sku, p.nombre, p.detalles_opcionales->>'subcategoria'
ORDER BY p.id_producto;

BEGIN;

-- Candidatos observados en Neon el 2026-07-27. Verificar físicamente que estos SKU
-- correspondan a alimento seco antes de ejecutar. Se usa SKU estable, no IDs.
WITH productos_secos_confirmados(sku) AS (
    VALUES
        ('RC_AP_2k'),
        ('BRIT-C-ADUL-10K'),
        ('ACA-CA-10KG'),
        ('ACA-CAT-10k'),
        ('BRIT-K-10K'),
        ('BRIT-K-U-10K')
)
UPDATE productos p
SET detalles_opcionales = COALESCE(p.detalles_opcionales, '{}'::jsonb)
        || jsonb_build_object(
            'subcategoria_anterior', p.detalles_opcionales->>'subcategoria',
            'subcategoria', 'Alimento seco',
            'subcategoria_codigo', 'alimento-seco'
        ),
    actualizado_en = CURRENT_TIMESTAMP
FROM categorias c, productos_secos_confirmados candidatos
WHERE c.id_categoria = p.id_categoria
  AND c.slug = 'alimentos'
  AND p.sku = candidatos.sku
  AND LOWER(TRIM(COALESCE(p.detalles_opcionales->>'subcategoria', ''))) <> 'alimento seco';

-- Conserva el texto visible y agrega un código estable para la regla de negocio.
UPDATE productos p
SET detalles_opcionales = jsonb_set(
        COALESCE(p.detalles_opcionales, '{}'::jsonb),
        '{subcategoria_codigo}',
        to_jsonb(LOWER(TRIM(REGEXP_REPLACE(
            TRANSLATE(p.detalles_opcionales->>'subcategoria', 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun'),
            '[^a-zA-Z0-9]+',
            '-',
            'g'
        )))),
        TRUE
    ),
    actualizado_en = CURRENT_TIMESTAMP
FROM categorias c
WHERE c.id_categoria = p.id_categoria
  AND c.slug = 'alimentos'
  AND NULLIF(TRIM(p.detalles_opcionales->>'subcategoria'), '') IS NOT NULL
  AND COALESCE(p.detalles_opcionales->>'subcategoria_codigo', '') IS DISTINCT FROM
      LOWER(TRIM(REGEXP_REPLACE(
          TRANSLATE(p.detalles_opcionales->>'subcategoria', 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun'),
          '[^a-zA-Z0-9]+',
          '-',
          'g'
      )));

-- Verificación: solo estas filas cumplen la regla definitiva.
SELECT p.id_producto, p.sku, p.nombre, p.detalles_opcionales->>'subcategoria' AS subcategoria
FROM productos p
INNER JOIN categorias c ON c.id_categoria = p.id_categoria
WHERE c.slug = 'alimentos'
  AND p.detalles_opcionales->>'subcategoria_codigo' = 'alimento-seco'
ORDER BY p.id_producto;

-- No elimina presentaciones: muestra las que quedarán bloqueadas para revisión manual.
SELECT pp.id_presentacion, pp.id_producto, pp.sku, pp.nombre
FROM producto_presentaciones pp
INNER JOIN productos p ON p.id_producto = pp.id_producto
INNER JOIN categorias c ON c.id_categoria = p.id_categoria
WHERE NOT (
    c.slug = 'alimentos'
    AND p.detalles_opcionales->>'subcategoria_codigo' = 'alimento-seco'
)
ORDER BY pp.id_producto, pp.id_presentacion;

-- Ejecutar COMMIT solo después de revisar ambas consultas de verificación.
-- COMMIT;
-- ROLLBACK;
