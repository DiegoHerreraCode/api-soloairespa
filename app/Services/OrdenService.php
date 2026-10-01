<?php

namespace App\Services;

use App\Models\Orden;
use App\Models\DetalleOrden;
use App\Models\Inventario;
use App\Models\Repuesto;
use App\Models\TipoOrden;
use App\Services\InventarioService;
use App\Enums\RepuestoEstado;
use App\Enums\OrdenEstadoOperativo;
use App\Enums\OrdenEstadoAdmin;
use Illuminate\Support\Facades\DB;

class OrdenService
{
    public static function getAll()
    {
        return Orden::get();
    }

    public static function getOne($id)
    {
        return Orden::find($id);
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $detalles = $data['detalles'] ?? [];
        $repuestosEntrantes = $data['repuestos_entrantes'] ?? [];
        unset($data['detalles'], $data['repuestos_entrantes']);

        $tipoOrden = TipoOrden::find($data['id_tipo_orden']);
        $nombreTipo = $tipoOrden ? strtolower(trim($tipoOrden->nombre)) : '';

        // 1. Estados iniciales por defecto de la orden
        // Ventas y Recambios nacen directamente como "finalizada" operativamente
        if (in_array($nombreTipo, ['venta', 'recambio'])) {
            $data['estado_operativo'] = OrdenEstadoOperativo::FINALIZADA->value;
        } else {
            $data['estado_operativo'] = $data['estado_operativo'] ?? OrdenEstadoOperativo::EN_ESPERA->value;
        }

        $data['estado_administrativo'] = $data['estado_administrativo'] ?? OrdenEstadoAdmin::PENDIENTE_PAGO->value;
        $data['fecha_creacion']        = $data['fecha_creacion'] ?? now();
        $data['last_update']           = now();

        $orden = Orden::create($data);

        // 2. Procesar según el tipo de orden
        if ($nombreTipo === 'venta') {
            self::procesarVenta($orden, $detalles);
        } elseif ($nombreTipo === 'recambio') {
            self::procesarRecambio($orden, $detalles);
        } elseif ($nombreTipo === 'reparacion') {
            self::procesarRecepcionReparacion($orden, $repuestosEntrantes);
        }

        DB::commit();

        return $orden->fresh();
    }

