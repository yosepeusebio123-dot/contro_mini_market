<?php

declare(strict_types=1);

require_once __DIR__ . '/util/Entorno.php';

class Database
{
    private ?PDO $conexion = null;

    public function getConnection(): PDO
    {
        if ($this->conexion instanceof PDO) {
            return $this->conexion;
        }

        $host = Entorno::obtener('DB_HOST', 'base_datos');
        $puerto = Entorno::obtener('DB_PORT', '5432');
        $nombre = Entorno::obtener('DB_NAME');
        $usuario = Entorno::obtener('DB_USER');
        $contrasena = Entorno::secretoDesdeArchivo('DB_PASSWORD_FILE');

        if ($nombre === null || $usuario === null) {
            throw new RuntimeException('Faltan variables de conexión a la base de datos.');
        }

        $dsn = "pgsql:host={$host};port={$puerto};dbname={$nombre}";
        $this->conexion = new PDO($dsn, $usuario, $contrasena, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->conexion;
    }
}
