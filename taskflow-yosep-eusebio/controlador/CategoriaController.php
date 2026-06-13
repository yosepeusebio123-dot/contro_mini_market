<?php

declare(strict_types=1);

class CategoriaController
{
    public function __construct(private readonly CategoriaModel $categorias)
    {
    }

    public function listar(array $query): never
    {
        [$pagina, $limite] = $this->paginacion($query);
        $filtro = trim((string) ($query['filter'] ?? $query['buscar'] ?? ''));
        Respuesta::json($this->categorias->listar($pagina, $limite, $filtro));
    }

    public function mostrar(int $id): never
    {
        $categoria = $this->categorias->buscarPorId($id);
        if (!$categoria) {
            throw new ApiException('Categoría no encontrada.', 404);
        }
        Respuesta::json(['data' => $categoria]);
    }

    public function crear(array $datos): never
    {
        $errores = Validador::categoria($datos);
        if ($errores !== []) {
            throw new ApiException('Los datos enviados no son válidos.', 422, $errores);
        }

        try {
            $categoria = $this->categorias->crear(
                (string) $datos['nombre'],
                isset($datos['descripcion']) ? (string) $datos['descripcion'] : null
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new ApiException('Ya existe una categoría con ese nombre.', 409);
            }
            throw $e;
        }

        Respuesta::json(['mensaje' => 'Categoría creada correctamente.', 'data' => $categoria], 201);
    }

    public function actualizar(int $id, array $datos): never
    {
        $errores = Validador::categoria($datos);
        if ($errores !== []) {
            throw new ApiException('Los datos enviados no son válidos.', 422, $errores);
        }

        try {
            $categoria = $this->categorias->actualizar(
                $id,
                (string) $datos['nombre'],
                isset($datos['descripcion']) ? (string) $datos['descripcion'] : null
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                throw new ApiException('Ya existe una categoría con ese nombre.', 409);
            }
            throw $e;
        }

        if (!$categoria) {
            throw new ApiException('Categoría no encontrada.', 404);
        }

        Respuesta::json(['mensaje' => 'Categoría actualizada correctamente.', 'data' => $categoria]);
    }

    public function eliminar(int $id): never
    {
        if ($this->categorias->tieneProductosActivos($id)) {
            throw new ApiException('No se puede eliminar una categoría con productos activos.', 409);
        }

        if (!$this->categorias->eliminar($id)) {
            throw new ApiException('Categoría no encontrada.', 404);
        }

        Respuesta::json(['mensaje' => 'Categoría eliminada correctamente.']);
    }

    private function paginacion(array $query): array
    {
        $pagina = max(1, (int) ($query['page'] ?? $query['pagina'] ?? 1));
        $limite = (int) ($query['limit'] ?? $query['limite'] ?? 10);
        $limite = min(100, max(1, $limite));
        return [$pagina, $limite];
    }
}
