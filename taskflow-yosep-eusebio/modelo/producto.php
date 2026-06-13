<?php

declare(strict_types=1);

class ProductoModel
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function listar(array $filtros): array
    {
        $pagina = $filtros['page'];
        $limite = $filtros['limit'];
        $offset = ($pagina - 1) * $limite;
        $condiciones = ['p.activo = TRUE'];
        $parametros = [];

        if ($filtros['filter'] !== '') {
            $condiciones[] = '(p.nombre ILIKE :nombre_filtro OR p.codigo_barras ILIKE :codigo_filtro)';
            $parametros[':nombre_filtro'] = '%' . $filtros['filter'] . '%';
            $parametros[':codigo_filtro'] = '%' . $filtros['filter'] . '%';
        }

        if ($filtros['category_id'] !== null) {
            $condiciones[] = 'p.categoria_id = :categoria_id';
            $parametros[':categoria_id'] = $filtros['category_id'];
        }

        if ($filtros['low_stock']) {
            $condiciones[] = 'p.stock_actual <= p.stock_minimo';
        }

        if ($filtros['expiring_days'] !== null) {
            $condiciones[] = 'p.fecha_vencimiento IS NOT NULL
                              AND p.fecha_vencimiento BETWEEN CURRENT_DATE
                              AND CURRENT_DATE + (:dias_vencimiento * INTERVAL \'1 day\')';
            $parametros[':dias_vencimiento'] = $filtros['expiring_days'];
        }

        $where = implode(' AND ', $condiciones);
        $stmtConteo = $this->conexion->prepare("SELECT COUNT(*) FROM productos p WHERE {$where}");
        $this->vincular($stmtConteo, $parametros);
        $stmtConteo->execute();
        $total = (int) $stmtConteo->fetchColumn();

        $sql = "SELECT
                    p.id,
                    p.categoria_id,
                    c.nombre AS categoria,
                    p.nombre,
                    p.codigo_barras,
                    p.descripcion,
                    p.unidad_medida,
                    p.precio,
                    p.stock_actual,
                    p.stock_minimo,
                    p.fecha_vencimiento,
                    (p.stock_actual <= p.stock_minimo) AS stock_bajo,
                    CASE
                        WHEN p.fecha_vencimiento IS NULL THEN FALSE
                        WHEN p.fecha_vencimiento <= CURRENT_DATE + INTERVAL '30 days' THEN TRUE
                        ELSE FALSE
                    END AS proximo_a_vencer,
                    p.creado_en,
                    p.actualizado_en
                FROM productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                WHERE {$where}
                ORDER BY p.id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->conexion->prepare($sql);
        $this->vincular($stmt, $parametros);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'meta' => [
                'page' => $pagina,
                'limit' => $limite,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limite),
            ],
        ];
    }

    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->conexion->prepare(
            "SELECT
                p.id,
                p.categoria_id,
                c.nombre AS categoria,
                p.nombre,
                p.codigo_barras,
                p.descripcion,
                p.unidad_medida,
                p.precio,
                p.stock_actual,
                p.stock_minimo,
                p.fecha_vencimiento,
                p.activo,
                p.creado_en,
                p.actualizado_en
             FROM productos p
             INNER JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id = :id AND p.activo = TRUE"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function crear(array $datos): array
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO productos
             (categoria_id, nombre, codigo_barras, descripcion, unidad_medida, precio, stock_actual, stock_minimo, fecha_vencimiento)
             VALUES
             (:categoria_id, :nombre, :codigo_barras, :descripcion, :unidad_medida, :precio, :stock_actual, :stock_minimo, :fecha_vencimiento)
             RETURNING id'
        );
        $stmt->execute($this->parametrosProducto($datos));
        return $this->buscarPorId((int) $stmt->fetchColumn());
    }

    public function actualizar(int $id, array $datos): array|false
    {
        $parametros = $this->parametrosProducto($datos);
        $parametros[':id'] = $id;
        $stmt = $this->conexion->prepare(
            'UPDATE productos SET
                categoria_id = :categoria_id,
                nombre = :nombre,
                codigo_barras = :codigo_barras,
                descripcion = :descripcion,
                unidad_medida = :unidad_medida,
                precio = :precio,
                stock_actual = :stock_actual,
                stock_minimo = :stock_minimo,
                fecha_vencimiento = :fecha_vencimiento
             WHERE id = :id AND activo = TRUE
             RETURNING id'
        );
        $stmt->execute($parametros);
        $productoId = $stmt->fetchColumn();
        return $productoId === false ? false : $this->buscarPorId((int) $productoId);
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->conexion->prepare(
            'UPDATE productos SET activo = FALSE WHERE id = :id AND activo = TRUE'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function parametrosProducto(array $datos): array
    {
        return [
            ':categoria_id' => (int) $datos['categoria_id'],
            ':nombre' => trim((string) $datos['nombre']),
            ':codigo_barras' => trim((string) $datos['codigo_barras']),
            ':descripcion' => trim((string) ($datos['descripcion'] ?? '')) ?: null,
            ':unidad_medida' => trim((string) $datos['unidad_medida']),
            ':precio' => number_format((float) $datos['precio'], 2, '.', ''),
            ':stock_actual' => (int) $datos['stock_actual'],
            ':stock_minimo' => (int) $datos['stock_minimo'],
            ':fecha_vencimiento' => trim((string) ($datos['fecha_vencimiento'] ?? '')) ?: null,
        ];
    }

    private function vincular(PDOStatement $stmt, array $parametros): void
    {
        foreach ($parametros as $clave => $valor) {
            $tipo = is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($clave, $valor, $tipo);
        }
    }
}
