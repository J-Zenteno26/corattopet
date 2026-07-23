BEGIN;

ALTER TABLE imagenes_producto
    ADD COLUMN IF NOT EXISTS nombre_original VARCHAR(255),
    ADD COLUMN IF NOT EXISTS activo BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE imagenes_producto
SET activo = TRUE
WHERE activo IS NULL;

DROP INDEX IF EXISTS imagen_principal_unica_por_producto;

CREATE UNIQUE INDEX imagen_principal_activa_unica_por_producto
    ON imagenes_producto (id_producto)
    WHERE es_principal = TRUE AND activo = TRUE;

CREATE INDEX IF NOT EXISTS imagenes_producto_activas_idx
    ON imagenes_producto (id_producto, es_principal DESC, orden, id_imagen)
    WHERE activo = TRUE;

COMMIT;
