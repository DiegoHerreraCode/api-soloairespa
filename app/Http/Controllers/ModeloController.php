<?php

namespace App\Http\Controllers;

use App\Services\ModeloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModeloController extends Controller
{
    public function index(): JsonResponse
    {
        $modelos = ModeloService::getAll();
        return $this->successResponse(
            $modelos,
            $modelos->isEmpty() ? 'No se encontraron modelos' : 'Modelos obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_marca' => 'required|integer|exists:marcas,id_marca',
            'nombre' => 'required|string|max:100',
            'descripcion' => 'required|string|max:100',
        ]);

        $data = $request->all();

        $modelo = ModeloService::create($data);
        if (!$modelo) {
            return $this->errorResponse('Modelo no creado', 404);
        }

        return $this->successResponse($modelo, 'Modelo creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $modelo = ModeloService::getOne($id);
        if (!$modelo) {
            return $this->errorResponse('Modelo no encontrado', 404);
        }

        return $this->successResponse($modelo, 'Modelo obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_marca' => 'integer|exists:marcas,id_marca',
            'nombre' => 'string|max:100',
            'descripcion' => 'string|max:100',
        ]);

        // Validar que al menos un campo sea modificado
        if (!$request->has('id_marca') && !$request->has('nombre') && !$request->has('descripcion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $modelo = ModeloService::update($id, $data);
        if (!$modelo) {
            return $this->errorResponse('Modelo no encontrado', 404);
        }

        return $this->successResponse($modelo, 'Modelo actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $modelo = ModeloService::delete($id);
        if (!$modelo) {
            return $this->errorResponse('Modelo no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($modelo, 'Modelo eliminado correctamente');
    }
}
