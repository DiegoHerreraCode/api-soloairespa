<?php

namespace App\Services;

use App\Models\ReparacionInsumo;
use App\Models\Inventario;
use Illuminate\Support\Facades\DB;

/**
 * Service ReparacionInsumoService
 * 
 * Gestiona el consumo, edición y eliminación de insumos cargados a una reparación.
 * Sincroniza en tiempo real las existencias físicas de inventario (descontando o reintegrando stock)
 * y dispara el recálculo consolidado de costos de la reparación.
 */
class ReparacionInsumoService
{
    /**
     * Retorna todos los insumos consumidos en reparaciones.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_insumos;
     */
    public static function getAll()
    {
        return ReparacionInsumo::get();
    }

    /**
     * Obtiene el registro de un insumo cargado por su clave primaria.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_insumos WHERE id_reparacion_insumo = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return ReparacionInsumo::find($id);
    }

    /**
     * Registra un nuevo insumo consumido en una reparación a través de ReparacionService,
     * descontando el stock del inventario y recalculando la reparación.
     * Consulta SQL Raw:
     * INSERT INTO reparaciones_insumos (...) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();

        $data['id_admin'] = $data['id_admin'] ?? auth()->id() ?? 1;
        $insumo = ReparacionService::registrarInsumo($data);

        // Recalcular costos consolidados de la reparación
        ReparacionService::recalcularCostosReparacion($insumo->id_reparacion);

        DB::commit();

        return $insumo;
    }

    /**
     * Actualiza la cantidad o costos de un insumo cargado.
     * Si la cantidad cambia, calcula la diferencia neta y ajusta el stock propio de inventario.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_insumos WHERE id_reparacion_insumo = $id LIMIT 1;
     * UPDATE inventario SET cantidad_total = ..., cantidad_propia = ... WHERE id_inventario = ...;
     * UPDATE reparaciones_insumos SET cantidad = ..., monto_total_linea = ... WHERE id_reparacion_insumo = $id;
     */
    public static function update($id, $data)
    {
        $insumo = ReparacionInsumo::find($id);
        if (!$insumo) {
            return null;
        }

        DB::beginTransaction();

        $cantidadAnterior = (int) $insumo->cantidad;
        $item = Inventario::find($insumo->id_inventario);

        // Si se modifica la cantidad consumida, ajustar existencias de inventario
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

    /**
     * Elimina un insumo cargado por error y reintegra el 100% de las unidades al inventario.
     * Consulta SQL Raw:
     * UPDATE inventario SET cantidad_total = cantidad_total + ..., cantidad_propia = cantidad_propia + ... WHERE id_inventario = ...;
     * DELETE FROM reparaciones_insumos WHERE id_reparacion_insumo = $id;
     */
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

        // Recalcular costos consolidados de la reparación
        ReparacionService::recalcularCostosReparacion($idReparacion);

        DB::commit();

        return $insumo;
    }
}
