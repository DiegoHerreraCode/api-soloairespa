<?php

namespace App\Http\Controllers;

use App\Services\PagoCompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagoCompraController extends Controller
{
    public function index(): JsonResponse
    {
        $pagos = PagoCompraService::getAll();
        return $this->successResponse(
            $pagos,
            $pagos->isEmpty() ? 'No se encontraron pagos a compras' : 'Pagos a compras obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        // La creación de pagos de compra se gestiona exclusivamente al registrar la compra
        return $this->errorResponse('Los pagos de compra se generan automáticamente al registrar la compra', 405);
    }

    public function show($id): JsonResponse
    {
        $pago = PagoCompraService::getOne($id);
        if (!$pago) {
            return $this->errorResponse('Pago no encontrado', 404);
        }

        return $this->successResponse($pago, 'Pago obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_admin' => 'nullable|integer|exists:admins,id_admin',
            'monto_a_pagar' => 'numeric|min:0.01',
            'porcentaje_monto_total' => 'numeric',
            'fecha_pago' => 'nullable|date',
            'fecha_pago_acordada' => 'nullable|date',
            'metodo_pago' => 'nullable|in:transferencia,efectivo,cheque',
            'num_referencia' => 'nullable|string|max:50',
            'comprobante' => 'nullable|string|max:100',
            'estado' => 'in:pendiente,realizado',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_admin') &&
            !$request->has('monto_a_pagar') &&
            !$request->has('porcentaje_monto_total') &&
            !$request->has('fecha_pago') &&
            !$request->has('fecha_pago_acordada') &&
            !$request->has('metodo_pago') &&
            !$request->has('num_referencia') &&
            !$request->has('comprobante') &&
            !$request->has('estado')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $pago = PagoCompraService::update($id, $data);
        if (!$pago) {
            return $this->errorResponse('Pago no encontrado', 404);
        }

        return $this->successResponse($pago, 'Pago a compra actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        // No se permite eliminar pagos de compras aislados
        return $this->errorResponse('No está permitido eliminar pagos de compras individualmente', 405);
    }
}
