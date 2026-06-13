<?php

declare(strict_types=1);

class StockController
{
    public function __construct(private readonly MovimientoStockModel $movimientos)
    {
    }

    public function registrar(array $datos, array $usuario): never
    {
        $errores = Validador::movimiento($datos);
        if ($errores !== []) {
            throw new ApiException('Los datos enviados no son válidos.', 422, $errores);
        }

        $movimiento = $this->movimientos->registrar(
            (int) $datos['producto_id'],
            (int) $usuario['id'],
            (int) $datos['cantidad'],
            strtoupper((string) $datos['tipo_movimiento']),
            isset($datos['motivo']) ? (string) $datos['motivo'] : null
        );

        Respuesta::json([
            'mensaje' => 'Movimiento de stock registrado correctamente.',
            'data' => $movimiento,
        ], 201);
    }

    public function listar(array $query): never
    {
        $pagina = max(1, (int) ($query['page'] ?? $query['pagina'] ?? 1));
        $limite = min(100, max(1, (int) ($query['limit'] ?? $query['limite'] ?? 20)));
        $filtro = trim((string) ($query['filter'] ?? $query['buscar'] ?? ''));
        Respuesta::json($this->movimientos->listar($pagina, $limite, $filtro));
    }

    public function stockBajo(): never
    {
        Respuesta::json(['data' => $this->movimientos->stockBajo()]);
    }

    public function proximosVencer(array $query): never
    {
        $dias = min(365, max(1, (int) ($query['days'] ?? 30)));
        Respuesta::json(['data' => $this->movimientos->proximosVencer($dias), 'days' => $dias]);
    }

    public function resumen(): never
    {
        Respuesta::json(['data' => $this->movimientos->resumen()]);
    }
}
