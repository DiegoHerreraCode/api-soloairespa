<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\Admin;
use App\Services\MailerService;
use App\Models\DetalleCompra;
use App\Models\DetalleOrden;
use App\Models\Reparacion;
use App\Models\Repuesto;
use App\Models\Orden;
use App\Enums\ReparacionEstado;
use App\Enums\OrdenEstadoOperativo;
use App\Enums\InventarioCondicion;
use Illuminate\Support\Facades\DB;

/**
 * Service InventarioService
 * 
 * Gestiona el catálogo de existencias y centraliza el motor de recálculo
 * económico exacto según la Guía de Validación Manual de Métricas:
 * - monto_compra_prom (Opción A: Nuevos/Equipos/Insumos ponderado de compras; Opción B: Usados ponderado de tasados).
 * - monto_reparacion_prom (promedio ponderado de reparaciones finalizadas válidas).
 * - monto_venta_prom (promedio ponderado por cantidades de ventas no anuladas).
 * - monto_venta_unitario (precio sugerido sobre costo base y porcentaje_ganancia).
 * - Mínimos y máximos históricos (_min, _max).
 */
class InventarioService
{
    /**
     * Retorna todos los ítems de inventario con su modelo y marca.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAll()
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "inventario";
        // SELECT * FROM "modelos" WHERE "id_modelo" IN (...);
        // SELECT * FROM "marcas" WHERE "id_marca" IN (...);
        return Inventario::with('modelo.marca')->get();
    }

    /**
     * Obtiene un ítem de inventario por su ID.
     *
     * @param int|string $id
     * @return Inventario|null
     */
    public static function getOne($id)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "inventario" WHERE "id_inventario" = :id LIMIT 1;
        // SELECT * FROM "modelos" WHERE "id_modelo" = :id_modelo LIMIT 1;
        // SELECT * FROM "marcas" WHERE "id_marca" = :id_marca LIMIT 1;
        return Inventario::with('modelo.marca')->find($id);
    }

    /**
     * Registra un nuevo ítem en inventario dentro de una transacción.
     *
     * @param array $data
     * @return Inventario
     */
    public static function create($data)
    {
        DB::beginTransaction();

        // Consulta SQL Raw equivalente:
        // INSERT INTO "inventario" ("id_inventario", "sku", "nombre", "tipo", "condicion", "cantidad_total", "cantidad_propia", "cantidad_cliente", "stock_minimo", "porcentaje_ganancia", "created_at", "updated_at") VALUES (...);
        $item = Inventario::create($data);

        DB::commit();
        self::verificarYNotificarStockBajo($item);
        return $item;
    }

    /**
     * Actualiza atributos de un ítem de inventario.
     *
     * @param int|string $id
     * @param array $data
     * @return Inventario|null
     */
    public static function update($id, $data)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "inventario" WHERE "id_inventario" = :id LIMIT 1;
        $item = Inventario::find($id);
        if (!$item) {
            return null;
        }

        DB::beginTransaction();

        // Consulta SQL Raw equivalente:
        // UPDATE "inventario" SET "nombre" = :nombre, "stock_minimo" = :stock, "porcentaje_ganancia" = :ganancia, "updated_at" = NOW() WHERE "id_inventario" = :id;
        $item->update($data);

        DB::commit();
        self::verificarYNotificarStockBajo($item);
        return $item;
    }

    /**
     * Elimina un ítem de inventario.
     *
     * @param int|string $id
     * @return Inventario|null
     */
    public static function delete($id)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "inventario" WHERE "id_inventario" = :id LIMIT 1;
        $item = Inventario::find($id);
        if (!$item) {
            return null;
        }

        DB::beginTransaction();

        // Consulta SQL Raw equivalente:
        // DELETE FROM "inventario" WHERE "id_inventario" = :id;
        $item->delete();

        DB::commit();
        return $item;
    }

    /**
     * Actualiza existencias tras una compra a proveedor y ejecuta el recálculo económico consolidado.
     *
     * @param Inventario $item
     * @param int $cantidadComprada
     * @param float $costoUnitario
     * @return Inventario|null
     */
    public static function actualizarPorCompra(Inventario $item, int $cantidadComprada, float $costoUnitario)
    {
        $nuevoStockTotal = (int) $item->cantidad_total + $cantidadComprada;
        $nuevoStockPropio = (int) $item->cantidad_propia + $cantidadComprada;

        // Consulta SQL Raw equivalente:
        // UPDATE "inventario" SET "cantidad_total" = :nuevoStockTotal, "cantidad_propia" = :nuevoStockPropio, "updated_at" = NOW() WHERE "id_inventario" = :id;
        $item->update([
            'cantidad_total' => $nuevoStockTotal,
            'cantidad_propia' => $nuevoStockPropio,
        ]);

        return self::recalcularMetricasCompletas($item->id_inventario);
    }

    /**
     * Actualiza métricas económicas de reparación tras finalizar una labor en taller.
     *
     * @param Inventario $item
     * @param float $costoBaseReparacion
     * @return Inventario|null
     */
    public static function actualizarPorReparacion(Inventario $item, float $costoBaseReparacion)
    {
        return self::recalcularMetricasCompletas($item->id_inventario);
    }

    /**
     * Descuenta existencias tras una venta o salida de recambio y recalcula métricas ponderadas.
     *
     * @param Inventario $item
     * @param int $cantidadVendida
     * @param float $precioVentaUnitario
     * @return Inventario|null
     */
    public static function actualizarPorVenta(Inventario $item, int $cantidadVendida, float $precioVentaUnitario)
    {
        $nuevoStockTotal = max(0, (int) $item->cantidad_total - $cantidadVendida);
        $nuevoStockPropio = max(0, (int) $item->cantidad_propia - $cantidadVendida);

        // Consulta SQL Raw equivalente:
        // UPDATE "inventario" SET "cantidad_total" = :nuevoStockTotal, "cantidad_propia" = :nuevoStockPropio, "updated_at" = NOW() WHERE "id_inventario" = :id;
        $item->update([
            'cantidad_total' => $nuevoStockTotal,
            'cantidad_propia' => $nuevoStockPropio,
        ]);

        return self::recalcularMetricasCompletas($item->id_inventario);
    }

    /**
     * Descuenta existencias de cliente al entregar una reparación facturada y recalcula métricas.
     *
     * @param Inventario $item
     * @param int $cantidadReparada
     * @param float $precioCobrado
     * @return Inventario|null
     */
    public static function actualizarPorReparacionEntrega(Inventario $item, int $cantidadReparada, float $precioCobrado)
    {
        $nuevoStockTotal = max(0, (int) $item->cantidad_total - $cantidadReparada);
        $nuevoStockCliente = max(0, (int) $item->cantidad_cliente - $cantidadReparada);

        // Consulta SQL Raw equivalente:
        // UPDATE "inventario" SET "cantidad_total" = :nuevoStockTotal, "cantidad_cliente" = :nuevoStockCliente, "updated_at" = NOW() WHERE "id_inventario" = :id;
        $item->update([
            'cantidad_total' => $nuevoStockTotal,
            'cantidad_cliente' => $nuevoStockCliente,
        ]);

        return self::recalcularMetricasCompletas($item->id_inventario);
    }

    /**
     * Ingresa existencias recibidas de un cliente para diagnóstico/reparación directa.
     *
     * @param Inventario $item
     * @param int $cantidadEntrante
     * @return Inventario
     */
    public static function sumarStockCliente(Inventario $item, int $cantidadEntrante = 1)
    {
        // Consulta SQL Raw equivalente:
        // UPDATE "inventario" SET "cantidad_total" = cantidad_total + :cant, "cantidad_cliente" = cantidad_cliente + :cant, "updated_at" = NOW() WHERE "id_inventario" = :id;
        $item->update([
            'cantidad_total' => (int) $item->cantidad_total + $cantidadEntrante,
            'cantidad_cliente' => (int) $item->cantidad_cliente + $cantidadEntrante,
        ]);

        return $item->fresh();
    }

    /**
     * Descuenta existencias de cliente (por ejemplo en cancelaciones o entregas sin reparación).
     *
     * @param Inventario $item
     * @param int $cantidadSaliente
     * @return Inventario
     */
    public static function restarStockCliente(Inventario $item, int $cantidadSaliente = 1)
    {
        // Consulta SQL Raw equivalente:
        // UPDATE "inventario" SET "cantidad_total" = GREATEST(0, cantidad_total - :cant), "cantidad_cliente" = GREATEST(0, cantidad_cliente - :cant), "updated_at" = NOW() WHERE "id_inventario" = :id;
        $item->update([
            'cantidad_total' => max(0, (int) $item->cantidad_total - $cantidadSaliente),
            'cantidad_cliente' => max(0, (int) $item->cantidad_cliente - $cantidadSaliente),
        ]);

        return $item->fresh();
    }

    /**
     * Motor central de recálculo económico exacto según las 4 métricas del negocio.
     *
     * @param int $idInventario
     * @return Inventario|null
     */
    public static function recalcularMetricasCompletas(int $idInventario)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "inventario" WHERE "id_inventario" = :idInventario LIMIT 1;
        $item = Inventario::find($idInventario);
        if (!$item) {
            return null;
        }

        // =========================================================================
        // 1. CORROBORAR monto_compra_prom
        // =========================================================================
        $condicionStr = $item->condicion instanceof InventarioCondicion ? $item->condicion->value : (string) $item->condicion;

        if ($condicionStr === 'usado') {
            // Opción B: Si el ítem es USADO (Recambios)
            // 1. Filtra la tabla repuestos por id_inventario, con propietario = true y costo_adquisicion > 0.
            // 2. Confirma que su id_orden_entrada pertenezca a una orden no anulada.
            // 3. Suma los valores de costo_adquisicion (tasación) y divide entre el total de repuestos usados.

            // Consulta SQL Raw equivalente:
            // SELECT r.* FROM "repuestos" r
            // WHERE r."id_inventario" = :idInventario
            //   AND r."propietario" = true
            //   AND r."costo_adquisicion" > 0
            //   AND EXISTS (
            //       SELECT 1 FROM "ordenes" o 
            //       WHERE o."id_orden" = r."id_orden_entrada" 
            //         AND o."estado_operativo" != 'anulada'
            //   );
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
            // Opción A: Si el ítem es NUEVO, INSUMO o EQUIPO
            // 1. Filtra la tabla detalles_compras por el id_inventario.
            // 2. Multiplica en cada fila: cantidad * costo_unitario.
            // 3. Suma todos los subtotales obtenidos.
            // 4. Divide el total entre la suma de todas las cantidades: ((c1*p1) + (c2*p2) + ...) / (c1 + c2 + ...)

            // Consulta SQL Raw equivalente:
            // SELECT * FROM "detalles_compras" WHERE "id_inventario" = :idInventario;
            $detallesCompras = DetalleCompra::where('id_inventario', $idInventario)->get();
            if ($detallesCompras->isNotEmpty()) {
                $costosCompras = $detallesCompras->pluck('costo_unitario')->map(fn($v) => (float) $v);
                $minCompra = $costosCompras->min();
                $maxCompra = $costosCompras->max();

                $sumaCostoTotal = (float) $detallesCompras->sum(fn($d) => (float) $d->costo_unitario * (int) $d->cantidad);
                $sumaCantidades = (int) $detallesCompras->sum('cantidad');
                $promCompra = $sumaCantidades > 0 ? round($sumaCostoTotal / $sumaCantidades, 2) : 0.00;

                // Consulta SQL Raw equivalente:
                // SELECT * FROM "detalles_compras" WHERE "id_inventario" = :idInventario ORDER BY "id_detalle_compra" DESC LIMIT 1;
                $ultimaCompra = DetalleCompra::where('id_inventario', $idInventario)->latest('id_detalle_compra')->first();
                $ultimoMontoCompra = $ultimaCompra ? (float) $ultimaCompra->costo_unitario : 0.00;
            } else {
                $minCompra = null;
                $maxCompra = null;
                $promCompra = null;
                $ultimoMontoCompra = null;
            }
        }

        // =========================================================================
        // 2. CORROBORAR monto_reparacion_prom
        // =========================================================================
        // 1. Consulta la tabla reparaciones para los repuestos de ese id_inventario.
        // 2. Considera solo los registros con estado = 'finalizada' cuya orden asociada no esté anulada 
        //    (si id_orden es null igualmente se toma en cuenta para el cálculo).
        // 3. Suma los costo_total y divide el resultado entre la cantidad de reparaciones válidas.

        // Consulta SQL Raw equivalente:
        // SELECT "id_repuesto" FROM "repuestos" WHERE "id_inventario" = :idInventario;
        $idsRepuestos = Repuesto::where('id_inventario', $idInventario)->pluck('id_repuesto');

        // Consulta SQL Raw equivalente:
        // SELECT rep.* FROM "reparaciones" rep
        // WHERE rep."id_repuesto" IN (SELECT "id_repuesto" FROM "repuestos" WHERE "id_inventario" = :idInventario)
        //   AND rep."estado" = 'finalizada'
        //   AND (
        //       rep."id_orden" IS NULL 
        //       OR EXISTS (
        //           SELECT 1 FROM "ordenes" o 
        //           WHERE o."id_orden" = rep."id_orden" 
        //             AND o."estado_operativo" != 'anulada'
        //       )
        //   );
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

        // =========================================================================
        // 3. CORROBORAR monto_venta_prom
        // =========================================================================
        // 1. En la tabla detalles_ordenes, filtra por id_inventario donde orden asociada != 'anulada'.
        // 2. Multiplica: cantidad * precio_unitario.
        // 3. Suma total de las ventas.
        // 4. Divide el monto global entre la suma de las cantidades vendidas.

        // Consulta SQL Raw equivalente:
        // SELECT d.* FROM "detalles_ordenes" d
        // JOIN "ordenes" o ON o."id_orden" = d."id_orden"
        // WHERE (d."id_inventario_repuesto_saliente" = :idInventario OR d."id_inventario_insumo_saliente" = :idInventario)
        //   AND o."estado_operativo" != 'anulada';
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

        // =========================================================================
        // 4. CORROBORAR monto_venta_unitario (Precio Sugerido)
        // =========================================================================
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

        // Actualización final consolidada en inventario
        // Consulta SQL Raw equivalente:
        // UPDATE "inventario" SET 
        //   "ultimo_monto_compra"  = :ultimoMontoCompra,
        //   "monto_compra_min"     = :minCompra,
        //   "monto_compra_max"     = :maxCompra,
        //   "monto_compra_prom"    = :promCompra,
        //   "monto_reparacion_min"  = :minRep,
        //   "monto_reparacion_max"  = :maxRep,
        //   "monto_reparacion_prom" = :promRep,
        //   "monto_venta_min"      = :minVenta,
        //   "monto_venta_max"      = :maxVenta,
        //   "monto_venta_prom"     = :promVenta,
        //   "monto_venta_unitario" = :nuevoPrecioVenta,
        //   "updated_at"           = NOW()
        // WHERE "id_inventario"    = :idInventario;
        $item->update([
            'ultimo_monto_compra' => $ultimoMontoCompra !== null ? round($ultimoMontoCompra, 2) : null,
            'monto_compra_min' => $minCompra !== null ? round($minCompra, 2) : null,
            'monto_compra_max' => $maxCompra !== null ? round($maxCompra, 2) : null,
            'monto_compra_prom' => $promCompra !== null ? round($promCompra, 2) : null,
            'monto_reparacion_min' => $minRep !== null ? round($minRep, 2) : null,
            'monto_reparacion_max' => $maxRep !== null ? round($maxRep, 2) : null,
            'monto_reparacion_prom' => $promRep !== null ? round($promRep, 2) : null,
            'monto_venta_min' => $minVenta !== null ? round($minVenta, 2) : null,
            'monto_venta_max' => $maxVenta !== null ? round($maxVenta, 2) : null,
            'monto_venta_prom' => $promVenta !== null ? round($promVenta, 2) : null,
            'monto_venta_unitario' => round($nuevoPrecioVenta, 2),
        ]);

        // Consulta SQL Raw equivalente:
        // SELECT * FROM "inventario" WHERE "id_inventario" = :idInventario LIMIT 1;
        return $item->fresh();
    }

    /**
     * Evalúa una lista de ítems de inventario tras una operación de entrada o salida,
     * y si alguno quedó por debajo de su stock mínimo, notifica individualmente a cada admin.
     *
     * @param mixed $items Colección, arreglo de modelos Inventario o IDs de inventario
     * @return void
     */
    public static function verificarYNotificarStockBajo($items): void
    {
        if (empty($items)) {
            return;
        }

        if (!is_iterable($items)) {
            $items = [$items];
        }

        $itemsEnRiesgo = [];

        foreach ($items as $item) {
            if (!$item instanceof Inventario) {
                $item = Inventario::find($item);
            }
            if (!$item) {
                continue;
            }

            // Condición: cantidad_propia estrictamente menor a stock_minimo
            if ($item->stock_minimo !== null && (int) $item->cantidad_propia < (int) $item->stock_minimo) {
                $tipoStr = $item->tipo ? (is_object($item->tipo) ? ($item->tipo->value ?? (string) $item->tipo) : (string) $item->tipo) : 'item';
                $itemsEnRiesgo[$item->id_inventario] = [
                    'sku'             => $item->sku,
                    'nombre'          => $item->nombre,
                    'tipo'            => ucfirst(strtolower($tipoStr)),
                    'cantidad_propia' => (int) $item->cantidad_propia,
                    'stock_minimo'    => (int) $item->stock_minimo,
                ];
            }
        }

        if (empty($itemsEnRiesgo)) {
            return;
        }

        $admins = Admin::with('user')->get();

        foreach ($admins as $admin) {
            $email = $admin->user?->email;
            if (!$email) {
                continue;
            }

            MailerService::enviarCorreo(
                ['to' => [$email]],
                'Alerta: Items de Inventario Bajo Stock Mínimo',
                'emails.stock_bajo',
                [
                    'admin_nombre' => $admin->nombre,
                    'items'        => array_values($itemsEnRiesgo),
                ]
            );
        }
    }
}
