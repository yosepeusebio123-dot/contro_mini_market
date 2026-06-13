BEGIN;

CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL CHECK (char_length(trim(nombre)) >= 3),
    correo VARCHAR(150) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'operador' CHECK (rol IN ('admin', 'operador')),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_usuarios_correo_lower
    ON usuarios (LOWER(correo));

CREATE TABLE IF NOT EXISTS categorias (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_categorias_nombre_lower
    ON categorias (LOWER(nombre));
CREATE INDEX IF NOT EXISTS idx_categorias_activo
    ON categorias (activo);

CREATE TABLE IF NOT EXISTS productos (
    id SERIAL PRIMARY KEY,
    categoria_id INTEGER NOT NULL REFERENCES categorias(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    nombre VARCHAR(150) NOT NULL CHECK (char_length(trim(nombre)) >= 2),
    codigo_barras VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(500),
    unidad_medida VARCHAR(30) NOT NULL DEFAULT 'unidad',
    precio NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (precio >= 0),
    stock_actual INTEGER NOT NULL DEFAULT 0 CHECK (stock_actual >= 0),
    stock_minimo INTEGER NOT NULL DEFAULT 0 CHECK (stock_minimo >= 0),
    fecha_vencimiento DATE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_productos_nombre
    ON productos (nombre);
CREATE INDEX IF NOT EXISTS idx_productos_categoria
    ON productos (categoria_id);
CREATE INDEX IF NOT EXISTS idx_productos_fecha_vencimiento
    ON productos (fecha_vencimiento);
CREATE INDEX IF NOT EXISTS idx_productos_stock
    ON productos (stock_actual, stock_minimo);
CREATE INDEX IF NOT EXISTS idx_productos_activo
    ON productos (activo);

CREATE TABLE IF NOT EXISTS movimientos_stock (
    id BIGSERIAL PRIMARY KEY,
    producto_id INTEGER NOT NULL REFERENCES productos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    cantidad INTEGER NOT NULL CHECK (cantidad > 0),
    tipo_movimiento VARCHAR(10) NOT NULL CHECK (tipo_movimiento IN ('ENTRADA', 'SALIDA')),
    stock_anterior INTEGER NOT NULL CHECK (stock_anterior >= 0),
    stock_resultante INTEGER NOT NULL CHECK (stock_resultante >= 0),
    motivo VARCHAR(255),
    fecha TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_movimientos_producto
    ON movimientos_stock (producto_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_usuario
    ON movimientos_stock (usuario_id);
CREATE INDEX IF NOT EXISTS idx_movimientos_fecha
    ON movimientos_stock (fecha DESC);
CREATE INDEX IF NOT EXISTS idx_movimientos_tipo
    ON movimientos_stock (tipo_movimiento);

CREATE OR REPLACE FUNCTION actualizar_fecha_modificacion()
RETURNS TRIGGER AS $$
BEGIN
    NEW.actualizado_en = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_usuarios_actualizado ON usuarios;
CREATE TRIGGER trg_usuarios_actualizado
BEFORE UPDATE ON usuarios
FOR EACH ROW EXECUTE FUNCTION actualizar_fecha_modificacion();

DROP TRIGGER IF EXISTS trg_categorias_actualizado ON categorias;
CREATE TRIGGER trg_categorias_actualizado
BEFORE UPDATE ON categorias
FOR EACH ROW EXECUTE FUNCTION actualizar_fecha_modificacion();

DROP TRIGGER IF EXISTS trg_productos_actualizado ON productos;
CREATE TRIGGER trg_productos_actualizado
BEFORE UPDATE ON productos
FOR EACH ROW EXECUTE FUNCTION actualizar_fecha_modificacion();

COMMIT;
