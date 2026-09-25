<?php

namespace App\Http\Controllers;

use App\Services\CompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompraController extends Controller
{
    public function index(): JsonResponse
    {
        $compras = CompraService::getAll();
        return $this->successResponse(
            $compras,
            $compras->isEmpty() ? 'No se encontraron compras' : 'Compras obtenidas correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Cabecera
            'id_proveedor' => 'nullable|integer|exists:proveedores,id_proveedor',
            'id_admin' => 'nullable|integer|exists:admins,id_admin',
            'num_factura_boleta' => 'nullable|string|max:100',
            'fecha_compra' => 'nullable|date',
            'tipo_pago' => 'nullable|in:contado,credito',
            'estado' => 'nullable|in:por_pagar,pagada_parcial,pagada',
            'monto_total_gravado' => 'nullable|numeric',
            'monto_total_exento' => 'nullable|numeric',
            'monto_total_iva' => 'nullable|numeric',
            'monto_total' => 'nullable|numeric',
            'monto_pendiente' => 'nullable|numeric',
            'num_pagos' => 'nullable|integer',

            // Líneas de detalles
            'detalles' => 'required|array|min:1',
            'detalles.*.id_inventario' => 'required|integer|exists:inventario,id_inventario',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.costo_unitario' => 'required|numeric',
            'detalles.*.monto_total_linea_sin_iva' => 'nullable|numeric',
            'detalles.*.porcentaje_iva' => 'nullable|numeric',
            'detalles.*.monto_iva' => 'nullable|numeric',
            'detalles.*.monto_total_linea_con_iva' => 'nullable|numeric',

            // Seriales opcionales por detalle para equipos o repuestos
            'detalles.*.seriales' => 'nullable|array',
            'detalles.*.seriales.*.serial' => 'nullable|string|max:100',
            'detalles.*.seriales.*.nombre' => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        $compra = CompraService::create($data);
        if (!$compra) {
            return $this->errorResponse('Compra no creada', 404);
        }

        return $this->successResponse($compra, 'Compra creada y procesada correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $compra = CompraService::getOne($id);
        if (!$compra) {
            return $this->errorResponse('Compra no encontrada', 404);
        }

        return $this->successResponse($compra, 'Compra obtenida correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'estado' => 'in:por_pagar,pagada_parcial,pagada',
            'monto_pendiente' => 'numeric|min:0',
        ]);

        // Validar que al menos uno de los dos campos permitidos sea enviado
        if (!$request->has('estado') && !$request->has('monto_pendiente')) {
            return $this->errorResponse('Debe enviar estado o monto_pendiente para actualizar la compra', 400);
        }

        $data = $request->all();

        $compra = CompraService::update($id, $data);
        if (!$compra) {
            return $this->errorResponse('Compra no encontrada', 404);
        }

        return $this->successResponse($compra, 'Compra actualizada correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $compra = CompraService::delete($id);
        if (!$compra) {
            return $this->errorResponse('Compra no encontrada o no se pudo eliminar', 404);
        }

        return $this->successResponse($compra, 'Compra eliminada correctamente');
    }
}
