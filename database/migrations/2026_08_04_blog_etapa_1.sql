BEGIN;

CREATE TABLE IF NOT EXISTS blog_categorias (
    id_categoria_blog BIGSERIAL PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(250) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    orden INTEGER NOT NULL DEFAULT 0,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS blog_articulos (
    id_articulo BIGSERIAL PRIMARY KEY,
    id_categoria_blog BIGINT NOT NULL REFERENCES blog_categorias(id_categoria_blog),
    id_autor BIGINT NOT NULL REFERENCES usuarios(id_usuario),
    titulo VARCHAR(180) NOT NULL,
    slug VARCHAR(210) NOT NULL UNIQUE,
    extracto VARCHAR(360) NOT NULL,
    contenido_html TEXT NOT NULL,
    imagen_portada VARCHAR(500) NULL,
    video_url VARCHAR(500) NULL,
    autor_publico VARCHAR(120) NOT NULL DEFAULT 'Equipo Coratto',
    estado VARCHAR(20) NOT NULL DEFAULT 'borrador',
    destacado BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_publicacion TIMESTAMPTZ NULL,
    seo_titulo VARCHAR(70) NULL,
    seo_descripcion VARCHAR(170) NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT blog_articulos_estado_valido
        CHECK (estado IN ('borrador', 'publicado', 'archivado'))
);

CREATE INDEX IF NOT EXISTS idx_blog_articulos_estado
    ON blog_articulos (estado);
CREATE INDEX IF NOT EXISTS idx_blog_articulos_categoria
    ON blog_articulos (id_categoria_blog);
CREATE INDEX IF NOT EXISTS idx_blog_articulos_fecha_publicacion
    ON blog_articulos (fecha_publicacion DESC);
CREATE INDEX IF NOT EXISTS idx_blog_articulos_destacado
    ON blog_articulos (destacado);

INSERT INTO blog_categorias (nombre, slug, orden)
VALUES
    ('Nutrición', 'nutricion', 10),
    ('Bienestar', 'bienestar', 20),
    ('Cuidado diario', 'cuidado-diario', 30),
    ('Cachorros y gatitos', 'cachorros-y-gatitos', 40),
    ('Digestión', 'digestion', 50),
    ('Piel y pelaje', 'piel-y-pelaje', 60),
    ('Guías', 'guias', 70)
ON CONFLICT (slug) DO NOTHING;

ALTER TABLE usuarios
    DROP CONSTRAINT IF EXISTS usuarios_rol_valido;

ALTER TABLE usuarios
    ADD CONSTRAINT usuarios_rol_valido
    CHECK (rol IN ('administrador', 'editor', 'Blog'));

COMMIT;
