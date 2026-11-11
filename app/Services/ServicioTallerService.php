<?php

namespace App\Services;

use App\Models\ServicioTaller;
use Illuminate\Support\Facades\DB;

/**
 * Service ServicioTallerService
 * 
 * Gestiona el catálogo de servicios técnicos y mano de obra del taller.
 */
class ServicioTallerService
{
    public static function getAll()
    {
        return ServicioTaller::get();
    }

    public static function getOne($id)
    {
        return ServicioTaller::find($id);
    }

    public static function create($data)
    {
        DB::beginTransaction();

        // Si no viene monto_venta_unitario o es 0, calcularlo con costo_base y ganancia
        if (empty($data['monto_venta_unitario']) || (float) $data['monto_venta_unitario'] <= 0) {
            $costo = (float) ($data['costo_base'] ?? 0.00);
            $gan = (float) ($data['porcentaje_ganancia'] ?? 0.00);
            $factor = 1 - ($gan / 100);
            $data['monto_venta_unitario'] = $factor > 0 ? round($costo / $factor, 2) : $costo;
        }

        $servicio = ServicioTaller::create($data);
        DB::commit();
        return $servicio;
    }

    public static function update($id, $data)
    {
        $servicio = ServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        DB::beginTransaction();

        $precioAnterior = (float) $servicio->monto_venta_unitario;

        // Si se actualizan costo o ganancia pero no monto_venta_unitario explícito
        if (!isset($data['monto_venta_unitario'])) {
            $costo = isset($data['costo_base']) ? (float) $data['costo_base'] : (float) $servicio->costo_base;
            $gan = isset($data['porcentaje_ganancia']) ? (float) $data['porcentaje_ganancia'] : (float) $servicio->porcentaje_ganancia;
            $factor = 1 - ($gan / 100);
            $data['monto_venta_unitario'] = $factor > 0 ? round($costo / $factor, 2) : $costo;
        }

        $servicio->update($data);

        // Si cambió el precio de venta unitario, recalcular items que tengan este servicio en su reparación base
        if (round($precioAnterior, 2) !== round((float) $servicio->monto_venta_unitario, 2)) {
            InventarioService::recalcularItemsPorCambioServicio($servicio->id_servicio_taller);
        }

        DB::commit();
        return $servicio;
    }

    public static function delete($id)
    {
        $servicio = ServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        DB::beginTransaction();
        $servicio->delete();
        DB::commit();
        return $servicio;
    }
}