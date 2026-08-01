BEGIN;

CREATE TABLE IF NOT EXISTS stock_lotes (
    id_lote SERIAL PRIMARY KEY,
    id_producto INTEGER NOT NULL REFERENCES productos(id_producto),
    id_proveedor INTEGER NULL REFERENCES proveedores(id_proveedor),
    codigo_lote VARCHAR(80) NOT NULL,
    fecha_elaboracion DATE NULL,
    fecha_vencimiento DATE NOT NULL,
    cantidad_inicial_g NUMERIC(12,3) NOT NULL CHECK (cantidad_inicial_g > 0),
    cantidad_disponible_g NUMERIC(12,3) NOT NULL CHECK (cantidad_disponible_g >= 0),
    saldo_no_asignado_g NUMERIC(12,3) NOT NULL DEFAULT 0 CHECK (saldo_no_asignado_g >= 0),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL
    ,CHECK (fecha_elaboracion IS NULL OR fecha_elaboracion <= fecha_vencimiento)
    ,CHECK (cantidad_disponible_g <= cantidad_inicial_g)
    ,CHECK (saldo_no_asignado_g <= cantidad_disponible_g)
);

CREATE TABLE IF NOT EXISTS stock_lote_presentaciones (
    id_lote_presentacion SERIAL PRIMARY KEY,
    id_lote INTEGER NOT NULL REFERENCES stock_lotes(id_lote),
    id_presentacion INTEGER NOT NULL REFERENCES producto_presentaciones(id_presentacion),
    unidades_iniciales INTEGER NOT NULL CHECK (unidades_iniciales >= 0),
    unidades_disponibles INTEGER NOT NULL CHECK (unidades_disponibles >= 0),
    gramos_por_unidad NUMERIC(12,3) NOT NULL CHECK (gramos_por_unidad > 0),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL,
    UNIQUE (id_lote, id_presentacion)
);

CREATE INDEX IF NOT EXISTS idx_stock_lotes_producto ON stock_lotes(id_producto);
CREATE INDEX IF NOT EXISTS idx_stock_lotes_proveedor ON stock_lotes(id_proveedor);
CREATE INDEX IF NOT EXISTS idx_stock_lotes_codigo ON stock_lotes(codigo_lote);
CREATE INDEX IF NOT EXISTS idx_stock_lotes_vencimiento ON stock_lotes(fecha_vencimiento);
CREATE INDEX IF NOT EXISTS idx_stock_lotes_activo ON stock_lotes(activo);
CREATE INDEX IF NOT EXISTS idx_stock_lote_presentaciones_lote ON stock_lote_presentaciones(id_lote);
CREATE INDEX IF NOT EXISTS idx_stock_lote_presentaciones_presentacion ON stock_lote_presentaciones(id_presentacion);
CREATE INDEX IF NOT EXISTS idx_stock_lote_presentaciones_disponibles ON stock_lote_presentaciones(unidades_disponibles);
CREATE INDEX IF NOT EXISTS idx_stock_lote_presentaciones_activo ON stock_lote_presentaciones(activo);

DO $$ BEGIN
    ALTER TABLE stock_lotes ADD CONSTRAINT chk_stock_lotes_fechas CHECK (fecha_elaboracion IS NULL OR fecha_elaboracion <= fecha_vencimiento);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN
    ALTER TABLE stock_lotes ADD CONSTRAINT chk_stock_lotes_disponible CHECK (cantidad_disponible_g <= cantidad_inicial_g);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN
    ALTER TABLE stock_lotes ADD CONSTRAINT chk_stock_lotes_saldo CHECK (saldo_no_asignado_g <= cantidad_disponible_g);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

ALTER TABLE movimientos_stock ADD COLUMN IF NOT EXISTS id_lote INTEGER NULL REFERENCES stock_lotes(id_lote);
ALTER TABLE movimientos_stock ADD COLUMN IF NOT EXISTS id_lote_presentacion INTEGER NULL REFERENCES stock_lote_presentaciones(id_lote_presentacion);
CREATE INDEX IF NOT EXISTS idx_movimientos_stock_lote ON movimientos_stock(id_lote);
CREATE INDEX IF NOT EXISTS idx_movimientos_stock_lote_presentacion ON movimientos_stock(id_lote_presentacion);

COMMIT;
