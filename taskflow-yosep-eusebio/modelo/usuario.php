<?php

declare(strict_types=1);

class UsuarioModel
{
    public function __construct(private readonly PDO $conexion)
    {
    }

    public function crear(string $nombre, string $correo, string $contrasena): array
    {
        $sql = 'INSERT INTO usuarios (nombre, correo, contrasena)
                VALUES (:nombre, LOWER(:correo), :contrasena)
                RETURNING id, nombre, correo, rol, activo, creado_en';
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':nombre' => trim($nombre),
            ':correo' => trim($correo),
            ':contrasena' => password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        return $stmt->fetch();
    }

    public function buscarPorCorreo(string $correo): array|false
    {
        $stmt = $this->conexion->prepare(
            'SELECT id, nombre, correo, contrasena, rol, activo
             FROM usuarios
             WHERE LOWER(correo) = LOWER(:correo)
             LIMIT 1'
        );
        $stmt->execute([':correo' => trim($correo)]);
        return $stmt->fetch();
    }

    public function buscarActivoPorId(int $id): array|false
    {
        $stmt = $this->conexion->prepare(
            'SELECT id, nombre, correo, rol, activo
             FROM usuarios
             WHERE id = :id AND activo = TRUE
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
