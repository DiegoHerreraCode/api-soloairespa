<?php

namespace App\Http\Controllers;

use App\Services\CompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class CompraController
 *
 * Controlador RESTful encargado de la gestión de compras a proveedores.
 * Valida la cabecera de la factura/boleta, las líneas de detalle adquiridas,
 * los seriales ingresados y el plan o cronograma de pagos asociado.
 */
class CompraController extends Controller
{
    /**
     * Retorna todas las compras registradas con sus relaciones.
     *
     * Lógica:
     * 1. Consulta la colección completa mediante CompraService::getAll().
     * 2. Retorna respuesta estándar JSON con HTTP 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "compras";
     *
     * @return JsonResponse Lista de compras.
     */
    public function index(): JsonResponse
    {
        $compras = CompraService::getAll();
        return $this->successResponse(
            $compras,
            $compras->isEmpty() ? 'No se encontraron compras' : 'Compras obtenidas correctamente'
        );
    }

    /**
     * Registra una compra completa dentro de una transacción de base de datos.
     *
     * Lógica:
     * 1. Valida cabecera (proveedor, admin, factura, montos totales, tipo de pago contado/crédito).
     * 2. Valida arreglo de detalles (id_inventario, cantidad, costo_unitario) y seriales opcionales.
     * 3. Valida arreglo de pagos acordados (montos, fechas, estados).
     * 4. Envía toda la estructura a CompraService::create($data), que crea la compra, los detalles,
     *    los repuestos/equipos por serial, los pagos y recalcula existencias y métricas en inventario.
     * 5. Retorna la compra creada con HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- BEGIN;
     * -- INSERT INTO "compras" (...) VALUES (...);
     * -- INSERT INTO "detalles_compras" (...) VALUES (...);
     * -- INSERT INTO "repuestos" / "equipos" (...) VALUES (...);
     * -- INSERT INTO "pagos_compras" (...) VALUES (...);
     * -- Recálculo de inventario (UPDATE "inventario" ...);
     * -- COMMIT;
     *
     * @param Request $request
     * @return JsonResponse
     */
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
            'detalles.*.seriales.*.service_tag' => 'nullable|string|max:100',
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

    /**
     * Consulta y devuelve la información de una compra por su ID con sus detalles y pagos.
     *
     * Lógica:
     * 1. Busca la compra mediante CompraService::getOne($id).
     * 2. Si no existe retorna 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "compras" WHERE "id_compra" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $compra = CompraService::getOne($id);
        if (!$compra) {
            return $this->errorResponse('Compra no encontrada', 404);
        }

        return $this->successResponse($compra, 'Compra obtenida correctamente');
    }

    /**
     * Actualiza el estado administrativo o el saldo pendiente de una compra.
     *
     * Lógica:
     * 1. Verifica que se proporcione 'estado' o 'monto_pendiente'.
     * 2. Valida los valores recibidos.
     * 3. Invoca CompraService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "compras" WHERE "id_compra" = :id LIMIT 1;
     * -- UPDATE "compras" SET "estado" = '...', "monto_pendiente" = ... WHERE "id_compra" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
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

    /**
     * Elimina una compra y sus registros dependientes del sistema.
     *
     * Lógica:
     * 1. Llama a CompraService::delete($id).
     * 2. Si la compra no existe devuelve error 404.
     * 3. Retorna mensaje de confirmación de eliminación exitosa.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "compras" WHERE "id_compra" = :id LIMIT 1;
     * -- DELETE FROM "compras" WHERE "id_compra" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $compra = CompraService::delete($id);
        if (!$compra) {
            return $this->errorResponse('Compra no encontrada', 404);
        }

        return $this->successResponse(null, 'Compra eliminada exitosamente');
    }
}