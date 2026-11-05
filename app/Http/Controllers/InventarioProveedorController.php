<?php

namespace App\Http\Controllers;

use App\Models\InventarioProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventarioProveedorController extends Controller
{
    /**
     * Retorna todas las relaciones o filtra por id_proveedor o id_inventario.
     */
    public function index(Request $request): JsonResponse
    {
        $query = InventarioProveedor::with(['proveedor', 'inventario']);

        if ($request->has('id_proveedor')) {
            $query->where('id_proveedor', $request->id_proveedor);
        }

        if ($request->has('id_inventario')) {
            $query->where('id_inventario', $request->id_inventario);
        }

        $items = $query->get();

        return $this->successResponse($items, 'Relaciones inventario proveedor obtenidas correctamente');
    }

    /**
     * Consulta una relación específica por su ID.
     */
    public function show($id): JsonResponse
    {
        $item = InventarioProveedor::with(['proveedor', 'inventario'])->find($id);
        if (!$item) {
            return $this->errorResponse('Relación no encontrada', 404);
        }

        return $this->successResponse($item, 'Relación obtenida correctamente');
    }

    /**
     * Crea una nueva homologación usando la secuencia de ApiModel.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_proveedor' => 'required|integer|exists:proveedores,id_proveedor',
            'id_inventario' => 'required|integer|exists:inventario,id_inventario',
            'codigo_proveedor' => 'required|string|max:100',
            'ultimo_monto_compra' => 'nullable|numeric|min:0',
        ]);

        $item = InventarioProveedor::create([
            'id_proveedor' => $request->id_proveedor,
            'id_inventario' => $request->id_inventario,
            'codigo_proveedor' => trim($request->codigo_proveedor),
            'ultimo_monto_compra' => $request->filled('ultimo_monto_compra') ? (float) $request->ultimo_monto_compra : 0.00,
        ]);

        return $this->successResponse($item->load(['proveedor', 'inventario']), 'Homologación registrada correctamente', 201);
    }

    /**
     * Actualiza el código de proveedor y/o último monto de compra de un ítem existente.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'codigo_proveedor' => 'sometimes|required|string|max:100',
            'ultimo_monto_compra' => 'nullable|numeric|min:0',
        ]);

        $item = InventarioProveedor::find($id);
        if (!$item) {
            return $this->errorResponse('Relación no encontrada', 404);
        }

        $dataToUpdate = [];
        if ($request->has('codigo_proveedor')) {
            $dataToUpdate['codigo_proveedor'] = trim($request->codigo_proveedor);
        }
        if ($request->has('ultimo_monto_compra')) {
            $dataToUpdate['ultimo_monto_compra'] = $request->filled('ultimo_monto_compra') ? (float) $request->ultimo_monto_compra : 0.00;
        }

        $item->update($dataToUpdate);

        return $this->successResponse($item->load(['proveedor', 'inventario']), 'Código de proveedor actualizado correctamente');
    }

    /**
     * Elimina una relación de inventario proveedor.
     */
    public function destroy($id): JsonResponse
    {
        $item = InventarioProveedor::find($id);
        if (!$item) {
            return $this->errorResponse('Relación no encontrada', 404);
        }

        $item->delete();

        return $this->successResponse($item, 'Relación eliminada correctamente');
    }
}
