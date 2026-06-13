<?php

declare(strict_types=1);

class CategoriaModel
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function listar(int $pagina, int $limite, string $filtro): array
    {
        $offset = ($pagina - 1) * $limite;
        $condiciones = ['activo = TRUE'];
        $parametros = [];

        if ($filtro !== '') {
            $condiciones[] = '(nombre ILIKE :filtro_nombre OR descripcion ILIKE :filtro_descripcion)';
            $parametros[':filtro_nombre'] = "%{$filtro}%";
            $parametros[':filtro_descripcion'] = "%{$filtro}%";
        }

        $where = implode(' AND ', $condiciones);
        $conteo = $this->conexion->prepare("SELECT COUNT(*) FROM categorias WHERE {$where}");
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $sql = "SELECT id, nombre, descripcion, activo, creado_en, actualizado_en
                FROM categorias
                WHERE {$where}
                ORDER BY nombre ASC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->conexion->prepare($sql);

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

    public function buscarPorId(int $id): array|false
    {
        $stmt = $this->conexion->prepare(
            'SELECT id, nombre, descripcion, activo, creado_en, actualizado_en
             FROM categorias
             WHERE id = :id AND activo = TRUE'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function crear(string $nombre, ?string $descripcion): array
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO categorias (nombre, descripcion)
             VALUES (:nombre, :descripcion)
             RETURNING id, nombre, descripcion, activo, creado_en, actualizado_en'
        );
        $stmt->execute([
            ':nombre' => trim($nombre),
            ':descripcion' => $descripcion !== null && trim($descripcion) !== '' ? trim($descripcion) : null,
        ]);
        return $stmt->fetch();
    }

    public function actualizar(int $id, string $nombre, ?string $descripcion): array|false
    {
        $stmt = $this->conexion->prepare(
            'UPDATE categorias
             SET nombre = :nombre, descripcion = :descripcion
             WHERE id = :id AND activo = TRUE
             RETURNING id, nombre, descripcion, activo, creado_en, actualizado_en'
        );
        $stmt->execute([
            ':id' => $id,
            ':nombre' => trim($nombre),
            ':descripcion' => $descripcion !== null && trim($descripcion) !== '' ? trim($descripcion) : null,
        ]);
        return $stmt->fetch();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->conexion->prepare(
            'UPDATE categorias SET activo = FALSE WHERE id = :id AND activo = TRUE'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function tieneProductosActivos(int $id): bool
    {
        $stmt = $this->conexion->prepare(
            'SELECT EXISTS(SELECT 1 FROM productos WHERE categoria_id = :id AND activo = TRUE)'
        );
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }
}
