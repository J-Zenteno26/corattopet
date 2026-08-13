BEGIN;

ALTER TABLE blog_articulos
    ADD COLUMN IF NOT EXISTS imagen_complementaria VARCHAR(500) NULL;

COMMIT;
