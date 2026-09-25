<?php

namespace App\Http\Controllers;

use App\Services\PagoClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagoClienteController extends Controller
{
    public function index(): JsonResponse
    {
        $pagos = PagoClienteService::getAll();
        return $this->successResponse(
            $pagos,
            $pagos->isEmpty() ? 'No se encontraron pagos de clientes' : 'Pagos de clientes obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_cliente' => 'required|integer|exists:clientes,id_cliente',
            'id_orden' => 'required|integer|exists:ordenes,id_orden',
            'monto' => 'required|numeric|min:0.01',
            'fecha_pago' => 'nullable|date',
            'metodo_pago' => 'required|in:transferencia,efectivo,cheque',
            'num_referencia' => 'nullable|string|max:50',
            'comprobante' => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        $pago = PagoClienteService::create($data);
        if (!$pago) {
            return $this->errorResponse('Pago no creado', 404);
        }

        return $this->successResponse($pago, 'Pago registrado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $pago = PagoClienteService::getOne($id);
        if (!$pago) {
            return $this->errorResponse('Pago no encontrado', 404);
        }

        return $this->successResponse($pago, 'Pago obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_cliente' => 'integer|exists:clientes,id_cliente',
            'id_orden' => 'integer|exists:ordenes,id_orden',
            'monto' => 'numeric|min:0.01',
            'fecha_pago' => 'date',
            'metodo_pago' => 'in:transferencia,efectivo,cheque',
            'num_referencia' => 'string|max:50',
            'comprobante' => 'string|max:100',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_cliente') &&
            !$request->has('id_orden') &&
            !$request->has('monto') &&
            !$request->has('fecha_pago') &&
            !$request->has('metodo_pago') &&
            !$request->has('num_referencia') &&
            !$request->has('comprobante')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $pago = PagoClienteService::update($id, $data);
        if (!$pago) {
            return $this->errorResponse('Pago no encontrado', 404);
        }

        return $this->successResponse($pago, 'Pago actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $pago = PagoClienteService::delete($id);
        if (!$pago) {
            return $this->errorResponse('Pago no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($pago, 'Pago eliminado correctamente');
    }
}
