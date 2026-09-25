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
            'tipo_pago' => 'required|in:contado,credito',
            'estado' => 'nullable|in:por_pagar,pagada_parcial,pagada',
            'monto_total_gravado' => 'nullable|numeric',
            'monto_total_exento' => 'nullable|numeric',
            'monto_total_iva' => 'nullable|numeric',
            'monto_total' => 'nullable|numeric',
            'monto_pendiente' => 'nullable|numeric',
            'num_pagos' => 'nullable|integer|min:1',

            // Líneas de detalles
            'detalles' => 'required|array|min:1',
            'detalles.*.id_inventario' => 'required|integer|exists:inventario,id_inventario',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.costo_unitario' => 'required|numeric',
            'detalles.*.monto_total_linea_sin_iva' => 'nullable|numeric',
            'detalles.*.porcentaje_iva' => 'nullable|numeric',
            'detalles.*.monto_iva' => 'nullable|numeric',
            'detalles.*.monto_total_linea_con_iva' => 'nullable|numeric',

            // Seriales opcionales por detalle
            'detalles.*.seriales' => 'nullable|array',
            'detalles.*.seriales.*.serial' => 'nullable|string|max:100',
            'detalles.*.seriales.*.nombre' => 'nullable|string|max:100',

            // Arreglo de pagos (1 a N elementos)
            'pagos' => 'required|array|min:1',
            'pagos.*.monto_a_pagar' => 'required|numeric|min:0.01',
            'pagos.*.porcentaje_monto_total' => 'nullable|numeric',
            'pagos.*.fecha_pago_acordada' => 'required|date',
            'pagos.*.fecha_pago' => 'nullable|date',
            'pagos.*.metodo_pago' => 'nullable|in:transferencia,efectivo,cheque',
            'pagos.*.num_referencia' => 'nullable|string|max:50',
            'pagos.*.comprobante' => 'nullable|string|max:100',
            'pagos.*.estado' => 'required|in:pendiente,realizado',
        ]);

        $data = $request->all();

        $compra = CompraService::create($data);
        if (!$compra) {
            return $this->errorResponse('Compra no creada', 404);
        }

        return $this->successResponse($compra, 'Compra creada exitosamente', 201);
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
        // Solo permitimos modificar estado y monto_pendiente
        if (!$request->hasAny(['estado', 'monto_pendiente'])) {
            return $this->errorResponse(
                'Debe proporcionar al menos un campo válido para actualizar (estado, monto_pendiente)',
                400
            );
        }

        $request->validate([
            'estado' => 'sometimes|required|in:por_pagar,pagada_parcial,pagada',
            'monto_pendiente' => 'sometimes|required|numeric|min:0',
        ]);

        $compra = CompraService::update($id, $request->only(['estado', 'monto_pendiente']));
        if (!$compra) {
            return $this->errorResponse('Compra no encontrada', 404);
        }

        return $this->successResponse($compra, 'Compra actualizada exitosamente');
    }

    public function destroy($id): JsonResponse
    {
        $compra = CompraService::delete($id);
        if (!$compra) {
            return $this->errorResponse('Compra no encontrada', 404);
        }

        return $this->successResponse(null, 'Compra eliminada exitosamente');
    }
}
