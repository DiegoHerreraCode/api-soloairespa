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
        ]);

        $data = $request->all();

        $compra = CompraService::create($data);
        if (!$compra) {
            return $this->errorResponse('Compra no creada', 404);
        }

        return $this->successResponse($compra, 'Compra creada correctamente', 201);
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
            'id_proveedor' => 'integer|exists:proveedores,id_proveedor',
            'id_admin' => 'integer|exists:admins,id_admin',
            'num_factura_boleta' => 'string|max:100',
            'fecha_compra' => 'date',
            'tipo_pago' => 'in:contado,credito',
            'estado' => 'in:por_pagar,pagada_parcial,pagada',
            'monto_total_gravado' => 'numeric',
            'monto_total_exento' => 'numeric',
            'monto_total_iva' => 'numeric',
            'monto_total' => 'numeric',
            'monto_pendiente' => 'numeric',
            'num_pagos' => 'integer',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_proveedor') &&
            !$request->has('id_admin') &&
            !$request->has('num_factura_boleta') &&
            !$request->has('fecha_compra') &&
            !$request->has('tipo_pago') &&
            !$request->has('estado') &&
            !$request->has('monto_total_gravado') &&
            !$request->has('monto_total_exento') &&
            !$request->has('monto_total_iva') &&
            !$request->has('monto_total') &&
            !$request->has('monto_pendiente') &&
            !$request->has('num_pagos')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
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
