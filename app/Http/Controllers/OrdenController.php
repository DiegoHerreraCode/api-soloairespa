<?php

namespace App\Http\Controllers;

use App\Services\OrdenService;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdenController extends Controller
{
    public function index(): JsonResponse
    {
        $ordenes = OrdenService::getAll();
        return $this->successResponse(
            $ordenes,
            $ordenes->isEmpty() ? 'No se encontraron órdenes' : 'Órdenes obtenidas correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Cabecera
            'id_cliente'               => 'required|integer|exists:clientes,id_cliente',
            'id_tipo_orden'            => 'required|integer|exists:tipos_ordenes,id_tipo_orden',
            'id_admin'                 => 'nullable|integer|exists:admins,id_admin',
            'fecha_creacion'           => 'nullable|date',
            'monto_total_gravado'      => 'nullable|numeric',
            'monto_total_exento'       => 'nullable|numeric',
            'monto_total_iva'          => 'nullable|numeric',
            'monto_total'              => 'nullable|numeric',
            'monto_pendiente'          => 'nullable|numeric',
            'fecha_entrega_reparacion' => 'nullable|date',
            'estado_operativo'         => 'nullable|in:en_espera,en_proceso,finalizada,anulada',
            'estado_administrativo'    => 'nullable|in:pendiente_pago,pagada',

                        // Líneas de detalles (para Venta y Recambio)
            'detalles'                                     => 'nullable|array',
            'detalles.*.id_inventario_insumo_saliente'     => 'nullable|integer|exists:inventario,id_inventario',
            'detalles.*.id_inventario_repuesto_saliente'   => 'nullable|integer|exists:inventario,id_inventario',
            'detalles.*.id_repuesto_saliente'              => 'nullable|integer|exists:repuestos,id_repuesto',
            'detalles.*.repuestos_salientes'               => 'nullable|array',
            'detalles.*.repuestos_salientes.*.id_repuesto' => 'required_with:detalles.*.repuestos_salientes|integer|exists:repuestos,id_repuesto',
            'detalles.*.repuestos_salientes.*.precio_unitario' => 'nullable|numeric|min:0',
            'detalles.*.id_inventario_repuesto_entrante'   => 'nullable|integer|exists:inventario,id_inventario',
            'detalles.*.id_repuesto_entrante'              => 'nullable|integer|exists:repuestos,id_repuesto',
            'detalles.*.cantidad'                          => 'nullable|integer|min:1',
            'detalles.*.precio_unitario'                   => 'nullable|numeric|min:0',
            'detalles.*.monto_tasacion'                    => 'nullable|numeric|min:0',
            'detalles.*.porcentaje_iva'                    => 'nullable|numeric|min:0',
            'detalles.*.serial_entrante'                   => 'nullable|string|max:100',
            'detalles.*.nombre_entrante'                   => 'nullable|string|max:100',

            // Repuestos que ingresan (para Órdenes de Reparación directa)
            'repuestos_entrantes'                 => 'nullable|array',
            'repuestos_entrantes.*.id_inventario' => 'required_with:repuestos_entrantes|integer|exists:inventario,id_inventario',
            'repuestos_entrantes.*.serial'        => 'nullable|string|max:100',
            'repuestos_entrantes.*.nombre'        => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        if (empty($data['id_admin'])) {
            $userId = auth()->id();
            $admin = $userId ? Admin::where('id_user', $userId)->first() : null;
            $data['id_admin'] = $admin ? $admin->id_admin : 1;
        }

        $orden = OrdenService::create($data);
        if (!$orden) {
            return $this->errorResponse('Orden no creada', 404);
        }

        return $this->successResponse($orden, 'Orden creada correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $orden = OrdenService::getOne($id);
        if (!$orden) {
            return $this->errorResponse('Orden no encontrada', 404);
        }

        return $this->successResponse($orden, 'Orden obtenida correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_cliente'               => 'integer|exists:clientes,id_cliente',
            'id_tipo_orden'            => 'integer|exists:tipos_ordenes,id_tipo_orden',
            'id_admin'                 => 'integer|exists:admins,id_admin',
            'monto_total_gravado'      => 'numeric',
            'monto_total_exento'       => 'numeric',
            'monto_total_iva'          => 'numeric',
            'monto_total'              => 'numeric',
            'monto_pendiente'          => 'numeric',
            'fecha_entrega_reparacion' => 'nullable|date',
            'estado_operativo'         => 'in:en_espera,en_proceso,finalizada,anulada',
            'estado_administrativo'    => 'in:pendiente_pago,pagada',
            'id_admin_anulacion'       => 'nullable|integer|exists:admins,id_admin',
            'motivo_anulacion'         => 'nullable|string|max:250',
        ]);

        if (
            !$request->has('id_cliente') &&
            !$request->has('id_tipo_orden') &&
            !$request->has('id_admin') &&
            !$request->has('monto_total_gravado') &&
            !$request->has('monto_total_exento') &&
            !$request->has('monto_total_iva') &&
            !$request->has('monto_total') &&
            !$request->has('monto_pendiente') &&
            !$request->has('fecha_entrega_reparacion') &&
            !$request->has('estado_operativo') &&
            !$request->has('estado_administrativo') &&
            !$request->has('id_admin_anulacion') &&
            !$request->has('motivo_anulacion')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $orden = OrdenService::update($id, $data);
        if (!$orden) {
            return $this->errorResponse('Orden no encontrada', 404);
        }

        return $this->successResponse($orden, 'Orden actualizada correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $orden = OrdenService::delete($id);
        if (!$orden) {
            return $this->errorResponse('Orden no encontrada o no se pudo eliminar', 404);
        }

        return $this->successResponse($orden, 'Orden eliminada correctamente');
    }
    public function anular(Request $request, $id): JsonResponse
    {
        $request->validate([
            'motivo_anulacion'   => 'required|string|max:250',
            'insumos_devueltos'  => 'nullable|array',
            'insumos_devueltos.*.id_reparacion_insumo' => 'required_with:insumos_devueltos|integer|exists:reparaciones_insumos,id_reparacion_insumo',
            'insumos_devueltos.*.cantidad_no_gastada'  => 'required_with:insumos_devueltos|integer|min:0',
        ]);

        $data = $request->all();

        // Resolver id_admin_anulacion automáticamente desde el token autenticado
        $userId = auth()->id();
        $admin = $userId ? Admin::where('id_user', $userId)->first() : null;
        $data['id_admin_anulacion'] = $admin ? $admin->id_admin : ($data['id_admin_anulacion'] ?? 1);

        $resultado = OrdenService::anular($id, $data);

        if (is_string($resultado)) {
            return $this->errorResponse($resultado, 422);
        }

        if (!$resultado) {
            return $this->errorResponse('Orden no encontrada o no se pudo anular', 404);
        }

        return $this->successResponse($resultado, 'Orden anulada correctamente');
    }
}
