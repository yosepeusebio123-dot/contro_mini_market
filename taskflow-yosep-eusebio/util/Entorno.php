<?php

declare(strict_types=1);

class Entorno
{
    public static function obtener(string $clave, ?string $predeterminado = null): ?string
    {
        $valor = getenv($clave);
        return $valor === false || $valor === '' ? $predeterminado : $valor;
    }

    public static function secretoDesdeArchivo(string $variableArchivo): string
    {
        $ruta = self::obtener($variableArchivo);

        if ($ruta === null || !is_readable($ruta)) {
            throw new RuntimeException("No se pudo leer el secreto configurado en {$variableArchivo}.");
        }

        $secreto = trim((string) file_get_contents($ruta));

        if ($secreto === '') {
            throw new RuntimeException("El secreto configurado en {$variableArchivo} está vacío.");
        }

        return $secreto;
    }
}
