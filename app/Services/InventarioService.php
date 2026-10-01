<?php

namespace App\Services;

use App\Models\Inventario;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    public static function getAll()
    {
        $items = Inventario::get();
        return $items;
    }

    public static function getOne($id)
    {
        $item = Inventario::find($id);
        return $item;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $item = Inventario::create($data);
        DB::commit();
        return $item;
    }

    public static function update($id, $data)
    {
        $item = Inventario::find($id);
        if (!$item) {
            return null;
        }

        DB::beginTransaction();
        $item->update($data);
        DB::commit();
        return $item;
    }

    public static function delete($id)
    {
        $item = Inventario::find($id);
        if (!$item) {
            return null;
        }

        DB::beginTransaction();
        $item->delete();
        DB::commit();
        return $item;
    }

    /**
     * Actualiza stock y métricas económicas al realizar una compra a proveedor:
     * - cantidad_total, cantidad_propia
     * - ultimo_monto_compra
     * - monto_compra_min, monto_compra_max, monto_compra_prom
     * - monto_venta_unitario
     */
    public static function actualizarPorCompra(Inventario $item, int $cantidadComprada, float $costoUnitario)
    {
        $stockAnterior = (int) $item->cantidad_total;
        $costoAnterior = (float) $item->monto_compra_prom;

        $nuevoStockTotal  = $stockAnterior + $cantidadComprada;
        $nuevoStockPropio = (int) $item->cantidad_propia + $cantidadComprada;

        if ($nuevoStockTotal > 0) {
            $nuevoCostoPromedio = (($stockAnterior * $costoAnterior) + ($cantidadComprada * $costoUnitario)) / $nuevoStockTotal;
        } else {
            $nuevoCostoPromedio = $costoUnitario;
        }

        $minCompra = is_null($item->monto_compra_min) || (float) $item->monto_compra_min <= 0
            ? $costoUnitario
            : min((float) $item->monto_compra_min, $costoUnitario);

        $maxCompra = is_null($item->monto_compra_max)
            ? $costoUnitario
            : max((float) $item->monto_compra_max, $costoUnitario);

        $porcentajeGanancia = (float) ($item->porcentaje_ganancia ?? 0.00);
        $costoReparacionProm = (float) ($item->monto_reparacion_prom ?? 0.00);
        $costoBaseTotal = $nuevoCostoPromedio + $costoReparacionProm;

        if ($porcentajeGanancia > 0 && $porcentajeGanancia < 100) {
            $nuevoPrecioVenta = $costoBaseTotal / (1 - ($porcentajeGanancia / 100));
        } else {
            $nuevoPrecioVenta = (float) $item->monto_venta_unitario;
        }

        $item->update([
            'cantidad_total'       => $nuevoStockTotal,
            'cantidad_propia'      => $nuevoStockPropio,
            'ultimo_monto_compra'  => round($costoUnitario, 2),
            'monto_compra_min'     => round($minCompra, 2),
            'monto_compra_max'     => round($maxCompra, 2),
            'monto_compra_prom'    => round($nuevoCostoPromedio, 2),
            'monto_venta_unitario' => round($nuevoPrecioVenta, 2),
        ]);

        return $item;
    }

    /**
     * Actualiza métricas económicas al finalizar una reparación en el taller:
     * - monto_reparacion_min, monto_reparacion_max, monto_reparacion_prom
     * - monto_venta_unitario
     */
    public static function actualizarPorReparacion(Inventario $item, float $costoBaseReparacion)
    {
        if ($costoBaseReparacion <= 0) {
            return $item;
        }

        $minRep = is_null($item->monto_reparacion_min) || (float) $item->monto_reparacion_min <= 0
            ? $costoBaseReparacion
            : min((float) $item->monto_reparacion_min, $costoBaseReparacion);

        $maxRep = is_null($item->monto_reparacion_max)
            ? $costoBaseReparacion
            : max((float) $item->monto_reparacion_max, $costoBaseReparacion);

        $montoRepProm = (float) ($item->monto_reparacion_prom ?? 0.00);
        $nuevoMontoRepProm = $montoRepProm > 0
            ? round(($montoRepProm + $costoBaseReparacion) / 2, 2)
            : $costoBaseReparacion;

        $montoCompraProm = (float) ($item->monto_compra_prom ?? 0.00);
        $porcentajeGanancia = (float) ($item->porcentaje_ganancia ?? 0.00);
        $costoBaseTotal = $montoCompraProm + $nuevoMontoRepProm;

        if ($porcentajeGanancia > 0 && $porcentajeGanancia < 100) {
            $nuevoPrecioVenta = $costoBaseTotal / (1 - ($porcentajeGanancia / 100));
        } else {
            $nuevoPrecioVenta = (float) $item->monto_venta_unitario;
        }

        $item->update([
            'monto_reparacion_min'  => round($minRep, 2),
            'monto_reparacion_max'  => round($maxRep, 2),
            'monto_reparacion_prom' => round($nuevoMontoRepProm, 2),
            'monto_venta_unitario'  => round($nuevoPrecioVenta, 2),
        ]);

        return $item;
    }

    /**
     * Actualiza métricas económicas y stock al realizar una venta o recambio:
     * - monto_venta_min, monto_venta_max, monto_venta_prom
     * - cantidad_total, cantidad_propia
     */
    public static function actualizarPorVenta(Inventario $item, int $cantidadVendida, float $precioVentaUnitario)
    {
        $minVenta = is_null($item->monto_venta_min) || (float) $item->monto_venta_min <= 0
            ? $precioVentaUnitario
            : min((float) $item->monto_venta_min, $precioVentaUnitario);

        $maxVenta = is_null($item->monto_venta_max)
            ? $precioVentaUnitario
            : max((float) $item->monto_venta_max, $precioVentaUnitario);

        $ventaPromActual = (float) ($item->monto_venta_prom ?? 0.00);
        $nuevaVentaProm = $ventaPromActual > 0
            ? round(($ventaPromActual + $precioVentaUnitario) / 2, 2)
            : $precioVentaUnitario;

        $nuevoStockTotal  = max(0, (int) $item->cantidad_total - $cantidadVendida);
        $nuevoStockPropio = max(0, (int) $item->cantidad_propia - $cantidadVendida);

        $item->update([
            'monto_venta_min'  => round($minVenta, 2),
            'monto_venta_max'  => round($maxVenta, 2),
            'monto_venta_prom' => round($nuevaVentaProm, 2),
            'cantidad_total'   => $nuevoStockTotal,
            'cantidad_propia'  => $nuevoStockPropio,
        ]);

        return $item;
    }

    /**
     * Actualiza stock de cliente y metricas de venta al pagar y entregar una reparacion:
     * - cantidad_total, cantidad_cliente (disminuyen en cantidadReparada)
     * - monto_venta_min, monto_venta_max, monto_venta_prom
     */
    public static function actualizarPorReparacionEntrega(Inventario $item, int $cantidadReparada, float $precioCobrado)
    {
        $minVenta = is_null($item->monto_venta_min) || (float) $item->monto_venta_min <= 0
            ? $precioCobrado
            : min((float) $item->monto_venta_min, $precioCobrado);

        $maxVenta = is_null($item->monto_venta_max)
            ? $precioCobrado
            : max((float) $item->monto_venta_max, $precioCobrado);

        $ventaPromActual = (float) ($item->monto_venta_prom ?? 0.00);
        $nuevaVentaProm = $ventaPromActual > 0
            ? round(($ventaPromActual + $precioCobrado) / 2, 2)
            : $precioCobrado;

        $nuevoStockTotal   = max(0, (int) $item->cantidad_total - $cantidadReparada);
        $nuevoStockCliente = max(0, (int) $item->cantidad_cliente - $cantidadReparada);

        $item->update([
            'monto_venta_min'  => round($minVenta, 2),
            'monto_venta_max'  => round($maxVenta, 2),
            'monto_venta_prom' => round($nuevaVentaProm, 2),
            'cantidad_total'   => $nuevoStockTotal,
            'cantidad_cliente' => $nuevoStockCliente,
        ]);

        return $item;
    }
}
