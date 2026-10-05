<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Inventario;
use App\Models\Equipo;
use App\Models\Repuesto;
use App\Models\PagoCompra;
use App\Enums\CompraEstado;
use App\Enums\CompraTipoPago;
use App\Enums\PagoCompraEstado;
use App\Services\InventarioService;
use Illuminate\Support\Facades\DB;

/**
 * Service CompraService
 * 
 * Orquesta el flujo integral de compras a proveedores:
 * - Creación de cabecera con desglose de montos gravados, exentos e IVA.
 * - Registro de líneas de compra (detalles_compras).
 * - Aumento de existencias y recálculo ponderado exacto en inventario.
 * - Generación de unidades físicas serializadas (equipos o repuestos nuevos).
 * - Generación de cronograma de pagos pendientes a proveedores.
 * - Reversión de stock y pagos en caso de eliminación.
 */
class CompraService
{
    /**
     * Retorna todas las compras registradas.
     * Consulta SQL Raw:
     * SELECT * FROM compras;
     */
    public static function getAll()
    {
        return Compra::get();
    }

    /**
     * Obtiene una compra por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM compras WHERE id_compra = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Compra::find($id);
    }

    /**
     * Registra una compra completa:
     * 1. Calcula totales fiscales y monto pendiente si no se proporcionaron.
     * 2. Inserta la cabecera con estado inicial 'por_pagar'.
     * 3. Inserta cada detalle, actualiza inventario y genera serializados.
     * 4. Registra el cronograma de pagos a proveedores en estado 'pendiente'.
     *
     * Consulta SQL Raw:
     * INSERT INTO compras (...) VALUES (...);
     * INSERT INTO detalles_compras (...) VALUES (...);
     * UPDATE inventario SET cantidad_total = ..., cantidad_propia = ... WHERE id_inventario = ...;
     * INSERT INTO equipos / repuestos (...) VALUES (...);
     * INSERT INTO pagos_compras (...) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();

        $detalles = $data['detalles'] ?? [];
        $pagos = $data['pagos'] ?? [];
        unset($data['detalles'], $data['pagos']);

        // 1. Cálculos automáticos de la cabecera si no vienen completos
        if (!isset($data['monto_total']) && !empty($detalles)) {
            $totalGravado = 0.00;
            $totalExento = 0.00;
            $totalIva = 0.00;
            $montoTotal = 0.00;

            foreach ($detalles as &$linea) {
                $cantidad = (int) ($linea['cantidad'] ?? 1);
                $costoUnitario = (float) ($linea['costo_unitario'] ?? 0.00);
                $porcentajeIva = (float) ($linea['porcentaje_iva'] ?? 0.00);

                if (!isset($linea['monto_total_linea_sin_iva'])) {
                    $linea['monto_total_linea_sin_iva'] = $cantidad * $costoUnitario;
                }

                if (!isset($linea['monto_iva'])) {
                    $linea['monto_iva'] = round($linea['monto_total_linea_sin_iva'] * ($porcentajeIva / 100), 2);
                }

                if (!isset($linea['monto_total_linea_con_iva'])) {
                    $linea['monto_total_linea_con_iva'] = $linea['monto_total_linea_sin_iva'] + $linea['monto_iva'];
                }

                if ($porcentajeIva > 0) {
                    $totalGravado += (float) $linea['monto_total_linea_sin_iva'];
                } else {
                    $totalExento += (float) $linea['monto_total_linea_sin_iva'];
                }
                $totalIva += (float) $linea['monto_iva'];
                $montoTotal += (float) $linea['monto_total_linea_con_iva'];
            }
            unset($linea);

            $data['monto_total_gravado'] = $data['monto_total_gravado'] ?? $totalGravado;
            $data['monto_total_exento']  = $data['monto_total_exento'] ?? $totalExento;
            $data['monto_total_iva']     = $data['monto_total_iva'] ?? $totalIva;
            $data['monto_total']         = $data['monto_total'] ?? $montoTotal;
            $data['monto_pendiente']     = $data['monto_pendiente'] ?? $montoTotal;
        }

        // Una compra siempre nace con estado "por_pagar"
        $data['estado'] = CompraEstado::POR_PAGAR;

        // 2. Crear cabecera de Compra
        $compra = Compra::create($data);

        // 3. Procesar líneas de compra, serializados e inventario
        if (!empty($detalles) && is_array($detalles)) {
            foreach ($detalles as $lineaData) {
                $seriales = $lineaData['seriales'] ?? [];
                unset($lineaData['seriales']);

                $lineaData['id_compra'] = $compra->id_compra;
                $detalle = DetalleCompra::create($lineaData);

                $item = Inventario::find($detalle->id_inventario);
                if ($item) {
                    self::actualizarInventarioTrasCompra($item, $detalle->cantidad, (float) $detalle->costo_unitario);
                    self::generarSerializados($detalle, $item, $seriales);
                }
            }
        }

        // 4. Generación de Pagos a Compras a partir del arreglo de pagos (todos nacen con estado "pendiente")
        self::generarPagosCompra($compra, $pagos);

        DB::commit();

        return $compra->load(['detalles', 'pagosCompras']);
    }

    /**
     * Actualiza atributos de la compra.
     * Consulta SQL Raw:
     * UPDATE compras SET num_factura_boleta = ..., fecha_compra = ... WHERE id_compra = $id;
     */
    public static function update($id, $data)
    {
        $compra = Compra::find($id);
        if (!$compra) {
            return null;
        }

        DB::beginTransaction();

        $compra->update($data);

        DB::commit();

        return $compra->load(['detalles', 'pagosCompras']);
    }

