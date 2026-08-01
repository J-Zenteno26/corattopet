CREATE TABLE IF NOT EXISTS subcategorias (
    id_subcategoria bigserial PRIMARY KEY,
    id_categoria bigint NOT NULL REFERENCES categorias(id_categoria),
    nombre varchar(120) NOT NULL,
    slug varchar(120) NOT NULL,
    orden integer NOT NULL DEFAULT 0,
    activo boolean NOT NULL DEFAULT true,
    creado_en timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT subcategorias_categoria_slug_unico UNIQUE (id_categoria, slug)
);

INSERT INTO subcategorias (id_categoria, nombre, slug, orden, activo)
SELECT c.id_categoria, datos.nombre, datos.slug, datos.orden, TRUE
FROM categorias c
CROSS JOIN (VALUES
    ('Alimento seco', 'alimento-seco', 10),
    ('Alimento húmedo', 'alimento-humedo', 20),
    ('Snacks', 'snacks', 30)
) AS datos(nombre, slug, orden)
WHERE c.slug = 'alimentos'
ON CONFLICT (id_categoria, slug) DO UPDATE
SET nombre = EXCLUDED.nombre,
    orden = EXCLUDED.orden,
    actualizado_en = CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS subcategorias_categoria_activas_idx
    ON subcategorias (id_categoria, activo, orden, nombre);
