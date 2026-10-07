<?php

namespace App\Http\Controllers;

use App\Services\PagoCompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class PagoCompraController
 *
 * Controlador RESTful encargado de gestionar y actualizar las cuotas o pagos realizados
 * a los proveedores por compras registradas previamente.
 */
class PagoCompraController extends Controller
{
    /**
     * Retorna el listado completo de pagos de compras registrados.
     *
     * Lógica:
     * 1. Consulta la colección de pagos mediante PagoCompraService::getAll().
     * 2. Retorna respuesta estándar JSON con código 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "pagos_compras";
     *
     * @return JsonResponse Lista de pagos a proveedores.
     */
    public function index(): JsonResponse
    {
        $pagos = PagoCompraService::getAll();
        return $this->successResponse(
            $pagos,
            $pagos->isEmpty() ? 'No se encontraron pagos a compras' : 'Pagos a compras obtenidos correctamente'
        );
    }

    /**
     * Bloquea la creación directa de pagos huérfanos.
     *
     * Lógica:
     * 1. Los pagos a proveedores nacen acoplados al registro inicial de la compra en CompraController::store().
     * 2. Retorna error 405 Method Not Allowed.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // La creación de pagos de compra se gestiona exclusivamente al registrar la compra
        return $this->errorResponse('Los pagos de compra se generan automáticamente al registrar la compra', 405);
    }

    /**
     * Consulta y entrega los datos de un pago a compra específico por su ID.
     *
     * Lógica:
     * 1. Busca el pago mediante PagoCompraService::getOne($id).
     * 2. Si no existe devuelve 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "pagos_compras" WHERE "id_pago_compra" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $pago = PagoCompraService::getOne($id);
        if (!$pago) {
            return $this->errorResponse('Pago no encontrado', 404);
        }

        return $this->successResponse($pago, 'Pago obtenido correctamente');
    }

    /**
     * Actualiza la información o estado de abono de un pago a proveedor.
     *
     * Lógica:
     * 1. Valida los campos de pago (monto, fechas, método, referencia, comprobante, estado).
     * 2. Comprueba que al menos un campo haya sido provisto para actualizar.
     * 3. Invoca PagoCompraService::update($id, $data) que actualiza el pago y sincroniza
     *    el saldo 'monto_pendiente' y el estado de la compra padre.
     * 4. Retorna el pago actualizado con 200 OK.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "pagos_compras" WHERE "id_pago_compra" = :id LIMIT 1;
     * -- UPDATE "pagos_compras" SET "estado" = 'realizado', "fecha_pago" = NOW() WHERE "id_pago_compra" = :id;
     * -- UPDATE "compras" SET "monto_pendiente" = ... WHERE "id_compra" = :id_compra;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_admin' => 'nullable|integer|exists:admins,id_admin',
            'monto_a_pagar' => 'nullable|numeric|min:0.01',
            'porcentaje_monto_total' => 'nullable|numeric',
            'fecha_pago' => 'nullable|date',
            'fecha_pago_acordada' => 'nullable|date',
            'metodo_pago' => 'nullable|in:transferencia,efectivo,cheque',
            'num_referencia' => 'nullable|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
            'estado' => 'nullable|in:pendiente,realizado',
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
            !$request->has('image') &&
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

    /**
     * Bloquea la eliminación aislada de cuotas de compras.
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        // No se permite eliminar pagos de compras aislados
        return $this->errorResponse('No está permitido eliminar pagos de compras individualmente', 405);
    }
}