    /**
     * Elimina una compra revirtiendo sus efectos:
     * - Elimina los pagos asociados.
     * - Elimina los equipos y repuestos serializados generados.
     * - Descuenta las existencias ingresadas del inventario.
     * - Elimina las líneas de detalle y la cabecera de compra.
     *
     * Consulta SQL Raw:
     * DELETE FROM pagos_compras WHERE id_compra = $id;
     * DELETE FROM equipos WHERE id_detalle_compra IN (...);
     * DELETE FROM repuestos WHERE id_detalle_compra IN (...);
     * UPDATE inventario SET cantidad_total = ..., cantidad_propia = ... WHERE id_inventario = ...;
     * DELETE FROM detalles_compras WHERE id_compra = $id;
     * DELETE FROM compras WHERE id_compra = $id;
     */
    public static function delete($id)
    {
        $compra = Compra::find($id);
        if (!$compra) {
            return null;
        }

        DB::beginTransaction();

        // Consulta SQL Raw equivalente:
        // DELETE FROM "pagos_compras" WHERE "id_compra" = :id;
        PagoCompra::where('id_compra', $id)->delete();

        // Consulta SQL Raw equivalente:
        // SELECT * FROM "detalles_compras" WHERE "id_compra" = :id;
        $detalles = DetalleCompra::where('id_compra', $id)->get();

        foreach ($detalles as $detalle) {
            // Consulta SQL Raw equivalente:
            // DELETE FROM "equipos" WHERE "id_detalle_compra" = :id_detalle_compra;
            Equipo::where('id_detalle_compra', $detalle->id_detalle_compra)->delete();
            // Consulta SQL Raw equivalente:
            // DELETE FROM "repuestos" WHERE "id_detalle_compra" = :id_detalle_compra;
            Repuesto::where('id_detalle_compra', $detalle->id_detalle_compra)->delete();

            $item = Inventario::find($detalle->id_inventario);
            if ($item) {
                $nuevaCantidadTotal = max(0, (int) $item->cantidad_total - (int) $detalle->cantidad);
                $nuevaCantidadPropia = max(0, (int) $item->cantidad_propia - (int) $detalle->cantidad);

                $item->update([
                    'cantidad_total'  => $nuevaCantidadTotal,
                    'cantidad_propia' => $nuevaCantidadPropia,
                ]);

                // Recalcular métricas de compra en inventario
                InventarioService::recalcularMetricasCompletas($item->id_inventario);
            }

            $detalle->delete();
        }

        $compra->delete();

        DB::commit();

        return $compra;
    }

