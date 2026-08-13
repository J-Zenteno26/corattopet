BEGIN;

CREATE TABLE IF NOT EXISTS cliente_tokens_recuperacion (
    id_token SERIAL PRIMARY KEY,
    id_cliente INTEGER NOT NULL REFERENCES clientes(id_cliente) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expira_en TIMESTAMP NOT NULL,
    usado_en TIMESTAMP NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_cliente_tokens_recuperacion_cliente
    ON cliente_tokens_recuperacion (id_cliente, creado_en DESC);
CREATE INDEX IF NOT EXISTS idx_cliente_tokens_recuperacion_vigencia
    ON cliente_tokens_recuperacion (token_hash, expira_en)
    WHERE usado_en IS NULL;

COMMIT;
