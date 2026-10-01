<?php

namespace App\Http\Controllers;

use App\Services\AsignacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsignacionController extends Controller
{
    public function index(): JsonResponse
    {
        $asignaciones = AsignacionService::getAll();
        return $this->successResponse(
            $asignaciones,
            $asignaciones->isEmpty() ? 'No se encontraron asignaciones' : 'Asignaciones obtenidas correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_reparacion_servicio_taller' => 'required|integer|exists:reparaciones_servicios_taller,id_reparacion_servicio_taller',
            'id_personal'                  => 'required|integer|exists:personal,id_personal',
        ]);

        $data = $request->all();

        $asignacion = AsignacionService::create($data);
        if (!$asignacion) {
            return $this->errorResponse('Asignación no creada', 404);
        }

        return $this->successResponse($asignacion, 'Personal asignado al servicio correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $asignacion = AsignacionService::getOne($id);
        if (!$asignacion) {
            return $this->errorResponse('Asignación no encontrada', 404);
        }

        return $this->successResponse($asignacion, 'Asignación obtenida correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_reparacion_servicio_taller' => 'integer|exists:reparaciones_servicios_taller,id_reparacion_servicio_taller',
            'id_personal'                  => 'integer|exists:personal,id_personal',
        ]);

        if (!$request->has('id_reparacion_servicio_taller') && !$request->has('id_personal')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $asignacion = AsignacionService::update($id, $data);
        if (!$asignacion) {
            return $this->errorResponse('Asignación no encontrada', 404);
        }

        return $this->successResponse($asignacion, 'Asignación actualizada correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $asignacion = AsignacionService::delete($id);
        if (!$asignacion) {
            return $this->errorResponse('Asignación no encontrada o no se pudo eliminar', 404);
        }

        return $this->successResponse(null, 'Asignación eliminada exitosamente');
    }
}