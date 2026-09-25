<?php

namespace App\Services;

use App\Models\Repuesto;
use Illuminate\Support\Facades\DB;

class RepuestoService
{
    public static function getAll()
    {
        $repuestos = Repuesto::with(['inventario.modelo.marca', 'detalleCompra.compra'])->where('is_deleted', false)->get();
        return $repuestos;
    }

    public static function getOne($id)
    {
        $repuesto = Repuesto::with(['inventario.modelo.marca', 'detalleCompra.compra'])->where('is_deleted', false)->find($id);
        return $repuesto;
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $costoAdquisicion = (float) ($data['costo_adquisicion'] ?? 0.00);
        $costoReparacionBase = (float) ($data['costo_reparacion_base'] ?? 0.00);

        if (!isset($data['costo_total'])) {
            $data['costo_total'] = $costoAdquisicion + $costoReparacionBase;
        }

        $repuesto = Repuesto::create($data);
        DB::commit();
        return $repuesto;
    }

    public static function update($id, $data)
    {
        $repuesto = Repuesto::find($id);
        if (!$repuesto) {
            return null;
        }

        DB::beginTransaction();

        $costoAdquisicion = isset($data['costo_adquisicion']) ? (float) $data['costo_adquisicion'] : (float) $repuesto->costo_adquisicion;
        $costoReparacionBase = isset($data['costo_reparacion_base']) ? (float) $data['costo_reparacion_base'] : (float) $repuesto->costo_reparacion_base;

        if (isset($data['costo_adquisicion']) || isset($data['costo_reparacion_base'])) {
            if (!isset($data['costo_total'])) {
                $data['costo_total'] = $costoAdquisicion + $costoReparacionBase;
            }
        }

        $repuesto->update($data);
        DB::commit();
        return $repuesto;
    }

    public static function delete($id)
    {
        $repuesto = Repuesto::find($id);
        if (!$repuesto) {
            return null;
        }

        DB::beginTransaction();
        // Borrado lógico según la columna is_deleted del DDL
        $repuesto->update(['is_deleted' => true]);
        DB::commit();
        return $repuesto;
    }
}
