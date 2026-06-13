<?php

declare(strict_types=1);

class MovimientoStockModel
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function registrar(
        int $productoId,
        int $usuarioId,
        int $cantidad,
        string $tipo,
        ?string $motivo
    ): array {
        try {
            $this->conexion->beginTransaction();

            $stmtProducto = $this->conexion->prepare(
                'SELECT id, nombre, stock_actual
                 FROM productos
                 WHERE id = :id AND activo = TRUE
                 FOR UPDATE'
            );
            $stmtProducto->execute([':id' => $productoId]);
            $producto = $stmtProducto->fetch();

            if (!$producto) {
                throw new ApiException('Producto no encontrado.', 404);
            }

            $stockAnterior = (int) $producto['stock_actual'];
            $stockResultante = $tipo === 'ENTRADA'
                ? $stockAnterior + $cantidad
                : $stockAnterior - $cantidad;

            if ($stockResultante < 0) {
                throw new ApiException(
                    'Stock insuficiente para realizar la salida.',
                    422,
                    ['cantidad' => "Stock disponible: {$stockAnterior}."]
                );
            }

            $stmtActualizar = $this->conexion->prepare(
                'UPDATE productos SET stock_actual = :stock WHERE id = :id'
            );
            $stmtActualizar->execute([
                ':stock' => $stockResultante,
                ':id' => $productoId,
            ]);

            $stmtMovimiento = $this->conexion->prepare(
                'INSERT INTO movimientos_stock
                 (producto_id, usuario_id, cantidad, tipo_movimiento, stock_anterior, stock_resultante, motivo)
                 VALUES
                 (:producto_id, :usuario_id, :cantidad, :tipo, :stock_anterior, :stock_resultante, :motivo)
                 RETURNING id'
            );
            $stmtMovimiento->execute([
                ':producto_id' => $productoId,
                ':usuario_id' => $usuarioId,
                ':cantidad' => $cantidad,
                ':tipo' => $tipo,
                ':stock_anterior' => $stockAnterior,
                ':stock_resultante' => $stockResultante,
                ':motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
            ]);

            $movimientoId = (int) $stmtMovimiento->fetchColumn();
            $this->conexion->commit();
            return $this->buscarPorId($movimientoId);
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function listar(int $pagina, int $limite, string $filtro): array
    {
        $offset = ($pagina - 1) * $limite;
        $condiciones = [];
        $parametros = [];

        if ($filtro !== '') {
            $condiciones[] = '(p.nombre ILIKE :producto OR u.nombre ILIKE :usuario OR m.motivo ILIKE :motivo)';
            $parametros = [
                ':producto' => "%{$filtro}%",
                ':usuario' => "%{$filtro}%",
                ':motivo' => "%{$filtro}%",
            ];
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        $stmtConteo = $this->conexion->prepare(
            "SELECT COUNT(*)
             FROM movimientos_stock m
             INNER JOIN productos p ON p.id = m.producto_id
             INNER JOIN usuarios u ON u.id = m.usuario_id
             {$where}"
        );
        $stmtConteo->execute($parametros);
        $total = (int) $stmtConteo->fetchColumn();

        $stmt = $this->conexion->prepare(
            "SELECT
                m.id,
                m.producto_id,
                p.nombre AS producto,
                p.codigo_barras,
                m.usuario_id,
                u.nombre AS usuario,
                m.cantidad,
                m.tipo_movimiento,
                m.stock_anterior,
                m.stock_resultante,
                m.motivo,
                m.fecha
             FROM movimientos_stock m
             INNER JOIN productos p ON p.id = m.producto_id
             INNER JOIN usuarios u ON u.id = m.usuario_id
             {$where}
             ORDER BY m.fecha DESC
             LIMIT :limite OFFSET :offset"
        );

        foreach ($parametros as $clave => $valor) {
            $stmt->bindValue($clave, $valor, PDO::PARAM_STR);
        }
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

    public function stockBajo(): array
    {
        $stmt = $this->conexion->query(
            'SELECT p.id, p.nombre, p.codigo_barras, c.nombre AS categoria,
                    p.stock_actual, p.stock_minimo, (p.stock_minimo - p.stock_actual) AS faltante
             FROM productos p
             INNER JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = TRUE AND p.stock_actual <= p.stock_minimo
             ORDER BY faltante DESC, p.nombre ASC'
        );
        return $stmt->fetchAll();
    }

    public function proximosVencer(int $dias): array
    {
        $stmt = $this->conexion->prepare(
            "SELECT p.id, p.nombre, p.codigo_barras, c.nombre AS categoria,
                    p.stock_actual, p.fecha_vencimiento,
                    (p.fecha_vencimiento - CURRENT_DATE) AS dias_restantes
             FROM productos p
             INNER JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = TRUE
               AND p.fecha_vencimiento IS NOT NULL
               AND p.fecha_vencimiento BETWEEN CURRENT_DATE
               AND CURRENT_DATE + (:dias * INTERVAL '1 day')
             ORDER BY p.fecha_vencimiento ASC"
        );
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function resumen(): array
    {
        $stmt = $this->conexion->query(
            "SELECT
                (SELECT COUNT(*) FROM productos WHERE activo = TRUE) AS total_productos,
                (SELECT COUNT(*) FROM categorias WHERE activo = TRUE) AS total_categorias,
                (SELECT COUNT(*) FROM productos WHERE activo = TRUE AND stock_actual <= stock_minimo) AS productos_stock_bajo,
                (SELECT COUNT(*) FROM productos
                 WHERE activo = TRUE AND fecha_vencimiento IS NOT NULL
                   AND fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days') AS productos_por_vencer,
                (SELECT COUNT(*) FROM movimientos_stock WHERE fecha >= CURRENT_DATE) AS movimientos_hoy"
        );
        return $stmt->fetch();
    }

    private function buscarPorId(int $id): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT
                m.id,
                m.producto_id,
                p.nombre AS producto,
                m.usuario_id,
                u.nombre AS usuario,
                m.cantidad,
                m.tipo_movimiento,
                m.stock_anterior,
                m.stock_resultante,
                m.motivo,
                m.fecha
             FROM movimientos_stock m
             INNER JOIN productos p ON p.id = m.producto_id
             INNER JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
