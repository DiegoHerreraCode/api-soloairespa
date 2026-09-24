<?php

namespace App\Http\Controllers;

use App\Services\ServicioTallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicioTallerController extends Controller
{
    public function index(): JsonResponse
    {
        $servicios = ServicioTallerService::getAll();
        return $this->successResponse(
            $servicios,
            $servicios->isEmpty() ? 'No se encontraron servicios de taller' : 'Servicios de taller obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_tipo_servicio_taller' => 'required|integer|exists:tipos_servicios_taller,id_tipo_servicio_taller',
            'nombre' => 'required|string|max:100',
            'descripcion' => 'required|string|max:200',
            'costo_base' => 'nullable|numeric',
            'porcentaje_iva' => 'nullable|numeric',
            'porcentaje_ganancia' => 'nullable|numeric',
        ]);

        $data = $request->all();

        $servicio = ServicioTallerService::create($data);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no creado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $servicio = ServicioTallerService::getOne($id);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_tipo_servicio_taller' => 'integer|exists:tipos_servicios_taller,id_tipo_servicio_taller',
            'nombre' => 'string|max:100',
            'descripcion' => 'string|max:200',
            'costo_base' => 'numeric',
            'porcentaje_iva' => 'numeric',
            'porcentaje_ganancia' => 'numeric',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_tipo_servicio_taller') &&
            !$request->has('nombre') &&
            !$request->has('descripcion') &&
            !$request->has('costo_base') &&
            !$request->has('porcentaje_iva') &&
            !$request->has('porcentaje_ganancia')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $servicio = ServicioTallerService::update($id, $data);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $servicio = ServicioTallerService::delete($id);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller eliminado correctamente');
    }
}
