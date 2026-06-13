<?php

declare(strict_types=1);

class JwtServicio
{
    public function __construct(
        private readonly string $secreto,
        private readonly int $ttl,
        private readonly string $emisor = 'freshstock-api'
    ) {
        if (strlen($this->secreto) < 32) {
            throw new RuntimeException('JWT_SECRET debe tener al menos 32 caracteres.');
        }
    }

    public function generar(array $usuario): string
    {
        $ahora = time();
        $cabecera = ['typ' => 'JWT', 'alg' => 'HS256'];
        $carga = [
            'iss' => $this->emisor,
            'sub' => (string) $usuario['id'],
            'email' => $usuario['correo'],
            'name' => $usuario['nombre'],
            'role' => $usuario['rol'],
            'iat' => $ahora,
            'exp' => $ahora + $this->ttl,
        ];

        $cabeceraCodificada = $this->base64UrlEncode((string) json_encode($cabecera));
        $cargaCodificada = $this->base64UrlEncode((string) json_encode($carga, JSON_UNESCAPED_UNICODE));
        $firma = hash_hmac('sha256', "{$cabeceraCodificada}.{$cargaCodificada}", $this->secreto, true);

        return "{$cabeceraCodificada}.{$cargaCodificada}.{$this->base64UrlEncode($firma)}";
    }

    public function validar(string $token): array
    {
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            throw new ApiException('Token de acceso inválido.', 401);
        }

        [$cabeceraCodificada, $cargaCodificada, $firmaCodificada] = $partes;
        $cabecera = json_decode($this->base64UrlDecode($cabeceraCodificada), true);
        $carga = json_decode($this->base64UrlDecode($cargaCodificada), true);

        if (!is_array($cabecera) || ($cabecera['alg'] ?? null) !== 'HS256' || !is_array($carga)) {
            throw new ApiException('Token de acceso inválido.', 401);
        }

        $firmaEsperada = hash_hmac('sha256', "{$cabeceraCodificada}.{$cargaCodificada}", $this->secreto, true);
        $firmaRecibida = $this->base64UrlDecode($firmaCodificada);

        if (!hash_equals($firmaEsperada, $firmaRecibida)) {
            throw new ApiException('Token de acceso inválido.', 401);
        }

        if (($carga['iss'] ?? null) !== $this->emisor || !isset($carga['sub'], $carga['exp'])) {
            throw new ApiException('Token de acceso inválido.', 401);
        }

        if ((int) $carga['exp'] <= time()) {
            throw new ApiException('El token de acceso ha expirado.', 401);
        }

        return $carga;
    }

    private function base64UrlEncode(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $valor): string
    {
        $relleno = strlen($valor) % 4;
        if ($relleno > 0) {
            $valor .= str_repeat('=', 4 - $relleno);
        }

        $decodificado = base64_decode(strtr($valor, '-_', '+/'), true);

        if ($decodificado === false) {
            throw new ApiException('Token de acceso inválido.', 401);
        }

        return $decodificado;
    }
}
