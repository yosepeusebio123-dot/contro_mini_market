<?php

declare(strict_types=1);

require_once __DIR__ . '/util/ApiException.php';
require_once __DIR__ . '/util/Respuesta.php';
require_once __DIR__ . '/util/Entorno.php';
require_once __DIR__ . '/util/JwtServicio.php';
require_once __DIR__ . '/util/Validador.php';
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/modelo/usuario.php';
require_once __DIR__ . '/modelo/categoria.php';
require_once __DIR__ . '/modelo/producto.php';
require_once __DIR__ . '/modelo/movimientoStock.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controlador/AuthController.php';
require_once __DIR__ . '/controlador/CategoriaController.php';
require_once __DIR__ . '/controlador/ProductoController.php';
require_once __DIR__ . '/controlador/StockController.php';

header_remove('X-Powered-By');
header('Access-Control-Allow-Origin: ' . Entorno::obtener('CORS_ORIGIN', '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

set_exception_handler(function (Throwable $error): never {
    if ($error instanceof ApiException) {
        $respuesta = ['error' => $error->getMessage()];
        if ($error->errors() !== []) {
            $respuesta['campos'] = $error->errors();
        }
        Respuesta::json($respuesta, $error->status());
    }

    error_log(sprintf(
        '[%s] %s en %s:%d',
        date('c'),
        $error->getMessage(),
        $error->getFile(),
        $error->getLine()
    ));

    Respuesta::json(['error' => 'Ocurrió un error interno en el servidor.'], 500);
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

function leerJson(): array
{
    $contenido = file_get_contents('php://input');

    if ($contenido === false || trim($contenido) === '') {
        return [];
    }

    $tipoContenido = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (!str_contains($tipoContenido, 'application/json')) {
        throw new ApiException('El Content-Type debe ser application/json.', 415);
    }

    $datos = json_decode($contenido, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        throw new ApiException('El cuerpo de la solicitud contiene un JSON inválido.', 400);
    }

    return $datos;
}

function idSegmento(array $segmentos, int $indice): int
{
    $id = filter_var($segmentos[$indice] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new ApiException('El identificador de la ruta no es válido.', 400);
    }
    return $id;
}

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segmentos = $ruta === '' ? [] : explode('/', $ruta);
$datos = in_array($metodo, ['POST', 'PUT', 'PATCH'], true) ? leerJson() : [];

$database = new Database();
$db = $database->getConnection();
$usuarios = new UsuarioModel($db);
$categorias = new CategoriaModel($db);
$productos = new ProductoModel($db);
$movimientos = new MovimientoStockModel($db);
$jwt = new JwtServicio(
    Entorno::secretoDesdeArchivo('JWT_SECRET_FILE'),
    (int) Entorno::obtener('JWT_TTL', '14400')
);
$authMiddleware = new AuthMiddleware($jwt, $usuarios);
$authController = new AuthController($usuarios, $jwt);
$categoriaController = new CategoriaController($categorias);
$productoController = new ProductoController($productos, $categorias);
$stockController = new StockController($movimientos);

if ($ruta === 'health' && $metodo === 'GET') {
    $db->query('SELECT 1');
    Respuesta::json([
        'status' => 'ok',
        'service' => Entorno::obtener('APP_NAME', 'FreshStock'),
        'database' => 'connected',
        'timestamp' => date(DATE_ATOM),
    ]);
}

if (($ruta === 'auth/register' || $ruta === 'api/usuarios/registrar') && $metodo === 'POST') {
    $authController->registrar($datos);
}

if (($ruta === 'auth/login' || $ruta === 'api/usuarios/login') && $metodo === 'POST') {
    $authController->login($datos);
}

if (($segmentos[0] ?? null) !== 'api') {
    throw new ApiException('Ruta no encontrada.', 404);
}

$usuarioActual = $authMiddleware->autenticar();

if (($segmentos[1] ?? null) === 'categories') {
    if (count($segmentos) === 2 && $metodo === 'GET') {
        $categoriaController->listar($_GET);
    }
    if (count($segmentos) === 2 && $metodo === 'POST') {
        $authMiddleware->exigirRol($usuarioActual, ['admin']);
        $categoriaController->crear($datos);
    }
    if (count($segmentos) === 3 && $metodo === 'GET') {
        $categoriaController->mostrar(idSegmento($segmentos, 2));
    }
    if (count($segmentos) === 3 && $metodo === 'PUT') {
        $authMiddleware->exigirRol($usuarioActual, ['admin']);
        $categoriaController->actualizar(idSegmento($segmentos, 2), $datos);
    }
    if (count($segmentos) === 3 && $metodo === 'DELETE') {
        $authMiddleware->exigirRol($usuarioActual, ['admin']);
        $categoriaController->eliminar(idSegmento($segmentos, 2));
    }
}

if (($segmentos[1] ?? null) === 'products') {
    if (count($segmentos) === 2 && $metodo === 'GET') {
        $productoController->listar($_GET);
    }
    if (count($segmentos) === 2 && $metodo === 'POST') {
        $productoController->crear($datos);
    }
    if (count($segmentos) === 3 && $metodo === 'GET') {
        $productoController->mostrar(idSegmento($segmentos, 2));
    }
    if (count($segmentos) === 3 && $metodo === 'PUT') {
        $productoController->actualizar(idSegmento($segmentos, 2), $datos);
    }
    if (count($segmentos) === 3 && $metodo === 'DELETE') {
        $authMiddleware->exigirRol($usuarioActual, ['admin']);
        $productoController->eliminar(idSegmento($segmentos, 2));
    }
}

if (($segmentos[1] ?? null) === 'stock' && ($segmentos[2] ?? null) === 'movements') {
    if ($metodo === 'GET') {
        $stockController->listar($_GET);
    }
    if ($metodo === 'POST') {
        $stockController->registrar($datos, $usuarioActual);
    }
}

if ($ruta === 'api/alerts/low-stock' && $metodo === 'GET') {
    $stockController->stockBajo();
}

if ($ruta === 'api/alerts/expiring' && $metodo === 'GET') {
    $stockController->proximosVencer($_GET);
}

if ($ruta === 'api/dashboard/summary' && $metodo === 'GET') {
    $stockController->resumen();
}

if ($ruta === 'api/productos/listar' && $metodo === 'GET') {
    $productoController->listar($_GET);
}

if ($ruta === 'api/productos/crear' && $metodo === 'POST') {
    $productoController->crear($datos);
}

if ($ruta === 'api/productos/mover' && $metodo === 'POST') {
    $stockController->registrar($datos, $usuarioActual);
}

if ($ruta === 'api/productos/historial' && $metodo === 'GET') {
    $stockController->listar($_GET);
}

throw new ApiException('Ruta no encontrada.', 404);
