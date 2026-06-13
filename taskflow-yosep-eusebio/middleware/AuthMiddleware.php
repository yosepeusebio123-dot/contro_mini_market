<?php

declare(strict_types=1);

class AuthMiddleware
{
    public function __construct(
        private readonly JwtServicio $jwt,
        private readonly UsuarioModel $usuarios
    ) {
    }

    public function autenticar(): array
    {
        $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($cabecera), $coincidencias)) {
            throw new ApiException('Se requiere un token Bearer.', 401);
        }

        $carga = $this->jwt->validar(trim($coincidencias[1]));
        $usuario = $this->usuarios->buscarActivoPorId((int) $carga['sub']);

        if (!$usuario) {
            throw new ApiException('El usuario del token no está disponible.', 401);
        }

        return $usuario;
    }

    public function exigirRol(array $usuario, array $roles): void
    {
        if (!in_array($usuario['rol'], $roles, true)) {
            throw new ApiException('No tienes permisos para realizar esta acción.', 403);
        }
    }
}
