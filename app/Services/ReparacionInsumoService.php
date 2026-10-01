<?php

namespace App\Services;

use App\Models\ReparacionInsumo;
use App\Models\Inventario;
use Illuminate\Support\Facades\DB;

class ReparacionInsumoService
{
    public static function getAll()
    {
        return ReparacionInsumo::get();
    }

    public static function getOne($id)
    {
        return ReparacionInsumo::find($id);
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $data['id_admin'] = $data['id_admin'] ?? auth()->id() ?? 1;
        $insumo = ReparacionService::registrarInsumo($data);

        // Recalcular costos de la reparación
        ReparacionService::recalcularCostosReparacion($insumo->id_reparacion);

        DB::commit();

        return $insumo;
    }

    public static function update($id, $data)
    {
        $insumo = ReparacionInsumo::find($id);
        if (!$insumo) {
            return null;
        }

        DB::beginTransaction();

        $cantidadAnterior = (int) $insumo->cantidad;
        $item = Inventario::find($insumo->id_inventario);

        // Si se modifica la cantidad, ajustar inventario
        if (isset($data['cantidad'])) {
            $nuevaCantidad = (int) $data['cantidad'];
            $diferencia = $nuevaCantidad - $cantidadAnterior;

            if ($item && $diferencia != 0) {
                $item->update([
                    'cantidad_total'  => max(0, (int) $item->cantidad_total - $diferencia),
                    'cantidad_propia' => max(0, (int) $item->cantidad_propia - $diferencia),
                ]);
            }

            $costoUnitario = (float) ($data['costo_unitario'] ?? $insumo->costo_unitario);
            $costoUnitarioConGanancia = (float) ($data['costo_unitario_con_ganancia'] ?? $insumo->costo_unitario_con_ganancia);
            $porcentajeIva = (float) ($data['porcentaje_iva'] ?? $insumo->porcentaje_iva);

            $data['monto_total_linea'] = round($nuevaCantidad * $costoUnitario, 2);
            $data['monto_total_linea_con_ganancia'] = round($nuevaCantidad * $costoUnitarioConGanancia, 2);
            $data['monto_iva'] = round($data['monto_total_linea_con_ganancia'] * ($porcentajeIva / 100), 2);
        }

        $insumo->update($data);

        // Recalcular costos de la reparación
        ReparacionService::recalcularCostosReparacion($insumo->id_reparacion);

        DB::commit();

        return $insumo->fresh();
    }

    public static function delete($id)
    {
        $insumo = ReparacionInsumo::find($id);
        if (!$insumo) {
            return null;
        }

        DB::beginTransaction();

        $idReparacion = $insumo->id_reparacion;

        // Devolver stock al inventario
        $item = Inventario::find($insumo->id_inventario);
        if ($item) {
            $item->update([
                'cantidad_total'  => (int) $item->cantidad_total + (int) $insumo->cantidad,
                'cantidad_propia' => (int) $item->cantidad_propia + (int) $insumo->cantidad,
            ]);
        }

        $insumo->delete();

        // Recalcular costos de la reparación
        ReparacionService::recalcularCostosReparacion($idReparacion);

        DB::commit();

        return $insumo;
    }
}