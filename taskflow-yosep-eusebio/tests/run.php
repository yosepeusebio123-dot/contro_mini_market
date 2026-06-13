<?php

declare(strict_types=1);

require_once __DIR__ . '/../util/ApiException.php';
require_once __DIR__ . '/../util/JwtServicio.php';
require_once __DIR__ . '/../util/Validador.php';

$pruebas = [];
$fallos = 0;

function prueba(string $nombre, callable $funcion): void
{
    global $pruebas, $fallos;

    try {
        $funcion();
        $pruebas[] = "OK  {$nombre}";
    } catch (Throwable $error) {
        $fallos++;
        $pruebas[] = "FAIL {$nombre}: {$error->getMessage()}";
    }
}

function afirmar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$usuario = [
    'id' => 7,
    'nombre' => 'Usuario Prueba',
    'correo' => 'prueba@freshstock.local',
    'rol' => 'operador',
];
$secreto = str_repeat('s', 64);

prueba('JWT válido conserva identidad y rol', function () use ($usuario, $secreto): void {
    $jwt = new JwtServicio($secreto, 3600);
    $token = $jwt->generar($usuario);
    $carga = $jwt->validar($token);
    afirmar($carga['sub'] === '7', 'El sub del token no coincide.');
    afirmar($carga['role'] === 'operador', 'El rol del token no coincide.');
});

prueba('JWT alterado es rechazado', function () use ($usuario, $secreto): void {
    $jwt = new JwtServicio($secreto, 3600);
    $token = $jwt->generar($usuario);
    $tokenAlterado = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');

    try {
        $jwt->validar($tokenAlterado);
        throw new RuntimeException('El token alterado fue aceptado.');
    } catch (ApiException $error) {
        afirmar($error->status() === 401, 'Se esperaba un error 401.');
    }
});

prueba('JWT expirado es rechazado', function () use ($usuario, $secreto): void {
    $jwt = new JwtServicio($secreto, -1);
    $token = $jwt->generar($usuario);

    try {
        $jwt->validar($token);
        throw new RuntimeException('El token expirado fue aceptado.');
    } catch (ApiException $error) {
        afirmar($error->status() === 401, 'Se esperaba un error 401.');
    }
});

prueba('Producto con stock negativo no pasa validación', function (): void {
    $errores = Validador::producto([
        'categoria_id' => 1,
        'nombre' => 'Leche',
        'codigo_barras' => '7750001',
        'unidad_medida' => 'unidad',
        'precio' => 4.5,
        'stock_actual' => -1,
        'stock_minimo' => 2,
        'fecha_vencimiento' => '2027-01-01',
    ]);
    afirmar(isset($errores['stock_actual']), 'No se detectó el stock negativo.');
});

prueba('Movimiento válido no genera errores', function (): void {
    $errores = Validador::movimiento([
        'producto_id' => 1,
        'cantidad' => 5,
        'tipo_movimiento' => 'SALIDA',
        'motivo' => 'Venta',
    ]);
    afirmar($errores === [], 'El movimiento válido fue rechazado.');
});

echo implode(PHP_EOL, $pruebas) . PHP_EOL;
echo PHP_EOL . (count($pruebas) - $fallos) . '/' . count($pruebas) . " pruebas aprobadas." . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
