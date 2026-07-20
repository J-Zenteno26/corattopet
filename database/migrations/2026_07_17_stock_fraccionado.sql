BEGIN;

ALTER TABLE categorias
ADD COLUMN IF NOT EXISTS maneja_fraccionamiento boolean NOT NULL DEFAULT false;

UPDATE categorias
SET maneja_fraccionamiento = true
WHERE slug = 'alimentos';

CREATE TABLE IF NOT EXISTS producto_presentaciones (
    id_presentacion SERIAL PRIMARY KEY,
    id_producto INTEGER NOT NULL REFERENCES productos(id_producto) ON DELETE CASCADE,
    nombre VARCHAR(120) NOT NULL,
    cantidad_gramos INTEGER NOT NULL CHECK (cantidad_gramos > 0),
    precio_venta INTEGER NOT NULL CHECK (precio_venta >= 0),
    sku VARCHAR(100),
    activo BOOLEAN NOT NULL DEFAULT true,
    orden INTEGER NOT NULL DEFAULT 0,
    creado_en TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_producto_presentaciones_producto
ON producto_presentaciones(id_producto);

CREATE UNIQUE INDEX IF NOT EXISTS uq_producto_presentaciones_sku
ON producto_presentaciones(sku)
WHERE sku IS NOT NULL AND sku <> '';

COMMIT;
