<?php

declare(strict_types=1);

class Validador
{
    public static function registroUsuario(array $datos): array
    {
        $errores = [];
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $correo = trim((string) ($datos['correo'] ?? ''));
        $contrasena = (string) ($datos['contrasena'] ?? '');

        if (strlen($nombre) < 3 || strlen($nombre) > 100) {
            $errores['nombre'] = 'Debe tener entre 3 y 100 caracteres.';
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150) {
            $errores['correo'] = 'Debe ser un correo electrónico válido.';
        }

        if (
            strlen($contrasena) < 8 ||
            !preg_match('/[A-Z]/', $contrasena) ||
            !preg_match('/[a-z]/', $contrasena) ||
            !preg_match('/\d/', $contrasena)
        ) {
            $errores['contrasena'] = 'Debe tener 8 caracteres como mínimo, una mayúscula, una minúscula y un número.';
        }

        return $errores;
    }

    public static function login(array $datos): array
    {
        $errores = [];

        if (!filter_var(trim((string) ($datos['correo'] ?? '')), FILTER_VALIDATE_EMAIL)) {
            $errores['correo'] = 'Debe ser un correo electrónico válido.';
        }

        if ((string) ($datos['contrasena'] ?? '') === '') {
            $errores['contrasena'] = 'La contraseña es obligatoria.';
        }

        return $errores;
    }

    public static function categoria(array $datos): array
    {
        $errores = [];
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));

        if (strlen($nombre) < 2 || strlen($nombre) > 100) {
            $errores['nombre'] = 'Debe tener entre 2 y 100 caracteres.';
        }

        if (strlen($descripcion) > 255) {
            $errores['descripcion'] = 'No puede superar los 255 caracteres.';
        }

        return $errores;
    }

    public static function producto(array $datos): array
    {
        $errores = [];
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $codigo = trim((string) ($datos['codigo_barras'] ?? ''));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $unidad = trim((string) ($datos['unidad_medida'] ?? ''));

        if ((int) ($datos['categoria_id'] ?? 0) < 1) {
            $errores['categoria_id'] = 'Debe seleccionar una categoría válida.';
        }
        if (strlen($nombre) < 2 || strlen($nombre) > 150) {
            $errores['nombre'] = 'Debe tener entre 2 y 150 caracteres.';
        }
        if (!preg_match('/^[A-Za-z0-9._-]{4,100}$/', $codigo)) {
            $errores['codigo_barras'] = 'Debe tener entre 4 y 100 caracteres alfanuméricos.';
        }
        if (strlen($descripcion) > 500) {
            $errores['descripcion'] = 'No puede superar los 500 caracteres.';
        }
        if (strlen($unidad) < 2 || strlen($unidad) > 30) {
            $errores['unidad_medida'] = 'Debe tener entre 2 y 30 caracteres.';
        }
        if (!isset($datos['precio']) || !is_numeric($datos['precio']) || (float) $datos['precio'] < 0) {
            $errores['precio'] = 'Debe ser un número mayor o igual a cero.';
        }
        if (!isset($datos['stock_actual']) || filter_var($datos['stock_actual'], FILTER_VALIDATE_INT) === false || (int) $datos['stock_actual'] < 0) {
            $errores['stock_actual'] = 'Debe ser un número entero mayor o igual a cero.';
        }
        if (!isset($datos['stock_minimo']) || filter_var($datos['stock_minimo'], FILTER_VALIDATE_INT) === false || (int) $datos['stock_minimo'] < 0) {
            $errores['stock_minimo'] = 'Debe ser un número entero mayor o igual a cero.';
        }

        $fecha = trim((string) ($datos['fecha_vencimiento'] ?? ''));
        if ($fecha !== '' && !self::fechaValida($fecha)) {
            $errores['fecha_vencimiento'] = 'Debe tener el formato YYYY-MM-DD y ser una fecha real.';
        }

        return $errores;
    }

    private static function fechaValida(string $fecha): bool
    {
        $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $objeto !== false && $objeto->format('Y-m-d') === $fecha;
    }

    public static function movimiento(array $datos): array
    {
        $errores = [];
        $productoId = filter_var($datos['producto_id'] ?? null, FILTER_VALIDATE_INT);
        $cantidad = filter_var($datos['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $tipo = strtoupper(trim((string) ($datos['tipo_movimiento'] ?? '')));
        $motivo = trim((string) ($datos['motivo'] ?? ''));

        if ($productoId === false || $productoId < 1) {
            $errores['producto_id'] = 'Debe ser un identificador de producto válido.';
        }
        if ($cantidad === false || $cantidad < 1) {
            $errores['cantidad'] = 'Debe ser un entero mayor que cero.';
        }
        if (!in_array($tipo, ['ENTRADA', 'SALIDA'], true)) {
            $errores['tipo_movimiento'] = 'Debe ser ENTRADA o SALIDA.';
        }
        if (strlen($motivo) > 255) {
            $errores['motivo'] = 'No puede superar los 255 caracteres.';
        }

        return $errores;
    }
}
