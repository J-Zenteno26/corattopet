BEGIN;

CREATE TABLE IF NOT EXISTS clientes (
    id_cliente SERIAL PRIMARY KEY,
    nombre VARCHAR(140) NOT NULL,
    email VARCHAR(160),
    telefono VARCHAR(40),
    rut VARCHAR(20),
    direccion TEXT,
    comuna VARCHAR(100),
    region VARCHAR(100),
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_clientes_email ON clientes (email);
CREATE INDEX IF NOT EXISTS idx_clientes_telefono ON clientes (telefono);

CREATE TABLE IF NOT EXISTS pedidos (
    id_pedido SERIAL PRIMARY KEY,
    codigo_pedido VARCHAR(30) UNIQUE NOT NULL,
    id_cliente INTEGER REFERENCES clientes(id_cliente),
    estado VARCHAR(30) NOT NULL DEFAULT 'recibido',
    estado_pago VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    subtotal INTEGER NOT NULL DEFAULT 0,
    descuento INTEGER NOT NULL DEFAULT 0,
    costo_despacho INTEGER NOT NULL DEFAULT 0,
    total INTEGER NOT NULL DEFAULT 0,
    metodo_entrega VARCHAR(30),
    direccion_entrega TEXT,
    comuna_entrega VARCHAR(100),
    region_entrega VARCHAR(100),
    metodo_pago VARCHAR(40),
    referencia_pago VARCHAR(120),
    observaciones_cliente TEXT,
    observaciones_internas TEXT,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pedidos_estado_check CHECK (estado IN ('recibido', 'en_preparacion', 'listo_para_retiro', 'enviado', 'entregado', 'cancelado')),
    CONSTRAINT pedidos_estado_pago_check CHECK (estado_pago IN ('pendiente', 'pagado', 'rechazado', 'reembolsado')),
    CONSTRAINT pedidos_subtotal_check CHECK (subtotal >= 0),
    CONSTRAINT pedidos_descuento_check CHECK (descuento >= 0),
    CONSTRAINT pedidos_costo_despacho_check CHECK (costo_despacho >= 0),
    CONSTRAINT pedidos_total_check CHECK (total >= 0)
);

CREATE INDEX IF NOT EXISTS idx_pedidos_cliente ON pedidos (id_cliente);
CREATE INDEX IF NOT EXISTS idx_pedidos_estado ON pedidos (estado);
CREATE INDEX IF NOT EXISTS idx_pedidos_estado_pago ON pedidos (estado_pago);
CREATE INDEX IF NOT EXISTS idx_pedidos_creado_en ON pedidos (creado_en DESC);

CREATE TABLE IF NOT EXISTS pedido_detalles (
    id_detalle SERIAL PRIMARY KEY,
    id_pedido INTEGER NOT NULL REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
    id_producto INTEGER REFERENCES productos(id_producto),
    id_presentacion INTEGER REFERENCES producto_presentaciones(id_presentacion),
    nombre_producto VARCHAR(180) NOT NULL,
    sku VARCHAR(80),
    tipo_item VARCHAR(30) NOT NULL DEFAULT 'producto',
    cantidad INTEGER NOT NULL DEFAULT 1,
    cantidad_gramos INTEGER,
    precio_unitario INTEGER NOT NULL DEFAULT 0,
    subtotal INTEGER NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pedido_detalles_tipo_check CHECK (tipo_item IN ('producto', 'presentacion')),
    CONSTRAINT pedido_detalles_cantidad_check CHECK (cantidad > 0),
    CONSTRAINT pedido_detalles_gramos_check CHECK (cantidad_gramos IS NULL OR cantidad_gramos > 0),
    CONSTRAINT pedido_detalles_precio_check CHECK (precio_unitario >= 0),
    CONSTRAINT pedido_detalles_subtotal_check CHECK (subtotal >= 0)
);

CREATE INDEX IF NOT EXISTS idx_pedido_detalles_pedido ON pedido_detalles (id_pedido);

CREATE TABLE IF NOT EXISTS pedido_historial_estados (
    id_historial SERIAL PRIMARY KEY,
    id_pedido INTEGER NOT NULL REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
    estado_anterior VARCHAR(30),
    estado_nuevo VARCHAR(30),
    estado_pago_anterior VARCHAR(30),
    estado_pago_nuevo VARCHAR(30),
    observacion TEXT,
    id_usuario INTEGER REFERENCES usuarios(id_usuario),
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_pedido_historial_pedido ON pedido_historial_estados (id_pedido, creado_en DESC);

COMMIT;
