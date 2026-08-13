BEGIN;

CREATE TABLE IF NOT EXISTS fichas_alimentacion_clientes (
    id_ficha SERIAL PRIMARY KEY,
    id_cliente INTEGER NOT NULL REFERENCES clientes(id_cliente) ON DELETE CASCADE,
    id_producto INTEGER REFERENCES productos(id_producto) ON DELETE SET NULL,
    snapshot JSONB NOT NULL CHECK (jsonb_typeof(snapshot) = 'object'),
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fichas_alimentacion_cliente_fecha
    ON fichas_alimentacion_clientes (id_cliente, creado_en DESC, id_ficha DESC);

COMMIT;
