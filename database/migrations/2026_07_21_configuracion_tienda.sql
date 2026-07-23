BEGIN;

CREATE TABLE IF NOT EXISTS configuracion_tienda (
    id_configuracion SMALLINT PRIMARY KEY DEFAULT 1,
    nombre_tienda VARCHAR(120) NOT NULL DEFAULT 'Coratto Pet',
    razon_social VARCHAR(160),
    rut_empresa VARCHAR(20),
    descripcion_breve TEXT,
    mensaje_principal TEXT,
    mensaje_secundario TEXT,
    email_contacto VARCHAR(160),
    whatsapp_principal VARCHAR(40),
    telefono_secundario VARCHAR(40),
    direccion TEXT,
    comuna VARCHAR(100),
    region VARCHAR(100),
    horario_atencion TEXT,
    permite_retiro BOOLEAN NOT NULL DEFAULT TRUE,
    texto_retiro TEXT,
    permite_despacho BOOLEAN NOT NULL DEFAULT FALSE,
    texto_despacho TEXT,
    monto_minimo_compra INTEGER NOT NULL DEFAULT 0,
    costo_despacho_base INTEGER NOT NULL DEFAULT 0,
    despacho_gratis_desde INTEGER NOT NULL DEFAULT 0,
    tiempo_preparacion TEXT,
    instagram VARCHAR(255),
    facebook VARCHAR(255),
    tiktok VARCHAR(255),
    sitio_externo VARCHAR(255),
    mensaje_banner TEXT,
    mensaje_home TEXT,
    mensaje_checkout TEXT,
    mensaje_post_compra TEXT,
    mensaje_fraccionados TEXT,
    moneda VARCHAR(10) NOT NULL DEFAULT 'CLP',
    iva_incluido BOOLEAN NOT NULL DEFAULT TRUE,
    mostrar_stock BOOLEAN NOT NULL DEFAULT TRUE,
    mostrar_sin_stock BOOLEAN NOT NULL DEFAULT TRUE,
    permitir_venta_sin_stock BOOLEAN NOT NULL DEFAULT FALSE,
    modo_tienda VARCHAR(20) NOT NULL DEFAULT 'activa',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT configuracion_tienda_fila_unica CHECK (id_configuracion = 1),
    CONSTRAINT configuracion_tienda_monto_minimo_check CHECK (monto_minimo_compra >= 0),
    CONSTRAINT configuracion_tienda_costo_despacho_check CHECK (costo_despacho_base >= 0),
    CONSTRAINT configuracion_tienda_despacho_gratis_check CHECK (despacho_gratis_desde >= 0),
    CONSTRAINT configuracion_tienda_modo_check CHECK (modo_tienda IN ('activa', 'mantenimiento'))
);

INSERT INTO configuracion_tienda (id_configuracion, nombre_tienda)
VALUES (1, 'Coratto Pet')
ON CONFLICT (id_configuracion) DO NOTHING;

COMMIT;
