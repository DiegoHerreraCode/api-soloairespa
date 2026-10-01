<?php

namespace App\Services;

use App\Models\Orden;
use App\Models\DetalleOrden;
use App\Models\Inventario;
use App\Models\Repuesto;
use App\Models\TipoOrden;
use App\Enums\RepuestoEstado;
use App\Enums\OrdenEstadoOperativo;
use App\Enums\OrdenEstadoAdmin;
use App\Services\InventarioService;
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
                if (!empty($data['fecha_creacion'])) {
            $data['fecha_creacion'] = strlen($data['fecha_creacion']) === 10
                ? $data['fecha_creacion'] . ' ' . now()->format('H:i:s')
                : $data['fecha_creacion'];
        } else {
            $data['fecha_creacion'] = now();
        }

        if (!empty($data['fecha_entrega_reparacion'])) {
            $data['fecha_entrega_reparacion'] = strlen($data['fecha_entrega_reparacion']) === 10
                ? $data['fecha_entrega_reparacion'] . ' ' . now()->format('H:i:s')
                : $data['fecha_entrega_reparacion'];
        }
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

                if (!empty($data['fecha_entrega_reparacion'])) {
            $data['fecha_entrega_reparacion'] = strlen($data['fecha_entrega_reparacion']) === 10
                ? $data['fecha_entrega_reparacion'] . ' ' . now()->format('H:i:s')
                : $data['fecha_entrega_reparacion'];
        }

        $orden->update($data);

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
     * Procesa líneas de Venta:
     * - Para insumos: crea 1 registro en detalles_ordenes con la cantidad y descuenta stock propio.
     * - Para repuestos serializados: crea N registros en detalles_ordenes (cantidad = 1), marca id_orden_salida,
     *   monto_venta_real y utilidad en cada repuesto exacto, y descuenta stock e historiales de venta.
     */
    public static function procesarVenta(Orden $orden, array $detalles)
    {
        $totalGravado = 0.00;
        $totalExento = 0.00;
        $totalIva = 0.00;
        $totalNeto = 0.00;

        foreach ($detalles as $linea) {
            // CASO A: INSUMOS (no serializados)
            if (!empty($linea['id_inventario_insumo_saliente'])) {
                $item = Inventario::find($linea['id_inventario_insumo_saliente']);
                $cantidad = (int) ($linea['cantidad'] ?? 1);
                $precioUnitario = (float) ($linea['precio_unitario'] ?? ($item ? $item->monto_venta_unitario : 0.00));
                $porcentajeIva = (float) ($linea['porcentaje_iva'] ?? ($item ? $item->porcentaje_iva : 19.00));

                $subtotalLinea = round($cantidad * $precioUnitario, 2);
                $ivaLinea = round($subtotalLinea * ($porcentajeIva / 100), 2);
                $totalLinea = $subtotalLinea + $ivaLinea;

                DetalleOrden::create([
                    'id_orden'                        => $orden->id_orden,
                    'id_inventario_insumo_saliente'   => $linea['id_inventario_insumo_saliente'],
                    'id_inventario_repuesto_saliente' => null,
                    'id_repuesto_saliente'            => null,
                    'id_inventario_repuesto_entrante' => null,
                    'id_repuesto_entrante'            => null,
                    'cantidad'                        => $cantidad,
                    'precio_unitario'                 => $precioUnitario,
                    'monto_tasacion'                  => 0.00,
                    'monto_total_linea_sin_iva'       => $subtotalLinea,
                    'porcentaje_iva'                  => $porcentajeIva,
                    'monto_iva'                       => $ivaLinea,
                    'monto_total_linea_con_iva'       => $totalLinea,
                ]);

                // Descontar inmediatamente inventario y registrar metricas de venta
                if ($item) {
                    InventarioService::actualizarPorVenta($item, $cantidad, $precioUnitario);
                }

                if ($porcentajeIva > 0) {
                    $totalGravado += $subtotalLinea;
                } else {
                    $totalExento += $subtotalLinea;
                }
                $totalIva += $ivaLinea;
                $totalNeto += $totalLinea;
            }

            // CASO B: REPUESTOS SERIALIZADOS (compresor o valvula)
            if (!empty($linea['id_inventario_repuesto_saliente'])) {
                $item = Inventario::find($linea['id_inventario_repuesto_saliente']);
                $porcentajeIva = (float) ($linea['porcentaje_iva'] ?? ($item ? $item->porcentaje_iva : 19.00));
                $precioSugerido = (float) ($linea['precio_unitario'] ?? ($item ? $item->monto_venta_unitario : 0.00));

                $listaRepuestosSalientes = [];

                if (!empty($linea['repuestos_salientes']) && is_array($linea['repuestos_salientes'])) {
                    foreach ($linea['repuestos_salientes'] as $rs) {
                        if (is_array($rs)) {
                            $listaRepuestosSalientes[] = [
                                'id_repuesto'     => $rs['id_repuesto'],
                                'precio_unitario' => (float) ($rs['precio_unitario'] ?? $precioSugerido),
                            ];
                        } else {
                            $listaRepuestosSalientes[] = [
                                'id_repuesto'     => (int) $rs,
                                'precio_unitario' => $precioSugerido,
                            ];
                        }
                    }
                } elseif (!empty($linea['id_repuesto_saliente'])) {
                    $listaRepuestosSalientes[] = [
                        'id_repuesto'     => (int) $linea['id_repuesto_saliente'],
                        'precio_unitario' => $precioSugerido,
                    ];
                } else {
                    $cantRequerida = (int) ($linea['cantidad'] ?? 1);
                    $repuestosDisponibles = Repuesto::where('id_inventario', $linea['id_inventario_repuesto_saliente'])
                        ->whereNull('id_orden_salida')
                        ->where('propietario', true)
                        ->limit($cantRequerida)
                        ->get();

                    foreach ($repuestosDisponibles as $rd) {
                        $listaRepuestosSalientes[] = [
                            'id_repuesto'     => $rd->id_repuesto,
                            'precio_unitario' => $precioSugerido,
                        ];
                    }
                }

                // Crear 1 registro en detalles_ordenes por CADA repuesto serializado
                foreach ($listaRepuestosSalientes as $itemRep) {
                    $repuesto = Repuesto::find($itemRep['id_repuesto']);
                    $precioUnitario = $itemRep['precio_unitario'];
                    $subtotalLinea = $precioUnitario;
                    $ivaLinea = round($subtotalLinea * ($porcentajeIva / 100), 2);
                    $totalLinea = $subtotalLinea + $ivaLinea;

                    DetalleOrden::create([
                        'id_orden'                        => $orden->id_orden,
                        'id_inventario_repuesto_saliente' => $linea['id_inventario_repuesto_saliente'],
                        'id_repuesto_saliente'            => $itemRep['id_repuesto'],
                        'id_inventario_insumo_saliente'   => null,
                        'id_inventario_repuesto_entrante' => null,
                        'id_repuesto_entrante'            => null,
                        'cantidad'                        => 1,
                        'precio_unitario'                 => $precioUnitario,
                        'monto_tasacion'                  => 0.00,
                        'monto_total_linea_sin_iva'       => $subtotalLinea,
                        'porcentaje_iva'                  => $porcentajeIva,
                        'monto_iva'                       => $ivaLinea,
                        'monto_total_linea_con_iva'       => $totalLinea,
                    ]);

                    // Descontar repuesto serializado inmediatamente al crear la orden
                    if ($repuesto) {
                        $costoTotal = (float) $repuesto->costo_total;
                        $repuesto->update([
                            'id_orden_salida'  => $orden->id_orden,
                            'monto_venta_real' => $precioUnitario,
                            'utilidad'         => round($precioUnitario - $costoTotal, 2),
                        ]);
                    }

                    // Actualizar inventario por cada unidad vendida
                    if ($item) {
                        InventarioService::actualizarPorVenta($item, 1, $precioUnitario);
                    }

                    if ($porcentajeIva > 0) {
                        $totalGravado += $subtotalLinea;
                    } else {
                        $totalExento += $subtotalLinea;
                    }
                    $totalIva += $ivaLinea;
                    $totalNeto += $totalLinea;
                }
            }
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
     * - Sale repuesto del taller a precio_unitario (descuenta stock propio y asigna id_orden_salida, monto_venta_real, utilidad).
     * - Entra repuesto malo del cliente a monto_tasacion (propietario = true, pendiente_reparacion, suma stock propio) y asigna id_repuesto_entrante.
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

            $cantidad = 1;
            $precioUnitario = (float) ($linea['precio_unitario'] ?? ($itemSaliente ? $itemSaliente->monto_venta_unitario : 0.00));
            $montoTasacion  = (float) ($linea['monto_tasacion'] ?? 0.00);
            $porcentajeIva  = (float) ($linea['porcentaje_iva'] ?? 19.00);

            $diferenciaUnitario = max(0.00, $precioUnitario - $montoTasacion);
            $subtotalLinea = round($diferenciaUnitario, 2);
            $ivaLinea = round($subtotalLinea * ($porcentajeIva / 100), 2);
            $totalLinea = $subtotalLinea + $ivaLinea;

            // 1. REPUESTO SALIENTE: identificar, descontar stock propio y actualizar metricas
            $idRepuestoSaliente = $linea['id_repuesto_saliente'] ?? null;
            if (!$idRepuestoSaliente && !empty($linea['id_inventario_repuesto_saliente'])) {
                $repDisponible = Repuesto::where('id_inventario', $linea['id_inventario_repuesto_saliente'])
                    ->whereNull('id_orden_salida')
                    ->where('propietario', true)
                    ->first();
                if ($repDisponible) {
                    $idRepuestoSaliente = $repDisponible->id_repuesto;
                }
            }

            if ($idRepuestoSaliente) {
                $repSaliente = Repuesto::find($idRepuestoSaliente);
                if ($repSaliente) {
                    $costoTotal = (float) $repSaliente->costo_total;
                    $repSaliente->update([
                        'id_orden_salida'  => $orden->id_orden,
                        'monto_venta_real' => $precioUnitario,
                        'utilidad'         => round($precioUnitario - $costoTotal, 2),
                    ]);
                }
            }

            if ($itemSaliente) {
                InventarioService::actualizarPorVenta($itemSaliente, 1, $precioUnitario);
            }

            // 2. REPUESTO ENTRANTE: pasa a ser propiedad del taller
            // Si el item entrante es el mismo item saliente, refrescar el modelo para tener el stock ya descontado
            if ($itemEntrante && $itemSaliente && $itemEntrante->id_inventario === $itemSaliente->id_inventario) {
                $itemEntrante->refresh();
            }

            $idRepuestoEntranteCreado = null;
            if ($itemEntrante) {
                $nuevoTotal = (int) $itemEntrante->cantidad_total + 1;
                $nuevoPropio = (int) $itemEntrante->cantidad_propia + 1;
                $itemEntrante->update([
                    'cantidad_total'  => $nuevoTotal,
                    'cantidad_propia' => $nuevoPropio,
                ]);

                $repEntrante = Repuesto::create([
                    'id_inventario'     => $itemEntrante->id_inventario,
                    'serial'            => $linea['serial_entrante'] ?? null,
                    'nombre'            => $linea['nombre_entrante'] ?? ($itemEntrante->nombre . ' (Recambio)'),
                    'estado'            => RepuestoEstado::PENDIENTE_REPARACION->value,
                    'propietario'       => true,
                    'costo_adquisicion' => $montoTasacion,
                    'id_orden_entrada'  => $orden->id_orden,
                ]);
                $idRepuestoEntranteCreado = $repEntrante->id_repuesto;
            }

            // 3. Crear registro en detalles_ordenes con id_repuesto_saliente e id_repuesto_entrante
            DetalleOrden::create([
                'id_orden'                        => $orden->id_orden,
                'id_inventario_insumo_saliente'   => null,
                'id_inventario_repuesto_saliente' => $linea['id_inventario_repuesto_saliente'] ?? null,
                'id_repuesto_saliente'            => $idRepuestoSaliente,
                'id_inventario_repuesto_entrante' => $linea['id_inventario_repuesto_entrante'] ?? null,
                'id_repuesto_entrante'            => $idRepuestoEntranteCreado,
                'cantidad'                        => 1,
                'precio_unitario'                 => $precioUnitario,
                'monto_tasacion'                  => $montoTasacion,
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
}