BEGIN;

ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS apellido VARCHAR(100),
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255),
    ADD COLUMN IF NOT EXISTS activo BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS email_verificado BOOLEAN NOT NULL DEFAULT FALSE;

UPDATE clientes SET apellido = '' WHERE apellido IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_clientes_email_normalizado
    ON clientes (LOWER(TRIM(email)))
    WHERE email IS NOT NULL AND TRIM(email) <> '';

COMMIT;
