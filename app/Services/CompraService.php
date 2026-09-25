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
use Illuminate\Support\Facades\DB;

class CompraService
{
    public static function getAll()
    {
        // return Compra::with(['detalles', 'pagosCompras'])->get();
        return Compra::with(['detalles.inventario', 'pagosCompras'])->get();

        // return Compra::get();
    }

    public static function getOne($id)
    {
        return Compra::with(['detalles', 'pagosCompras'])->find($id);
    }

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
            $data['monto_total_exento'] = $data['monto_total_exento'] ?? $totalExento;
            $data['monto_total_iva'] = $data['monto_total_iva'] ?? $totalIva;
            $data['monto_total'] = $data['monto_total'] ?? $montoTotal;
            $data['monto_pendiente'] = $data['monto_pendiente'] ?? $montoTotal;
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

    public static function delete($id)
    {
        $compra = Compra::find($id);
        if (!$compra) {
            return null;
        }

        DB::beginTransaction();

        PagoCompra::where('id_compra', $id)->delete();

        $detalles = DetalleCompra::where('id_compra', $id)->get();

        foreach ($detalles as $detalle) {
            Equipo::where('id_detalle_compra', $detalle->id_detalle_compra)->delete();
            Repuesto::where('id_detalle_compra', $detalle->id_detalle_compra)->delete();

            $item = Inventario::find($detalle->id_inventario);
            if ($item) {
                $nuevaCantidadTotal = max(0, (int) $item->cantidad_total - (int) $detalle->cantidad);
                $nuevaCantidadPropia = max(0, (int) $item->cantidad_propia - (int) $detalle->cantidad);

                $item->update([
                    'cantidad_total' => $nuevaCantidadTotal,
                    'cantidad_propia' => $nuevaCantidadPropia,
                ]);
            }

            $detalle->delete();
        }

        $compra->delete();

        DB::commit();

        return $compra;
    }

    /**
     * Genera los registros en pagos_compras al registrar una nueva compra.
     * Siempre se recibe un arreglo de 1 a N pagos desde el front.
     * Todos los pagos nacen siempre en estado "pendiente" con valores iniciales null para los campos de pago.
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
                'id_compra' => $compra->id_compra,
                'id_admin' => $compra->id_admin,
                'monto_a_pagar' => $montoCuota,
                'porcentaje_monto_total' => $porcentaje,
                'fecha_pago' => null,
                'fecha_pago_acordada' => $pago['fecha_pago_acordada'] ?? now(),
                'metodo_pago' => null,
                'num_referencia' => null,
                'comprobante' => null,
                'estado' => PagoCompraEstado::PENDIENTE->value,
            ]);
        }
    }

    /**
     * Actualiza stock y Precio Promedio Ponderado de Inventario.
     */
    public static function actualizarInventarioTrasCompra(Inventario $item, int $cantidadComprada, float $costoUnitario)
    {
        $stockAnterior = (int) $item->cantidad_total;
        $costoAnterior = (float) $item->monto_compra_prom;

        $nuevoStockTotal = $stockAnterior + $cantidadComprada;
        $nuevoStockPropio = (int) $item->cantidad_propia + $cantidadComprada;

        if ($nuevoStockTotal > 0) {
            $nuevoCostoPromedio = (($stockAnterior * $costoAnterior) + ($cantidadComprada * $costoUnitario)) / $nuevoStockTotal;
        } else {
            $nuevoCostoPromedio = $costoUnitario;
        }

        $porcentajeGanancia = (float) $item->porcentaje_ganancia;
        if ($porcentajeGanancia > 0 && $porcentajeGanancia < 100) {
            $nuevoPrecioVenta = $nuevoCostoPromedio / (1 - ($porcentajeGanancia / 100));
        } else {
            $nuevoPrecioVenta = (float) $item->precio_venta;
        }

        $item->update([
            'cantidad_total' => $nuevoStockTotal,
            'cantidad_propia' => $nuevoStockPropio,
            'monto_compra_prom' => round($nuevoCostoPromedio, 2),
            'precio_venta' => round($nuevoPrecioVenta, 2),
        ]);
    }

    /**
     * Genera los registros de Equipos o Repuestos según el tipo de inventario.
     */
    public static function generarSerializados(DetalleCompra $detalle, Inventario $item, array $seriales = [])
    {
        $tipo = $item->tipo instanceof \App\Enums\InventarioTipo ? $item->tipo->value : (string) $item->tipo;

        if ($tipo === 'equipo') {
            for ($i = 0; $i < $detalle->cantidad; $i++) {
                $serialData = $seriales[$i] ?? [];
                Equipo::create([
                    'id_modelo' => $item->id_modelo,
                    'id_detalle_compra' => $detalle->id_detalle_compra,
                    'serial' => $serialData['serial'] ?? null,
                    'nombre' => $serialData['nombre'] ?? ($item->nombre . ' #' . ($i + 1)),
                    'is_deleted' => false,
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
                    'is_deleted'            => false,
                ]);
            }
        }
    }
}