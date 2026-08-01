BEGIN;

CREATE TABLE IF NOT EXISTS proveedores (
    id_proveedor SERIAL PRIMARY KEY,
    nombre VARCHAR(160) NOT NULL,
    razon_social VARCHAR(180) NULL,
    rut VARCHAR(20) NULL,
    giro VARCHAR(180) NULL,
    contacto_principal VARCHAR(120) NULL,
    telefono VARCHAR(40) NULL,
    email VARCHAR(160) NULL,
    direccion TEXT NULL,
    comuna VARCHAR(100) NULL,
    region VARCHAR(100) NULL,
    sitio_web VARCHAR(220) NULL,
    instagram VARCHAR(120) NULL,
    condicion_pago VARCHAR(120) NULL,
    plazo_pago_dias INTEGER NULL CHECK (plazo_pago_dias >= 0),
    metodo_pago VARCHAR(120) NULL,
    dias_despacho VARCHAR(160) NULL,
    monto_minimo_compra NUMERIC(12,2) NULL CHECK (monto_minimo_compra >= 0),
    contacto_ventas VARCHAR(160) NULL,
    contacto_cobranza VARCHAR(160) NULL,
    observaciones TEXT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS proveedor_productos (
    id_proveedor_producto SERIAL PRIMARY KEY,
    id_proveedor INTEGER NOT NULL REFERENCES proveedores(id_proveedor),
    id_producto INTEGER NOT NULL REFERENCES productos(id_producto),
    sku_proveedor VARCHAR(80) NULL,
    precio_compra NUMERIC(12,2) NULL CHECK (precio_compra >= 0),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (id_proveedor, id_producto)
);

CREATE INDEX IF NOT EXISTS idx_proveedores_nombre ON proveedores(nombre);
CREATE INDEX IF NOT EXISTS idx_proveedores_rut ON proveedores(rut);
CREATE INDEX IF NOT EXISTS idx_proveedores_activo ON proveedores(activo);
CREATE INDEX IF NOT EXISTS idx_proveedor_productos_proveedor ON proveedor_productos(id_proveedor);
CREATE INDEX IF NOT EXISTS idx_proveedor_productos_producto ON proveedor_productos(id_producto);
CREATE INDEX IF NOT EXISTS idx_proveedor_productos_activo ON proveedor_productos(activo);

DO $$
BEGIN
    IF to_regclass('stock_lotes') IS NOT NULL THEN
        ALTER TABLE stock_lotes ADD COLUMN IF NOT EXISTS id_proveedor INTEGER NULL REFERENCES proveedores(id_proveedor);
        CREATE INDEX IF NOT EXISTS idx_stock_lotes_proveedor ON stock_lotes(id_proveedor);
    END IF;
END $$;

COMMIT;
