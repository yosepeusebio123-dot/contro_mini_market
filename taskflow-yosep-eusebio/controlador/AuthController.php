<?php

declare(strict_types=1);

class AuthController
{
    public function __construct(
        private readonly UsuarioModel $usuarios,
        private readonly JwtServicio $jwt
    ) {
    }

    public function registrar(array $datos): never
    {
        $errores = Validador::registroUsuario($datos);
        if ($errores !== []) {
            throw new ApiException('Los datos enviados no son válidos.', 422, $errores);
        }

        try {
            $usuario = $this->usuarios->crear(
                (string) $datos['nombre'],
                (string) $datos['correo'],
                (string) $datos['contrasena']
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new ApiException('El correo electrónico ya está registrado.', 409);
            }
            throw $e;
        }

        Respuesta::json([
            'mensaje' => 'Usuario registrado correctamente.',
            'usuario' => $usuario,
        ], 201);
    }

    public function login(array $datos): never
    {
        $errores = Validador::login($datos);
        if ($errores !== []) {
            throw new ApiException('Los datos enviados no son válidos.', 422, $errores);
        }

        $usuario = $this->usuarios->buscarPorCorreo((string) $datos['correo']);

        if (!$usuario || !$usuario['activo'] || !password_verify((string) $datos['contrasena'], $usuario['contrasena'])) {
            throw new ApiException('Correo o contraseña incorrectos.', 401);
        }

        unset($usuario['contrasena']);

        Respuesta::json([
            'mensaje' => 'Inicio de sesión correcto.',
            'token' => $this->jwt->generar($usuario),
            'expires_in' => (int) Entorno::obtener('JWT_TTL', '14400'),
            'usuario' => $usuario,
        ]);
    }
}