    /**
     * Genera los registros en pagos_compras al registrar una nueva compra.
     * Todos los pagos nacen en estado 'pendiente'.
     *
     * Consulta SQL Raw:
     * INSERT INTO pagos_compras (id_compra, id_admin, monto_a_pagar, estado, ...) VALUES (...);
     */
    public static function generarPagosCompra(Compra $compra, array $pagos = [])
    {
        $montoTotal = (float) $compra->monto_total;

        foreach ($pagos as $pago) {
            $montoCuota = (float) ($pago['monto_a_pagar'] ?? 0.00);
            $porcentaje = isset($pago['porcentaje_monto_total'])
                ? (float) $pago['porcentaje_monto_total']
                : ($montoTotal > 0 ? round(($montoCuota / $montoTotal) * 100, 2) : 0);

            PagoCompra::create([
                'id_compra'              => $compra->id_compra,
                'id_admin'               => $compra->id_admin,
                'monto_a_pagar'          => $montoCuota,
                'porcentaje_monto_total' => $porcentaje,
                'fecha_pago'             => null,
                'fecha_pago_acordada'    => $pago['fecha_pago_acordada'] ?? now(),
                'metodo_pago'            => null,
                'num_referencia'         => null,
                'comprobante'            => null,
                'estado'                 => PagoCompraEstado::PENDIENTE->value,
            ]);
        }
    }

    /**
     * Aumenta el stock y recalcula métricas ponderadas en InventarioService.
     */
    public static function actualizarInventarioTrasCompra(Inventario $item, int $cantidadComprada, float $costoUnitario)
    {
        InventarioService::actualizarPorCompra($item, $cantidadComprada, $costoUnitario);
    }

    /**
     * Genera automáticamente las piezas físicas serializadas correspondientes a la compra
     * (equipos o repuestos nuevos con propietario = true y costo_adquisicion).
     *
     * Consulta SQL Raw:
     * INSERT INTO equipos (id_modelo, id_detalle_compra, serial, nombre) VALUES (...);
     * INSERT INTO repuestos (id_inventario, id_detalle_compra, serial, nombre, estado, propietario, costo_adquisicion, costo_total) VALUES (...);
     */
    public static function generarSerializados(DetalleCompra $detalle, Inventario $item, array $seriales = [])
    {
        $tipo = $item->tipo instanceof \App\Enums\InventarioTipo ? $item->tipo->value : (string) $item->tipo;

        if ($tipo === 'equipo') {
            for ($i = 0; $i < $detalle->cantidad; $i++) {
                $serialData = $seriales[$i] ?? [];
                Equipo::create([
                    'id_modelo'         => $item->id_modelo,
                    'id_detalle_compra' => $detalle->id_detalle_compra,
                    'serial'            => $serialData['serial'] ?? null,
                    'nombre'            => $serialData['nombre'] ?? ($item->nombre . ' #' . ($i + 1)),
                ]);
            }
        } elseif (in_array($tipo, ['compresor', 'valvula'])) {
            for ($i = 0; $i < $detalle->cantidad; $i++) {
                $serialData = $seriales[$i] ?? [];
                Repuesto::create([
                    'id_inventario'         => $item->id_inventario,
                    'id_detalle_compra'     => $detalle->id_detalle_compra,
                    'serial'                => $serialData['serial'] ?? null,
                    'nombre'                => $serialData['nombre'] ?? ($item->nombre . ' #' . ($i + 1)),
                    'estado'                => 'nuevo',
                    'propietario'           => true,
                    'costo_adquisicion'     => $detalle->costo_unitario,
                    'costo_reparacion_base' => 0.00,
                    'costo_total'           => $detalle->costo_unitario,
                ]);
            }
        }
    }
}
