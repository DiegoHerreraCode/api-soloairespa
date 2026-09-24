<?php

namespace App\Http\Controllers;

use App\Services\TipoServicioTallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoServicioTallerController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoServicioTallerService::getAll();
        return $this->successResponse(
            $tipos,
            $tipos->isEmpty() ? 'No se encontraron tipos de servicios de taller' : 'Tipos de servicios obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        $tipo = TipoServicioTallerService::create($data);

        if (!$tipo) {
            return $this->errorResponse('Tipo de servicio no creado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de servicio creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $tipo = TipoServicioTallerService::getOne($id);
        if (!$tipo) {
            return $this->errorResponse('Tipo de servicio no encontrado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de servicio obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'descripcion' => 'string|max:100',
        ]);

        // validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('descripcion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $tipo = TipoServicioTallerService::update($id, $data);

        if (!$tipo) {
            return $this->errorResponse('Tipo de servicio no encontrado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de servicio actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $deleted = TipoServicioTallerService::delete($id);
        if (!$deleted) {
            return $this->errorResponse('Tipo de servicio no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse(null, 'Tipo de servicio eliminado correctamente');
    }
}