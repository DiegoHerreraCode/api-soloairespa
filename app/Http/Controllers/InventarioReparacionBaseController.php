<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\InventarioReparacionInsumo;
use App\Models\InventarioReparacionServicio;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventarioReparacionBaseController extends Controller
{
    /**
     * Obtiene la configuración base de reparación para un ítem de inventario.
     */
    public function show($idInventario): JsonResponse
    {
        $item = Inventario::with(['reparacionInsumosBase.insumo', 'reparacionServiciosBase.servicioTaller'])->find($idInventario);

        if (!$item) {
            return $this->errorResponse('Ítem de inventario no encontrado', 404);
        }

        return $this->successResponse([
            'id_inventario' => $item->id_inventario,
            'nombre' => $item->nombre,
            'monto_venta_reparacion_base' => (float) ($item->monto_venta_reparacion_base ?? 0.00),
            'insumos' => $item->reparacionInsumosBase->map(function ($ri) {
                $pu = $ri->insumo ? (float) $ri->insumo->monto_venta_unitario : 0.00;
                return [
                    'id_inventario_reparacion_insumo' => $ri->id_inventario_reparacion_insumo,
                    'id_insumo' => $ri->id_insumo,
                    'id_inventario_insumo' => $ri->id_insumo,
                    'nombre' => $ri->insumo ? $ri->insumo->nombre : null,
                    'sku' => $ri->insumo ? $ri->insumo->sku : null,
                    'cantidad' => $ri->cantidad,
                    'costo_unitario' => $ri->insumo ? (float) $ri->insumo->monto_compra_prom : 0.00,
                    'porcentaje_ganancia' => $ri->insumo ? (float) $ri->insumo->porcentaje_ganancia : 0.00,
                    'costo_unitario_con_ganancia' => $pu,
                    'porcentaje_iva' => $ri->insumo ? (float) $ri->insumo->porcentaje_iva : 19.00,
                    'monto_venta_unitario' => $pu,
                    'subtotal' => round($ri->cantidad * $pu, 2),
                ];
            }),
            'servicios' => $item->reparacionServiciosBase->map(function ($rs) {
                $pu = $rs->servicioTaller ? (float) $rs->servicioTaller->monto_venta_unitario : 0.00;
                return [
                    'id_inventario_reparacion_servicio' => $rs->id_inventario_reparacion_servicio,
                    'id_servicio_taller' => $rs->id_servicio_taller,
                    'nombre' => $rs->servicioTaller ? $rs->servicioTaller->nombre : null,
                    'cantidad' => $rs->cantidad,
                    'costo_unitario' => $rs->servicioTaller ? (float) $rs->servicioTaller->costo_base : 0.00,
                    'porcentaje_ganancia' => $rs->servicioTaller ? (float) $rs->servicioTaller->porcentaje_ganancia : 0.00,
                    'costo_unitario_con_ganancia' => $pu,
                    'porcentaje_iva' => 19.00,
                    'monto_venta_unitario' => $pu,
                    'subtotal' => round($rs->cantidad * $pu, 2),
                ];
            }),
        ], 'Configuración de reparación base obtenida');
    }

    /**
     * Guarda o actualiza la configuración base de reparación para un ítem.
     */
    public function sync(Request $request, $idInventario): JsonResponse
    {
        $request->validate([
            'insumos' => 'nullable|array',
            'insumos.*.id_insumo' => 'nullable|integer|exists:inventario,id_inventario',
            'insumos.*.id_inventario_insumo' => 'nullable|integer|exists:inventario,id_inventario',
            'insumos.*.cantidad' => 'required|integer|min:1',
            'servicios' => 'nullable|array',
            'servicios.*.id_servicio_taller' => 'required|integer|exists:servicios_taller,id_servicio_taller',
            'servicios.*.cantidad' => 'required|integer|min:1',
        ]);

        $item = Inventario::find($idInventario);
        if (!$item) {
            return $this->errorResponse('Ítem de inventario no encontrado', 404);
        }

        \DB::beginTransaction();

        // 1. Sincronizar insumos base
        InventarioReparacionInsumo::where('id_inventario', $idInventario)->delete();
        if (!empty($request->insumos)) {
            foreach ($request->insumos as $ins) {
                $idInsumo = $ins['id_insumo'] ?? $ins['id_inventario_insumo'] ?? null;
                if ($idInsumo) {
                    InventarioReparacionInsumo::create([
                        'id_inventario' => $idInventario,
                        'id_insumo' => $idInsumo,
                        'cantidad' => $ins['cantidad'],
                    ]);
                }
            }
        }

        // 2. Sincronizar servicios base
        InventarioReparacionServicio::where('id_inventario', $idInventario)->delete();
        if (!empty($request->servicios)) {
            foreach ($request->servicios as $srv) {
                InventarioReparacionServicio::create([
                    'id_inventario' => $idInventario,
                    'id_servicio_taller' => $srv['id_servicio_taller'],
                    'cantidad' => $srv['cantidad'],
                ]);
            }
        }

        // 3. Recalcular monto_venta_reparacion_base
        $montoBase = InventarioService::calcularMontoVentaReparacionBase($idInventario);
        $item->update(['monto_venta_reparacion_base' => $montoBase]);

        \DB::commit();

        return $this->show($idInventario);
    }
}