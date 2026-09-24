<?php

namespace App\Http\Controllers;

use App\Services\TipoOrdenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoOrdenController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoOrdenService::getAll();
        return $this->successResponse(
            $tipos,
            $tipos->isEmpty() ? 'No se encontraron tipos de órdenes' : 'Tipos de órdenes obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:50',
            'descripcion' => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        $tipo = TipoOrdenService::create($data);
        if (!$tipo) {
            return $this->errorResponse('Tipo de orden no creado', 404);
        }
        return $this->successResponse($tipo, 'Tipo de orden creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $tipo = TipoOrdenService::getOne($id);
        if (!$tipo) {
            return $this->errorResponse('Tipo de orden no encontrado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de orden obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:50',
            'descripcion' => 'nullable|string|max:100',
        ]);

        // validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('descripcion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $tipo = TipoOrdenService::update($id, $data);
        if (!$tipo) {
            return $this->errorResponse('Tipo de orden no encontrado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de orden actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $tipo = TipoOrdenService::delete($id);
        if (!$tipo) {
            return $this->errorResponse('Tipo de orden no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($tipo, 'Tipo de orden eliminado correctamente');
    }
}