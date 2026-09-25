<?php

namespace App\Http\Controllers;

use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function index(): JsonResponse
    {
        $items = InventarioService::getAll();
        return $this->successResponse(
            $items,
            $items->isEmpty() ? 'No se encontraron items en inventario' : 'Inventario obtenido correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_modelo' => 'required|integer|exists:modelos,id_modelo',
            'sku' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|in:insumo,compresor,valvula,equipo',
            'condicion' => 'required|in:nuevo,usado,NA',
            'cantidad_total' => 'nullable|integer',
            'cantidad_propia' => 'nullable|integer',
            'cantidad_cliente' => 'nullable|integer',
            'stock_minimo' => 'nullable|integer',
            'monto_compra_min' => 'nullable|numeric',
            'monto_compra_prom' => 'nullable|numeric',
            'monto_compra_max' => 'nullable|numeric',
            'monto_venta_min' => 'nullable|numeric',
            'monto_venta_prom' => 'nullable|numeric',
            'monto_venta_max' => 'nullable|numeric',
            'monto_reparacion_min' => 'nullable|numeric',
            'monto_reparacion_prom' => 'nullable|numeric',
            'monto_reparacion_max' => 'nullable|numeric',
            'ultimo_monto_compra' => 'nullable|numeric',
            'monto_venta_unitario' => 'nullable|numeric',
            'porcentaje_iva' => 'nullable|numeric',
            'porcentaje_ganancia' => 'nullable|numeric',
        ]);

        $data = $request->all();

        $item = InventarioService::create($data);
        if (!$item) {
            return $this->errorResponse('Item de inventario no creado', 404);
        }

        return $this->successResponse($item, 'Item de inventario creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $item = InventarioService::getOne($id);
        if (!$item) {
            return $this->errorResponse('Item de inventario no encontrado', 404);
        }

        return $this->successResponse($item, 'Item de inventario obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_modelo' => 'integer|exists:modelos,id_modelo',
            'sku' => 'string|max:100',
            'nombre' => 'string|max:100',
            'tipo' => 'in:insumo,compresor,valvula,equipo',
            'condicion' => 'in:nuevo,usado,NA',
            'cantidad_total' => 'integer',
            'cantidad_propia' => 'integer',
            'cantidad_cliente' => 'integer',
            'stock_minimo' => 'integer',
            'monto_compra_min' => 'numeric',
            'monto_compra_prom' => 'numeric',
            'monto_compra_max' => 'numeric',
            'monto_venta_min' => 'numeric',
            'monto_venta_prom' => 'numeric',
            'monto_venta_max' => 'numeric',
            'monto_reparacion_min' => 'numeric',
            'monto_reparacion_prom' => 'numeric',
            'monto_reparacion_max' => 'numeric',
            'ultimo_monto_compra' => 'numeric',
            'monto_venta_unitario' => 'numeric',
            'porcentaje_iva' => 'numeric',
            'porcentaje_ganancia' => 'numeric',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_modelo') &&
            !$request->has('sku') &&
            !$request->has('nombre') &&
            !$request->has('tipo') &&
            !$request->has('condicion') &&
            !$request->has('cantidad_total') &&
            !$request->has('cantidad_propia') &&
            !$request->has('cantidad_cliente') &&
            !$request->has('stock_minimo') &&
            !$request->has('monto_compra_min') &&
            !$request->has('monto_compra_prom') &&
            !$request->has('monto_compra_max') &&
            !$request->has('monto_venta_min') &&
            !$request->has('monto_venta_prom') &&
            !$request->has('monto_venta_max') &&
            !$request->has('monto_reparacion_min') &&
            !$request->has('monto_reparacion_prom') &&
            !$request->has('monto_reparacion_max') &&
            !$request->has('ultimo_monto_compra') &&
            !$request->has('monto_venta_unitario') &&
            !$request->has('porcentaje_iva') &&
            !$request->has('porcentaje_ganancia')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $item = InventarioService::update($id, $data);
        if (!$item) {
            return $this->errorResponse('Item de inventario no encontrado', 404);
        }

        return $this->successResponse($item, 'Item de inventario actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $item = InventarioService::delete($id);
        if (!$item) {
            return $this->errorResponse('Item de inventario no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($item, 'Item de inventario eliminado correctamente');
    }
}
