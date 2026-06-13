BEGIN;

INSERT INTO usuarios (nombre, correo, contrasena, rol)
VALUES
    ('Administrador FreshStock', 'admin@freshstock.local', '$2y$12$b/licG2oWeT.KrmH5JLoZO09SB/sGLcLVjWc6qqfPK5sFn4rkU5q2', 'admin'),
    ('Operador Demo', 'operador@freshstock.local', '$2y$12$mrZUzsG8eYLGfr0XFMazZuiHG3Fua5CzOiZRB4ZtMWN3OPBFDcuCy', 'operador')
ON CONFLICT DO NOTHING;

INSERT INTO categorias (nombre, descripcion)
VALUES
    ('Abarrotes', 'Productos secos y envasados de consumo diario'),
    ('Lácteos', 'Productos refrigerados derivados de la leche'),
    ('Bebidas', 'Bebidas sin alcohol y productos hidratantes'),
    ('Limpieza', 'Artículos para higiene y limpieza del hogar')
ON CONFLICT DO NOTHING;

INSERT INTO productos (
    categoria_id,
    nombre,
    codigo_barras,
    descripcion,
    unidad_medida,
    precio,
    stock_actual,
    stock_minimo,
    fecha_vencimiento
)
SELECT c.id, v.nombre, v.codigo_barras, v.descripcion, v.unidad_medida, v.precio, v.stock_actual, v.stock_minimo, v.fecha_vencimiento
FROM (
    VALUES
        ('Abarrotes', 'Arroz extra 1 kg', '775000000001', 'Arroz extra embolsado', 'bolsa', 4.50::numeric, 30, 10, (CURRENT_DATE + INTERVAL '180 days')::date),
        ('Lácteos', 'Yogurt natural 1 L', '775000000002', 'Yogurt refrigerado', 'botella', 7.90::numeric, 5, 8, (CURRENT_DATE + INTERVAL '12 days')::date),
        ('Bebidas', 'Agua mineral 625 ml', '775000000003', 'Agua sin gas', 'botella', 2.00::numeric, 40, 12, (CURRENT_DATE + INTERVAL '365 days')::date),
        ('Limpieza', 'Detergente 500 g', '775000000004', 'Detergente en polvo', 'bolsa', 6.50::numeric, 7, 7, NULL::date)
) AS v(categoria, nombre, codigo_barras, descripcion, unidad_medida, precio, stock_actual, stock_minimo, fecha_vencimiento)
INNER JOIN categorias c ON c.nombre = v.categoria
ON CONFLICT (codigo_barras) DO NOTHING;

COMMIT;
