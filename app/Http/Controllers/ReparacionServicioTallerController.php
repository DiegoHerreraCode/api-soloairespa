<?php

namespace App\Http\Controllers;

use App\Services\ReparacionServicioTallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReparacionServicioTallerController extends Controller
{
    public function index(): JsonResponse
    {
        $servicios = ReparacionServicioTallerService::getAll();
        return $this->successResponse(
            $servicios,
            $servicios->isEmpty() ? 'No se encontraron servicios de reparaciones' : 'Servicios de reparaciones obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_reparacion'                  => 'required|integer|exists:reparaciones,id_reparacion',
            'id_servicio_taller'             => 'required|integer|exists:servicios_taller,id_servicio_taller',
            'id_admin'                       => 'nullable|integer|exists:admins,id_admin',
            'cantidad'                       => 'required|integer|min:1',
            'costo_unitario'                 => 'nullable|numeric|min:0',
            'monto_total_linea'              => 'nullable|numeric|min:0',
            'costo_unitario_con_ganancia'    => 'nullable|numeric|min:0',
            'monto_total_linea_con_ganancia' => 'nullable|numeric|min:0',
            'porcentaje_iva'                 => 'nullable|numeric|min:0',
            'monto_iva'                      => 'nullable|numeric|min:0',
            'estado'                         => 'nullable|in:pendiente_asignacion,en_espera,en_proceso,finalizado',
            'fecha_inicio'                   => 'nullable|date',
            'fecha_fin'                      => 'nullable|date',
        ]);

        $data = $request->all();

        $servicio = ReparacionServicioTallerService::create($data);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no creado', 404);
        }

        return $this->successResponse($servicio, 'Servicio agregado a la reparación correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $servicio = ReparacionServicioTallerService::getOne($id);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'cantidad'                       => 'integer|min:1',
            'costo_unitario'                 => 'numeric|min:0',
            'monto_total_linea'              => 'numeric|min:0',
            'costo_unitario_con_ganancia'    => 'numeric|min:0',
            'monto_total_linea_con_ganancia' => 'numeric|min:0',
            'porcentaje_iva'                 => 'numeric|min:0',
            'monto_iva'                      => 'numeric|min:0',
            'estado'                         => 'in:pendiente_asignacion,en_espera,en_proceso,finalizado',
            'fecha_inicio'                   => 'nullable|date',
            'fecha_fin'                      => 'nullable|date',
        ]);

        if (
            !$request->has('cantidad') &&
            !$request->has('costo_unitario') &&
            !$request->has('monto_total_linea') &&
            !$request->has('costo_unitario_con_ganancia') &&
            !$request->has('monto_total_linea_con_ganancia') &&
            !$request->has('porcentaje_iva') &&
            !$request->has('monto_iva') &&
            !$request->has('estado') &&
            !$request->has('fecha_inicio') &&
            !$request->has('fecha_fin')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $servicio = ReparacionServicioTallerService::update($id, $data);
        if ($servicio === false) {
            return $this->errorResponse('Para poner un servicio en ejecución (en_proceso) debe tener al menos un mecánico asignado', 422);
        }
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $servicio = ReparacionServicioTallerService::delete($id);
        if ($servicio === false) {
            return $this->errorResponse('Solo se pueden eliminar servicios en estado pendiente_asignacion o en_espera', 422);
        }
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse(null, 'Servicio de taller eliminado exitosamente');
    }
}