    public static function update($id, $data)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return null;
        }

        DB::beginTransaction();

        $data['last_update'] = now();

        // Si se está anulando la orden
        if (isset($data['estado_operativo']) && $data['estado_operativo'] === 'anulada') {
            $data['fecha_anulacion'] = now();
        }

        $estadoAnterior = $orden->estado_administrativo instanceof OrdenEstadoAdmin ? $orden->estado_administrativo->value : (string) $orden->estado_administrativo;
        $orden->update($data);

        if (isset($data['estado_administrativo']) && $data['estado_administrativo'] === 'pagada' && $estadoAnterior !== 'pagada') {
            self::liquidarSalidaOrdenPagada($orden->fresh());
        }

        DB::commit();

        return $orden->fresh();
    }

    public static function delete($id)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return null;
        }

        DB::beginTransaction();
        DetalleOrden::where('id_orden', $id)->delete();
        $orden->delete();
        DB::commit();

        return $orden;
    }

    /**
     * Procesa líneas de Venta: descuenta stock de inventario propio y calcula totales.
     */
    public static function procesarVenta(Orden $orden, array $detalles)
    {
        $totalGravado = 0.00;
        $totalExento = 0.00;
        $totalIva = 0.00;
        $totalNeto = 0.00;

        foreach ($detalles as $linea) {
            $item = isset($linea['id_inventario_repuesto_saliente'])
                ? Inventario::find($linea['id_inventario_repuesto_saliente'])
                : (isset($linea['id_inventario_insumo_saliente']) ? Inventario::find($linea['id_inventario_insumo_saliente']) : null);

            $cantidad = (int) ($linea['cantidad'] ?? 1);
            $precioUnitario = (float) ($linea['precio_unitario'] ?? ($item ? $item->monto_venta_unitario : 0.00));
            $porcentajeIva = (float) ($linea['porcentaje_iva'] ?? ($item ? $item->porcentaje_iva : 19.00));

            $subtotalLinea = round($cantidad * $precioUnitario, 2);
            $ivaLinea = round($subtotalLinea * ($porcentajeIva / 100), 2);
            $totalLinea = $subtotalLinea + $ivaLinea;

            DetalleOrden::create([
                'id_orden'                        => $orden->id_orden,
                'id_inventario_insumo_saliente'   => $linea['id_inventario_insumo_saliente'] ?? null,
                'id_inventario_repuesto_saliente' => $linea['id_inventario_repuesto_saliente'] ?? null,
                'id_inventario_repuesto_entrante' => null,
                'cantidad'                        => $cantidad,
                'precio_unitario'                 => $precioUnitario,
                'monto_tasacion'                  => 0.00,
                'monto_total_linea_sin_iva'       => $subtotalLinea,
                'porcentaje_iva'                  => $porcentajeIva,
                'monto_iva'                       => $ivaLinea,
                'monto_total_linea_con_iva'       => $totalLinea,
            ]);

            if ($porcentajeIva > 0) {
                $totalGravado += $subtotalLinea;
            } else {
                $totalExento += $subtotalLinea;
            }
            $totalIva += $ivaLinea;
            $totalNeto += $totalLinea;
        }

        $orden->update([
            'monto_total_gravado' => round($totalGravado, 2),
            'monto_total_exento'  => round($totalExento, 2),
            'monto_total_iva'     => round($totalIva, 2),
            'monto_total'         => round($totalNeto, 2),
            'monto_pendiente'     => round($totalNeto, 2),
        ]);
    }

    /**
     * Procesa Recambio:
     * - Sale repuesto del taller a precio_unitario (descuenta stock propio).
     * - Entra repuesto malo del cliente a monto_tasacion, propiedad de la empresa y estado 'pendiente_reparacion' (suma stock propio).
     * - El cliente paga la diferencia: (precio_unitario - monto_tasacion) + IVA.
     */
    public static function procesarRecambio(Orden $orden, array $detalles)
    {
        $totalGravado = 0.00;
        $totalExento = 0.00;
        $totalIva = 0.00;
        $totalNeto = 0.00;

        foreach ($detalles as $linea) {
            $itemSaliente = isset($linea['id_inventario_repuesto_saliente']) ? Inventario::find($linea['id_inventario_repuesto_saliente']) : null;
            $itemEntrante = isset($linea['id_inventario_repuesto_entrante']) ? Inventario::find($linea['id_inventario_repuesto_entrante']) : null;

            $cantidad = (int) ($linea['cantidad'] ?? 1);
            $precioUnitario = (float) ($linea['precio_unitario'] ?? ($itemSaliente ? $itemSaliente->monto_venta_unitario : 0.00));
            $montoTasacion  = (float) ($linea['monto_tasacion'] ?? 0.00);
            $porcentajeIva  = (float) ($linea['porcentaje_iva'] ?? 19.00);

            // La base imponible para el cliente es la diferencia tasada
            $diferenciaUnitario = max(0.00, $precioUnitario - $montoTasacion);
            $subtotalLinea = round($cantidad * $diferenciaUnitario, 2);
            $ivaLinea = round($subtotalLinea * ($porcentajeIva / 100), 2);
            $totalLinea = $subtotalLinea + $ivaLinea;

            DetalleOrden::create([
                'id_orden'                        => $orden->id_orden,
                'id_inventario_insumo_saliente'   => null,
                'id_inventario_repuesto_saliente' => $linea['id_inventario_repuesto_saliente'] ?? null,
                'id_inventario_repuesto_entrante' => $linea['id_inventario_repuesto_entrante'] ?? null,
                'cantidad'                        => $cantidad,
                'precio_unitario'                 => $precioUnitario,
                'monto_tasacion'                  => $montoTasacion,
                'monto_total_linea_sin_iva'       => $subtotalLinea,
                'porcentaje_iva'                  => $porcentajeIva,
                'monto_iva'                       => $ivaLinea,
                'monto_total_linea_con_iva'       => $totalLinea,
            ]);

            // REPUESTO ENTRANTE: pasa a ser propiedad del taller (propietario = true, pendiente_reparacion, costo = tasacion)
            if ($itemEntrante) {
                $nuevoTotal = (int) $itemEntrante->cantidad_total + $cantidad;
                $nuevoPropio = (int) $itemEntrante->cantidad_propia + $cantidad;
                $itemEntrante->update([
                    'cantidad_total'  => $nuevoTotal,
                    'cantidad_propia' => $nuevoPropio,
                ]);

                Repuesto::create([
                    'id_inventario'     => $itemEntrante->id_inventario,
                    'serial'            => $linea['serial_entrante'] ?? null,
                    'nombre'            => $linea['nombre_entrante'] ?? ($itemEntrante->nombre . ' (Recambio)'),
                    'estado'            => RepuestoEstado::PENDIENTE_REPARACION->value,
                    'propietario'       => true,
                    'costo_adquisicion' => $montoTasacion,
                    'id_orden_entrada'  => $orden->id_orden,
                ]);
            }

            if ($porcentajeIva > 0) {
                $totalGravado += $subtotalLinea;
            } else {
                $totalExento += $subtotalLinea;
            }
            $totalIva += $ivaLinea;
            $totalNeto += $totalLinea;
        }

        $orden->update([
            'monto_total_gravado' => round($totalGravado, 2),
            'monto_total_exento'  => round($totalExento, 2),
            'monto_total_iva'     => round($totalIva, 2),
            'monto_total'         => round($totalNeto, 2),
            'monto_pendiente'     => round($totalNeto, 2),
        ]);
    }

    /**
     * Procesa Recepción de Reparación directa:
     * - Entra repuesto del cliente (propietario = false, estado = 'pendiente_reparacion').
     * - En inventario suma cantidad_total y cantidad_cliente.
     */
    public static function procesarRecepcionReparacion(Orden $orden, array $repuestosEntrantes)
    {
        foreach ($repuestosEntrantes as $itemData) {
            $item = Inventario::find($itemData['id_inventario']);
            if ($item) {
                $item->update([
                    'cantidad_total'   => (int) $item->cantidad_total + 1,
                    'cantidad_cliente' => (int) $item->cantidad_cliente + 1,
                ]);

                Repuesto::create([
                    'id_inventario'     => $item->id_inventario,
                    'serial'            => $itemData['serial'] ?? null,
                    'nombre'            => $itemData['nombre'] ?? ($item->nombre . ' (Cliente)'),
                    'estado'            => RepuestoEstado::PENDIENTE_REPARACION->value,
                    'propietario'       => false,
                    'costo_adquisicion' => 0.00,
                    'id_orden_entrada'  => $orden->id_orden,
                                    ]);
            }
        }
    }

    /**
     * Liquida la salida contable y fisica de una orden cuando pasa a estado 'pagada':
     * - Asigna id_orden_salida, monto_venta_real y utilidad en repuestos.
     * - Descuenta stock de inventario (propio o cliente segun el caso).
     * - Actualiza las metricas historicas de venta en inventario.
     */
    public static function liquidarSalidaOrdenPagada(Orden $orden)
    {
        $tipoOrden = TipoOrden::find($orden->id_tipo_orden);
        $nombreTipo = $tipoOrden ? strtolower(trim($tipoOrden->nombre)) : '';

        if ($nombreTipo === 'reparacion') {
            // Repuestos del cliente que ingresaron en esta orden
            $repuestos = Repuesto::where('id_orden_entrada', $orden->id_orden)->get();
            $detalles = DetalleOrden::where('id_orden', $orden->id_orden)->get();

            foreach ($repuestos as $index => $repuesto) {
                // Solo liquidar si aun no tenia asignada id_orden_salida
                if (!empty($repuesto->id_orden_salida)) {
                    continue;
                }

                $detalle = $detalles->firstWhere('id_inventario_repuesto_saliente', $repuesto->id_inventario) ?? ($detalles[$index] ?? null);
                $montoVentaReal = $detalle ? (float) $detalle->precio_unitario : (float) ($repuesto->costo_reparacion_con_ganancia ?? 0.00);
                $costoTotal = (float) $repuesto->costo_total;
                $utilidad = round($montoVentaReal - $costoTotal, 2);

                $repuesto->update([
                    'id_orden_salida'  => $orden->id_orden,
                    'monto_venta_real' => $montoVentaReal,
                    'utilidad'         => $utilidad,
                ]);

                // Actualizar inventario: disminuye stock total y stock cliente, y actualiza metricas de venta
                $item = Inventario::find($repuesto->id_inventario);
                if ($item) {
                    InventarioService::actualizarPorReparacionEntrega($item, 1, $montoVentaReal);
                }
            }
        } elseif (in_array($nombreTipo, ['venta', 'recambio'])) {
            $detalles = DetalleOrden::where('id_orden', $orden->id_orden)->get();

            foreach ($detalles as $linea) {
                $idInventario = $linea->id_inventario_repuesto_saliente ?? $linea->id_inventario_insumo_saliente;
                $item = $idInventario ? Inventario::find($idInventario) : null;
                $cantidad = (int) $linea->cantidad;
                $precioUnitario = (float) $linea->precio_unitario;

                // Descontar inventario propio y actualizar estadisticas de venta
                if ($item) {
                    InventarioService::actualizarPorVenta($item, $cantidad, $precioUnitario);
                }

                // Si es un repuesto, buscar repuestos disponibles para asignar la salida
                if ($linea->id_inventario_repuesto_saliente) {
                    $repuestosAsignar = Repuesto::where('id_inventario', $linea->id_inventario_repuesto_saliente)
                        ->whereNull('id_orden_salida')
                        ->where('propietario', true)
                        ->limit($cantidad)
                        ->get();

                    foreach ($repuestosAsignar as $rep) {
                        $costoTotal = (float) $rep->costo_total;
                        $rep->update([
                            'id_orden_salida'  => $orden->id_orden,
                            'monto_venta_real' => $precioUnitario,
                            'utilidad'         => round($precioUnitario - $costoTotal, 2),
                        ]);
                    }
                }
            }
        }
    }
}
