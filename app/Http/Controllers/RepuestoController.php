<?php

namespace App\Http\Controllers;

use App\Services\RepuestoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepuestoController extends Controller
{
    public function index(): JsonResponse
    {
        $repuestos = RepuestoService::getAll();
        return $this->successResponse(
            $repuestos,
            $repuestos->isEmpty() ? 'No se encontraron repuestos' : 'Repuestos obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_inventario' => 'required|integer|exists:inventario,id_inventario',
            'id_detalle_compra' => 'nullable|integer|exists:detalles_compras,id_detalle_compra',
            'serial' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'estado' => 'required|in:nuevo,reparado,pendiente_reparacion',
            'propietario' => 'nullable|boolean',
            'id_orden_entrada' => 'nullable|integer|exists:ordenes,id_orden',
            'id_orden_salida' => 'nullable|integer|exists:ordenes,id_orden',
            'is_deleted' => 'nullable|boolean',
            'costo_adquisicion' => 'nullable|numeric',
            'costo_reparacion_base' => 'nullable|numeric',
            'costo_total' => 'nullable|numeric',
            'costo_reparacion_con_ganancia' => 'nullable|numeric',
            'monto_venta_real' => 'nullable|numeric',
            'utilidad' => 'nullable|numeric',
        ]);

        $data = $request->all();

        $repuesto = RepuestoService::create($data);
        if (!$repuesto) {
            return $this->errorResponse('Repuesto no creado', 404);
        }

        return $this->successResponse($repuesto, 'Repuesto creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $repuesto = RepuestoService::getOne($id);
        if (!$repuesto) {
            return $this->errorResponse('Repuesto no encontrado', 404);
        }

        return $this->successResponse($repuesto, 'Repuesto obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_inventario' => 'integer|exists:inventario,id_inventario',
            'id_detalle_compra' => 'nullable|integer|exists:detalles_compras,id_detalle_compra',
            'serial' => 'string|max:100',
            'nombre' => 'string|max:100',
            'estado' => 'in:nuevo,reparado,pendiente_reparacion',
            'propietario' => 'boolean',
            'id_orden_entrada' => 'nullable|integer|exists:ordenes,id_orden',
            'id_orden_salida' => 'nullable|integer|exists:ordenes,id_orden',
            'is_deleted' => 'boolean',
            'costo_adquisicion' => 'numeric',
            'costo_reparacion_base' => 'numeric',
            'costo_total' => 'numeric',
            'costo_reparacion_con_ganancia' => 'numeric',
            'monto_venta_real' => 'numeric',
            'utilidad' => 'numeric',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_inventario') &&
            !$request->has('id_detalle_compra') &&
            !$request->has('serial') &&
            !$request->has('nombre') &&
            !$request->has('estado') &&
            !$request->has('propietario') &&
            !$request->has('id_orden_entrada') &&
            !$request->has('id_orden_salida') &&
            !$request->has('is_deleted') &&
            !$request->has('costo_adquisicion') &&
            !$request->has('costo_reparacion_base') &&
            !$request->has('costo_total') &&
            !$request->has('costo_reparacion_con_ganancia') &&
            !$request->has('monto_venta_real') &&
            !$request->has('utilidad')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $repuesto = RepuestoService::update($id, $data);
        if (!$repuesto) {
            return $this->errorResponse('Repuesto no encontrado', 404);
        }

        return $this->successResponse($repuesto, 'Repuesto actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $repuesto = RepuestoService::delete($id);
        if (!$repuesto) {
            return $this->errorResponse('Repuesto no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($repuesto, 'Repuesto eliminado correctamente');
    }
}
