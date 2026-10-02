<?php

namespace App\Http\Controllers;

use App\Services\ReparacionService;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReparacionController extends Controller
{
    public function index(): JsonResponse
    {
        $reparaciones = ReparacionService::getAll();
        return $this->successResponse(
            $reparaciones,
            $reparaciones->isEmpty() ? 'No se encontraron reparaciones' : 'Reparaciones obtenidas correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Cabecera
            'id_repuesto'                  => 'required|integer|exists:repuestos,id_repuesto',
            'id_orden'                     => 'nullable|integer|exists:ordenes,id_orden',
            'estado'                       => 'nullable|in:pendiente,en_proceso,finalizada',
            'fecha_inicio'                 => 'nullable|date',
            'id_admin_fecha_inicio'        => 'nullable|integer|exists:admins,id_admin',
            'fecha_fin'                    => 'nullable|date',
            'id_admin_fecha_fin'           => 'nullable|integer|exists:admins,id_admin',
            'costo_servicios_base'         => 'nullable|numeric|min:0',
            'costo_insumos_base'           => 'nullable|numeric|min:0',
            'costo_servicios_con_ganancia' => 'nullable|numeric|min:0',
            'costo_insumos_con_ganancia'   => 'nullable|numeric|min:0',
            'costo_total'                  => 'nullable|numeric|min:0',
            'costo_total_con_ganancia'     => 'nullable|numeric|min:0',
            'monto_total_iva'              => 'nullable|numeric|min:0',

            // Arreglo opcional de insumos
            'insumos'                                  => 'nullable|array',
            'insumos.*.id_inventario'                  => 'required_with:insumos|integer|exists:inventario,id_inventario',
            'insumos.*.cantidad'                       => 'required_with:insumos|integer|min:1',
            'insumos.*.costo_unitario'                 => 'nullable|numeric|min:0',
            'insumos.*.monto_total_linea'              => 'nullable|numeric|min:0',
            'insumos.*.costo_unitario_con_ganancia'    => 'nullable|numeric|min:0',
            'insumos.*.monto_total_linea_con_ganancia' => 'nullable|numeric|min:0',
            'insumos.*.porcentaje_iva'                 => 'nullable|numeric|min:0',
            'insumos.*.monto_iva'                      => 'nullable|numeric|min:0',

            // Arreglo opcional de servicios
            'servicios'                                  => 'nullable|array',
            'servicios.*.id_servicio_taller'             => 'required_with:servicios|integer|exists:servicios_taller,id_servicio_taller',
            'servicios.*.cantidad'                       => 'required_with:servicios|integer|min:1',
            'servicios.*.costo_unitario'                 => 'nullable|numeric|min:0',
            'servicios.*.monto_total_linea'              => 'nullable|numeric|min:0',
            'servicios.*.costo_unitario_con_ganancia'    => 'nullable|numeric|min:0',
            'servicios.*.monto_total_linea_con_ganancia' => 'nullable|numeric|min:0',
            'servicios.*.porcentaje_iva'                 => 'nullable|numeric|min:0',
            'servicios.*.monto_iva'                      => 'nullable|numeric|min:0',
            'servicios.*.estado'                         => 'nullable|in:pendiente_asignacion,en_espera,en_proceso,finalizado',
        ]);

        $data = $request->all();

        if (empty($data['id_admin'])) {
            $userId = auth()->id();
            $admin = $userId ? Admin::where('id_user', $userId)->first() : null;
            $data['id_admin'] = $admin ? $admin->id_admin : 1;
        }

        $reparacion = ReparacionService::create($data);
        if (!$reparacion) {
            return $this->errorResponse('Reparación no creada', 404);
        }

        return $this->successResponse($reparacion, 'Reparación creada exitosamente', 201);
    }

    public function show($id): JsonResponse
    {
        $reparacion = ReparacionService::getOne($id);
        if (!$reparacion) {
            return $this->errorResponse('Reparación no encontrada', 404);
        }

        return $this->successResponse($reparacion, 'Reparación obtenida correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            // Cabecera
            'id_repuesto'                  => 'integer|exists:repuestos,id_repuesto',
            'id_orden'                     => 'integer|exists:ordenes,id_orden',
            'estado'                       => 'in:pendiente,en_proceso,finalizada',
            'fecha_inicio'                 => 'nullable|date',
            'id_admin_fecha_inicio'        => 'integer|exists:admins,id_admin',
            'fecha_fin'                    => 'nullable|date',
            'id_admin_fecha_fin'           => 'integer|exists:admins,id_admin',
            'costo_servicios_base'         => 'numeric|min:0',
            'costo_insumos_base'           => 'numeric|min:0',
            'costo_servicios_con_ganancia' => 'numeric|min:0',
            'costo_insumos_con_ganancia'   => 'numeric|min:0',
            'costo_total'                  => 'numeric|min:0',
            'costo_total_con_ganancia'     => 'numeric|min:0',
            'monto_total_iva'              => 'numeric|min:0',

            // Arreglo opcional de insumos (para sincronización unificada)
            'insumos'                                  => 'nullable|array',
            'insumos.*.id_reparacion_insumo'           => 'nullable|integer|exists:reparaciones_insumos,id_reparacion_insumo',
            'insumos.*.id_inventario'                  => 'required_with:insumos|integer|exists:inventario,id_inventario',
            'insumos.*.cantidad'                       => 'required_with:insumos|integer|min:1',
            'insumos.*.costo_unitario'                 => 'nullable|numeric|min:0',
            'insumos.*.costo_unitario_con_ganancia'    => 'nullable|numeric|min:0',
            'insumos.*.porcentaje_iva'                 => 'nullable|numeric|min:0',

            // Arreglo opcional de servicios (para sincronización unificada)
            'servicios'                                  => 'nullable|array',
            'servicios.*.id_reparacion_servicio_taller'  => 'nullable|integer|exists:reparaciones_servicios_taller,id_reparacion_servicio_taller',
            'servicios.*.id_servicio_taller'             => 'required_with:servicios|integer|exists:servicios_taller,id_servicio_taller',
            'servicios.*.cantidad'                       => 'required_with:servicios|integer|min:1',
            'servicios.*.costo_unitario'                 => 'nullable|numeric|min:0',
            'servicios.*.costo_unitario_con_ganancia'    => 'nullable|numeric|min:0',
            'servicios.*.porcentaje_iva'                 => 'nullable|numeric|min:0',
            'servicios.*.estado'                         => 'nullable|in:pendiente_asignacion,en_espera,en_proceso,finalizado',
        ]);

        if (
            !$request->hasAny([
                'id_repuesto',
                'id_orden',
                'estado',
                'fecha_inicio',
                'id_admin_fecha_inicio',
                'fecha_fin',
                'id_admin_fecha_fin',
                'costo_servicios_base',
                'costo_insumos_base',
                'costo_servicios_con_ganancia',
                'costo_insumos_con_ganancia',
                'costo_total',
                'costo_total_con_ganancia',
                'monto_total_iva',
                'insumos',
                'servicios'
            ])
        ) {
            return $this->errorResponse('Debe proporcionar al menos un campo válido para actualizar', 400);
        }

        $data = $request->all();

        $reparacion = ReparacionService::update($id, $data);
        if (!$reparacion) {
            return $this->errorResponse('Reparación no encontrada', 404);
        }

        return $this->successResponse($reparacion, 'Reparación actualizada exitosamente');
    }

    public function destroy($id): JsonResponse
    {
        $reparacion = ReparacionService::delete($id);
        if (!$reparacion) {
            return $this->errorResponse('Reparación no encontrada', 404);
        }

        return $this->successResponse(null, 'Reparación eliminada exitosamente');
    }
}