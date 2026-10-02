<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\DetalleCompra;
use App\Models\DetalleOrden;
use App\Models\Reparacion;
use App\Models\Repuesto;
use App\Models\Orden;
use App\Enums\ReparacionEstado;
use App\Enums\OrdenEstadoOperativo;
use App\Enums\InventarioCondicion;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    public static function getAll()
    {
        return Inventario::with('modelo.marca')->get();
    }

    public static function getOne($id)
    {
        return Inventario::with('modelo.marca')->find($id);
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
     * Actualiza stock y metricas economicas al realizar una compra a proveedor:
     * - cantidad_total, cantidad_propia
     * - Recalcula metricas completas segun la guia exacta
     */
    public static function actualizarPorCompra(Inventario $item, int $cantidadComprada, float $costoUnitario)
    {
        $nuevoStockTotal  = (int) $item->cantidad_total + $cantidadComprada;
        $nuevoStockPropio = (int) $item->cantidad_propia + $cantidadComprada;

        $item->update([
            'cantidad_total'  => $nuevoStockTotal,
            'cantidad_propia' => $nuevoStockPropio,
        ]);

        return self::recalcularMetricasCompletas($item->id_inventario);
    }

    /**
     * Actualiza metricas economicas al finalizar una reparacion en el taller:
     * - Recalcula metricas completas segun la guia exacta
     */
    public static function actualizarPorReparacion(Inventario $item, float $costoBaseReparacion)
    {
        return self::recalcularMetricasCompletas($item->id_inventario);
    }

    /**
     * Actualiza metricas economicas y stock al realizar una venta o recambio:
     * - cantidad_total, cantidad_propia
     * - Recalcula metricas completas segun la guia exacta
     */
    public static function actualizarPorVenta(Inventario $item, int $cantidadVendida, float $precioVentaUnitario)
    {
        $nuevoStockTotal  = max(0, (int) $item->cantidad_total - $cantidadVendida);
        $nuevoStockPropio = max(0, (int) $item->cantidad_propia - $cantidadVendida);

        $item->update([
            'cantidad_total'  => $nuevoStockTotal,
            'cantidad_propia' => $nuevoStockPropio,
        ]);

        return self::recalcularMetricasCompletas($item->id_inventario);
    }

    /**
     * Actualiza stock de cliente y metricas de venta al pagar y entregar una reparacion:
     * - cantidad_total, cantidad_cliente (disminuyen en cantidadReparada)
     * - Recalcula metricas completas segun la guia exacta
     */
    public static function actualizarPorReparacionEntrega(Inventario $item, int $cantidadReparada, float $precioCobrado)
    {
        $nuevoStockTotal   = max(0, (int) $item->cantidad_total - $cantidadReparada);
        $nuevoStockCliente = max(0, (int) $item->cantidad_cliente - $cantidadReparada);

        $item->update([
            'cantidad_total'   => $nuevoStockTotal,
            'cantidad_cliente' => $nuevoStockCliente,
        ]);

        return self::recalcularMetricasCompletas($item->id_inventario);
    }

    /**
     * Recalcula metricas economicas completas segun la Guia de Validacion Manual:
     * 1. monto_compra_prom (Opcion A: nuevos/insumos/equipos ponderado; Opcion B: usados tasados)
     * 2. monto_reparacion_prom (reparaciones finalizadas no anuladas)
     * 3. monto_venta_prom (detalles_ordenes no anuladas ponderado por cantidad)
     * 4. monto_venta_unitario = costo_base / (1 - (porcentaje_ganancia / 100))
     * Mínimos y Máximos (_min, _max)
     */
    public static function recalcularMetricasCompletas(int $idInventario)
    {
        $item = Inventario::find($idInventario);
        if (!$item) {
            return null;
        }

        // 1. CORROBORAR monto_compra_prom
        $condicionStr = $item->condicion instanceof InventarioCondicion ? $item->condicion->value : (string) $item->condicion;

        if ($condicionStr === 'usado') {
            // Opcion B: Si el item es USADO (Recambios)
            // 1. Filtra la tabla repuestos por id_inventario, con propietario = true y costo_adquisicion > 0.
            // 2. Confirma que su id_orden_entrada pertenezca a una orden no anulada.
            // 3. Suma los valores de costo_adquisicion (tasacion) y divide entre el total de repuestos usados.
            $repuestosUsadosTasados = Repuesto::where('id_inventario', $idInventario)
                ->where('propietario', true)
                ->where('costo_adquisicion', '>', 0)
                ->whereHas('ordenEntrada', function ($qo) {
                    $qo->where('estado_operativo', '!=', OrdenEstadoOperativo::ANULADA->value);
                })
                ->get();

            if ($repuestosUsadosTasados->isNotEmpty()) {
                $costosTasaciones = $repuestosUsadosTasados->pluck('costo_adquisicion')->map(fn($v) => (float) $v);
                $minCompra = $costosTasaciones->min();
                $maxCompra = $costosTasaciones->max();
                $sumaTasaciones = (float) $costosTasaciones->sum();
                $totalPiezas = $repuestosUsadosTasados->count();
                $promCompra = $totalPiezas > 0 ? round($sumaTasaciones / $totalPiezas, 2) : null;

                $ultimoRepuestoTasado = $repuestosUsadosTasados->sortByDesc('id_repuesto')->first();
                $ultimoMontoCompra = $ultimoRepuestoTasado ? (float) $ultimoRepuestoTasado->costo_adquisicion : null;
            } else {
                $minCompra = null;
                $maxCompra = null;
                $promCompra = null;
                $ultimoMontoCompra = null;
            }
        } else {
            // Opcion A: Si el item es NUEVO, INSUMO o EQUIPO
            // 1. Filtra la tabla detalles_compras por el id_inventario.
            // 2. Multiplica en cada fila: cantidad * costo_unitario.
            // 3. Suma todos los subtotales obtenidos.
            // 4. Divide el total entre la suma de todas las cantidades.
            $detallesCompras = DetalleCompra::where('id_inventario', $idInventario)->get();
            if ($detallesCompras->isNotEmpty()) {
                $costosCompras = $detallesCompras->pluck('costo_unitario')->map(fn($v) => (float) $v);
                $minCompra = $costosCompras->min();
                $maxCompra = $costosCompras->max();

                $sumaCostoTotal = (float) $detallesCompras->sum(fn($d) => (float) $d->costo_unitario * (int) $d->cantidad);
                $sumaCantidades = (int) $detallesCompras->sum('cantidad');
                $promCompra = $sumaCantidades > 0 ? round($sumaCostoTotal / $sumaCantidades, 2) : 0.00;

                $ultimaCompra = DetalleCompra::where('id_inventario', $idInventario)->latest('id_detalle_compra')->first();
                $ultimoMontoCompra = $ultimaCompra ? (float) $ultimaCompra->costo_unitario : 0.00;
            } else {
                $minCompra = null;
                $maxCompra = null;
                $promCompra = null;
                $ultimoMontoCompra = null;
            }
        }

        // 2. CORROBORAR monto_reparacion_prom
        // 1. Consulta la tabla reparaciones para los repuestos de ese id_inventario.
        // 2. Considera solo los registros con estado = 'finalizada' cuya orden asociada no este anulada (si id_orden es null igualmente se toma en cuenta).
        // 3. Suma los costo_total y divide el resultado entre la cantidad de reparaciones validas.
        $idsRepuestos = Repuesto::where('id_inventario', $idInventario)->pluck('id_repuesto');
        $reparacionesValidas = Reparacion::whereIn('id_repuesto', $idsRepuestos)
            ->where('estado', ReparacionEstado::FINALIZADA->value)
            ->where(function ($q) {
                $q->whereNull('id_orden')
                  ->orWhereHas('orden', function ($qo) {
                      $qo->where('estado_operativo', '!=', OrdenEstadoOperativo::ANULADA->value);
                  });
            })
            ->get();

        if ($reparacionesValidas->isNotEmpty()) {
            $costosRep = $reparacionesValidas->pluck('costo_total')->map(fn($v) => (float) $v)->filter(fn($v) => $v > 0);
            $minRep = $costosRep->isNotEmpty() ? $costosRep->min() : null;
            $maxRep = $costosRep->isNotEmpty() ? $costosRep->max() : null;
            $sumaCostosRep = (float) $costosRep->sum();
            $cantRepValidas = $costosRep->count();
            $promRep = $cantRepValidas > 0 ? round($sumaCostosRep / $cantRepValidas, 2) : null;
        } else {
            $minRep = null;
            $maxRep = null;
            $promRep = null;
        }

        // 3. CORROBORAR monto_venta_prom
        // 1. En la tabla detalles_ordenes, filtra por id_inventario donde orden asociada != 'anulada'.
        // 2. Multiplica: cantidad * precio_unitario.
        // 3. Suma total de las ventas.
        // 4. Divide el monto global entre la suma de las cantidades vendidas.
        $detallesVentas = DetalleOrden::where(function ($q) use ($idInventario) {
                $q->where('id_inventario_repuesto_saliente', $idInventario)
                  ->orWhere('id_inventario_insumo_saliente', $idInventario);
            })
            ->whereHas('orden', function ($qo) {
                $qo->where('estado_operativo', '!=', OrdenEstadoOperativo::ANULADA->value);
            })
            ->get();

        if ($detallesVentas->isNotEmpty()) {
            $preciosVenta = $detallesVentas->pluck('precio_unitario')->map(fn($v) => (float) $v)->filter(fn($v) => $v > 0);
            $minVenta = $preciosVenta->isNotEmpty() ? $preciosVenta->min() : null;
            $maxVenta = $preciosVenta->isNotEmpty() ? $preciosVenta->max() : null;

            $sumaTotalDinero = (float) $detallesVentas->sum(fn($d) => (float) $d->precio_unitario * (int) $d->cantidad);
            $sumaCantidades = (int) $detallesVentas->sum('cantidad');
            $promVenta = $sumaCantidades > 0 ? round($sumaTotalDinero / $sumaCantidades, 2) : null;
        } else {
            $minVenta = null;
            $maxVenta = null;
            $promVenta = null;
        }

        // 4. CORROBORAR monto_venta_unitario (Precio Sugerido)
        // 1. costo_base = monto_compra_prom + monto_reparacion_prom
        // 2. monto_venta_unitario = costo_base / (1 - (porcentaje_ganancia / 100))
        $baseCompra = (float) ($promCompra ?? 0.00);
        $baseRep = (float) ($promRep ?? 0.00);
        $costoBaseTotal = $baseCompra + $baseRep;
        $porcentajeGanancia = (float) ($item->porcentaje_ganancia ?? 0.00);

        if ($porcentajeGanancia > 0 && $porcentajeGanancia < 100 && $costoBaseTotal > 0) {
            $nuevoPrecioVenta = round($costoBaseTotal / (1 - ($porcentajeGanancia / 100)), 2);
        } else {
            $nuevoPrecioVenta = (float) $item->monto_venta_unitario;
        }

        $item->update([
            'ultimo_monto_compra'  => $ultimoMontoCompra !== null ? round($ultimoMontoCompra, 2) : null,
            'monto_compra_min'     => $minCompra !== null ? round($minCompra, 2) : null,
            'monto_compra_max'     => $maxCompra !== null ? round($maxCompra, 2) : null,
            'monto_compra_prom'    => $promCompra !== null ? round($promCompra, 2) : null,
            'monto_reparacion_min'  => $minRep !== null ? round($minRep, 2) : null,
            'monto_reparacion_max'  => $maxRep !== null ? round($maxRep, 2) : null,
            'monto_reparacion_prom' => $promRep !== null ? round($promRep, 2) : null,
            'monto_venta_min'      => $minVenta !== null ? round($minVenta, 2) : null,
            'monto_venta_max'      => $maxVenta !== null ? round($maxVenta, 2) : null,
            'monto_venta_prom'     => $promVenta !== null ? round($promVenta, 2) : null,
            'monto_venta_unitario' => round($nuevoPrecioVenta, 2),
        ]);

        return $item->fresh();
    }
}
