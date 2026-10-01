<?php

namespace App\Http\Controllers;

use App\Services\ReparacionInsumoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReparacionInsumoController extends Controller
{
    public function index(): JsonResponse
    {
        $insumos = ReparacionInsumoService::getAll();
        return $this->successResponse(
            $insumos,
            $insumos->isEmpty() ? 'No se encontraron insumos de reparaciones' : 'Insumos de reparaciones obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_reparacion'                  => 'required|integer|exists:reparaciones,id_reparacion',
            'id_inventario'                  => 'required|integer|exists:inventario,id_inventario',
            'id_admin'                       => 'nullable|integer|exists:admins,id_admin',
            'cantidad'                       => 'required|integer|min:1',
            'costo_unitario'                 => 'nullable|numeric|min:0',
            'monto_total_linea'              => 'nullable|numeric|min:0',
            'costo_unitario_con_ganancia'    => 'nullable|numeric|min:0',
            'monto_total_linea_con_ganancia' => 'nullable|numeric|min:0',
            'porcentaje_iva'                 => 'nullable|numeric|min:0',
            'monto_iva'                      => 'nullable|numeric|min:0',
        ]);

        $data = $request->all();

        $insumo = ReparacionInsumoService::create($data);
        if (!$insumo) {
            return $this->errorResponse('Insumo no creado', 404);
        }

        return $this->successResponse($insumo, 'Insumo agregado a la reparación correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $insumo = ReparacionInsumoService::getOne($id);
        if (!$insumo) {
            return $this->errorResponse('Insumo no encontrado', 404);
        }

        return $this->successResponse($insumo, 'Insumo obtenido correctamente');
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
        ]);

        if (
            !$request->has('cantidad') &&
            !$request->has('costo_unitario') &&
            !$request->has('monto_total_linea') &&
            !$request->has('costo_unitario_con_ganancia') &&
            !$request->has('monto_total_linea_con_ganancia') &&
            !$request->has('porcentaje_iva') &&
            !$request->has('monto_iva')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $insumo = ReparacionInsumoService::update($id, $data);
        if (!$insumo) {
            return $this->errorResponse('Insumo no encontrado', 404);
        }

        return $this->successResponse($insumo, 'Insumo actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $insumo = ReparacionInsumoService::delete($id);
        if (!$insumo) {
            return $this->errorResponse('Insumo no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse(null, 'Insumo eliminado y stock devuelto exitosamente');
    }
}