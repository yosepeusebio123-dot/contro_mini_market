<?php

declare(strict_types=1);

class ProductoController
{
    public function __construct(
        private readonly ProductoModel $productos,
        private readonly CategoriaModel $categorias
    ) {
    }

    public function listar(array $query): never
    {
        $pagina = max(1, (int) ($query['page'] ?? $query['pagina'] ?? 1));
        $limite = min(100, max(1, (int) ($query['limit'] ?? $query['limite'] ?? 10)));
        $categoriaId = isset($query['category_id']) && $query['category_id'] !== ''
            ? max(1, (int) $query['category_id'])
            : null;
        $dias = isset($query['expiring_days']) && $query['expiring_days'] !== ''
            ? min(365, max(1, (int) $query['expiring_days']))
            : null;

        $filtros = [
            'page' => $pagina,
            'limit' => $limite,
            'filter' => trim((string) ($query['filter'] ?? $query['buscar'] ?? '')),
            'category_id' => $categoriaId,
            'low_stock' => filter_var($query['low_stock'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'expiring_days' => $dias,
        ];

        Respuesta::json($this->productos->listar($filtros));
    }

    public function mostrar(int $id): never
    {
        $producto = $this->productos->buscarPorId($id);
        if (!$producto) {
            throw new ApiException('Producto no encontrado.', 404);
        }
        Respuesta::json(['data' => $producto]);
    }

    public function crear(array $datos): never
    {
        $errores = Validador::producto($datos);
        if ($errores !== []) {
            throw new ApiException('Los datos enviados no son válidos.', 422, $errores);
        }

        if (!$this->categorias->buscarPorId((int) $datos['categoria_id'])) {
            throw new ApiException('La categoría indicada no existe.', 422, ['categoria_id' => 'Categoría inválida.']);
        }

        try {
            $producto = $this->productos->crear($datos);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new ApiException('Ya existe un producto con ese código de barras.', 409);
            }
            throw $e;
        }

        Respuesta::json(['mensaje' => 'Producto creado correctamente.', 'data' => $producto], 201);
    }

    public function actualizar(int $id, array $datos): never
    {
        $existente = $this->productos->buscarPorId($id);
        if (!$existente) {
            throw new ApiException('Producto no encontrado.', 404);
        }

        $datos['stock_actual'] = (int) $existente['stock_actual'];
        $errores = Validador::producto($datos);
        if ($errores !== []) {
            throw new ApiException('Los datos enviados no son válidos.', 422, $errores);
        }

        if (!$this->categorias->buscarPorId((int) $datos['categoria_id'])) {
            throw new ApiException('La categoría indicada no existe.', 422, ['categoria_id' => 'Categoría inválida.']);
        }

        try {
            $producto = $this->productos->actualizar($id, $datos);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new ApiException('Ya existe un producto con ese código de barras.', 409);
            }
            throw $e;
        }

        if (!$producto) {
            throw new ApiException('Producto no encontrado.', 404);
        }

        Respuesta::json(['mensaje' => 'Producto actualizado correctamente.', 'data' => $producto]);
    }

    public function eliminar(int $id): never
    {
        if (!$this->productos->eliminar($id)) {
            throw new ApiException('Producto no encontrado.', 404);
        }
        Respuesta::json(['mensaje' => 'Producto eliminado correctamente.']);
    }
}
