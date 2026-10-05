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
use App\Enums\TallerEstado;
use App\Enums\ReparacionEstado;
use App\Enums\PagoClienteEstado;
use App\Models\ReparacionServicioTaller;
use App\Models\ReparacionInsumo;
use App\Models\Reparacion;
use App\Models\PagoCliente;
use Illuminate\Support\Facades\DB;

/**
 * Service OrdenService
 * 
 * Orquestador principal de operaciones comerciales y técnicas con clientes:
 * - Venta de insumos y repuestos serializados.
 * - Recambio (entrega de repuesto bueno del taller y tasación de repuesto entrante de cliente).
 * - Recepción de piezas para reparación en taller.
 * - Anulación completa y coherente de órdenes (reintegración de stock físico, reversión contable,
 *   auditoría y recálculo de métricas económicas en la tabla inventario).
 */
class OrdenService
{
    /**
     * Retorna todas las órdenes comerciales y de trabajo.
     * Consulta SQL Raw:
     * SELECT * FROM ordenes;
     */
    public static function getAll()
    {
        return Orden::get();
    }

    /**
     * Obtiene una orden por su clave primaria.
     * Consulta SQL Raw:
     * SELECT * FROM ordenes WHERE id_orden = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Orden::find($id);
    }

    /**
     * Crea una orden en el sistema:
     * 1. Determina los estados operativos iniciales (Ventas y Recambios nacen directamente 'finalizada'; Reparaciones nacen 'en_espera').
     * 2. Inserta la cabecera en ordenes.
     * 3. Ejecuta el procesador específico según el tipo de orden (procesarVenta, procesarRecambio o procesarRecepcionReparacion).
     *
     * Consulta SQL Raw:
     * INSERT INTO ordenes (id_cliente, id_tipo_orden, estado_operativo, estado_administrativo, ...) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();

        $detalles = $data['detalles'] ?? [];
        $repuestosEntrantes = $data['repuestos_entrantes'] ?? [];
        unset($data['detalles'], $data['repuestos_entrantes']);

        $tipoOrden = TipoOrden::find($data['id_tipo_orden']);
        $nombreTipo = $tipoOrden ? strtolower(trim($tipoOrden->nombre)) : '';

        // 1. Estados iniciales por defecto de la orden:
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
        $data['last_update'] = now();

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

    /**
     * Actualiza la información de una orden.
     * Consulta SQL Raw:
     * UPDATE ordenes SET fecha_entrega_reparacion = ..., last_update = now() WHERE id_orden = $id;
     */
    public static function update($id, $data)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return null;
        }

        DB::beginTransaction();

        $data['last_update'] = now();

        // Si se está anulando la orden directamente
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

    /**
     * Elimina físicamente una orden y sus líneas de detalle.
     * Consulta SQL Raw:
     * DELETE FROM detalles_ordenes WHERE id_orden = $id;
     * DELETE FROM ordenes WHERE id_orden = $id;
     */
    public static function delete($id)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return null;
        }

        DB::beginTransaction();
        // Consulta SQL Raw equivalente:
        // DELETE FROM "detalles_ordenes" WHERE "id_orden" = :id;
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
     *
     * Consulta SQL Raw:
     * INSERT INTO detalles_ordenes (...) VALUES (...);
     * UPDATE repuestos SET id_orden_salida = ..., monto_venta_real = ..., utilidad = ... WHERE id_repuesto = ...;
     * UPDATE ordenes SET monto_total = ..., monto_pendiente = ... WHERE id_orden = ...;
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

                // Descontar inmediatamente inventario y registrar métricas de venta
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

            // CASO B: REPUESTOS SERIALIZADOS (compresor o válvula)
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
                    // Consulta SQL Raw equivalente:
                    // SELECT * FROM "repuestos" 
                    // WHERE "id_inventario" = :id_inventario 
                    //   AND "id_orden_salida" IS NULL 
                    //   AND "propietario" = true 
                    // LIMIT :cantRequerida;
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
     *
     * Consulta SQL Raw:
     * INSERT INTO repuestos (id_inventario, serial, nombre, estado, propietario, costo_adquisicion, id_orden_entrada) VALUES (...);
     * INSERT INTO detalles_ordenes (id_orden, id_repuesto_saliente, id_repuesto_entrante, precio_unitario, monto_tasacion, ...) VALUES (...);
     * UPDATE repuestos SET id_orden_salida = ... WHERE id_repuesto = ...;
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

            // 1. REPUESTO SALIENTE: identificar, descontar stock propio y actualizar métricas
            $idRepuestoSaliente = $linea['id_repuesto_saliente'] ?? null;
            if (!$idRepuestoSaliente && !empty($linea['id_inventario_repuesto_saliente'])) {
                // Consulta SQL Raw equivalente:
                // SELECT * FROM "repuestos" 
                // WHERE "id_inventario" = :id_inventario 
                //   AND "id_orden_salida" IS NULL 
                //   AND "propietario" = true 
                // LIMIT 1;
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

            // 2. REPUESTO ENTRANTE: registrar repuesto usado
            $idRepuestoEntranteCreado = null;
            if ($itemEntrante) {
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

            // 3. CREAR REGISTRO EN detalles_ordenes PRIMERO
            // Esto garantiza que el detalle ya exista en base de datos para el cálculo ponderado de ventas
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

            // 4. ACTUALIZAR MÉTRICAS DE INVENTARIO:
            // Salida de la venta (actualiza métricas de venta ponderadas y descuenta stock propio)
            if ($itemSaliente) {
                InventarioService::actualizarPorVenta($itemSaliente, 1, $precioUnitario);
            }

            // Si el ítem entrante es el mismo saliente, refrescar para tomar el stock recién decrementado
            if ($itemEntrante && $itemSaliente && $itemEntrante->id_inventario === $itemSaliente->id_inventario) {
                $itemEntrante->refresh();
            }

            // Entrada por tasación (actualiza métricas de adquisición/compra de usado y suma stock propio)
            if ($itemEntrante) {
                InventarioService::actualizarPorCompra($itemEntrante, 1, $montoTasacion);
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
     *
     * Consulta SQL Raw:
     * UPDATE inventario SET cantidad_total = cantidad_total + 1, cantidad_cliente = cantidad_cliente + 1 WHERE id_inventario = ...;
     * INSERT INTO repuestos (id_inventario, serial, nombre, estado, propietario, costo_adquisicion, id_orden_entrada) VALUES (...);
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
     * Anula una orden (Venta, Recambio o Reparación):
     * 1. Valida que no tenga pagos activos asociados.
     * 2. Deshace la salida y entrada física de repuestos y materiales al inventario.
     * 3. Marca id_orden_salida = id_orden en repuestos que ingresaron por clientes (trazabilidad inmutable).
     * 4. Devuelve insumos al inventario según el array opcional de cantidades no gastadas o el 100% por defecto.
     * 5. Anula reparaciones y servicios de taller vinculados.
     * 6. Recalcula métricas completas en los inventarios afectados (InventarioService::recalcularMetricasCompletas).
     *
     * Consulta SQL Raw:
     * SELECT EXISTS(SELECT 1 FROM pagos_clientes WHERE id_orden = $id AND estado != 'anulado');
     * UPDATE repuestos SET id_orden_salida = NULL, monto_venta_real = NULL WHERE id_repuesto = ...;
     * UPDATE reparaciones SET estado = 'anulada' WHERE id_orden = $id;
     * UPDATE reparaciones_servicios_taller SET estado = 'anulado' WHERE id_reparacion IN (...);
     * UPDATE ordenes SET estado_operativo = 'anulada', fecha_anulacion = now(), ... WHERE id_orden = $id;
     */
    public static function anular($id, array $data)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return null;
        }

        $estadoOperativoStr = $orden->estado_operativo instanceof OrdenEstadoOperativo
            ? $orden->estado_operativo->value
            : (string) $orden->estado_operativo;

        if ($estadoOperativoStr === OrdenEstadoOperativo::ANULADA->value) {
            return 'La orden ya se encuentra anulada';
        }

        // 1. REGLA: No permitir anular si existen pagos asociados que no estén anulados
        $tienePagosActivos = PagoCliente::where('id_orden', $orden->id_orden)
            ->where('estado', '!=', PagoClienteEstado::ANULADO->value)
            ->exists();

        if ($tienePagosActivos) {
            return 'No se puede anular la orden porque tiene pagos activos asociados. Primero debe anular los pagos correspondientes.';
        }

        DB::beginTransaction();

        $tipoOrden = TipoOrden::find($orden->id_tipo_orden);
        $nombreTipo = $tipoOrden ? strtolower(trim($tipoOrden->nombre)) : '';

        $inventariosAfectados = [];

        // 2. REVERTIR SEGÚN EL TIPO DE ORDEN
        if ($nombreTipo === 'venta') {
            // Consulta SQL Raw equivalente:
            // SELECT * FROM "detalles_ordenes" WHERE "id_orden" = :id_orden;
            $detalles = DetalleOrden::where('id_orden', $orden->id_orden)->get();

            foreach ($detalles as $det) {
                // A. Insumos vendidos: devolver stock propio al inventario
                if (!empty($det->id_inventario_insumo_saliente)) {
                    $item = Inventario::find($det->id_inventario_insumo_saliente);
                    if ($item) {
                        $item->update([
                            'cantidad_total'  => (int) $item->cantidad_total + (int) $det->cantidad,
                            'cantidad_propia' => (int) $item->cantidad_propia + (int) $det->cantidad,
                        ]);
                        $inventariosAfectados[$item->id_inventario] = true;
                    }
                }

                // B. Repuestos propios vendidos: reintegrar al taller disponibles
                if (!empty($det->id_repuesto_saliente)) {
                    $repuesto = Repuesto::find($det->id_repuesto_saliente);
                    if ($repuesto) {
                        $repuesto->update([
                            'id_orden_salida'  => null,
                            'monto_venta_real' => null,
                            'utilidad'         => null,
                        ]);
                        $item = Inventario::find($repuesto->id_inventario);
                        if ($item) {
                            $item->update([
                                'cantidad_total'  => (int) $item->cantidad_total + 1,
                                'cantidad_propia' => (int) $item->cantidad_propia + 1,
                            ]);
                            $inventariosAfectados[$item->id_inventario] = true;
                        }
                    }
                }
            }
        } elseif ($nombreTipo === 'recambio') {
            // Consulta SQL Raw equivalente:
            // SELECT * FROM "detalles_ordenes" WHERE "id_orden" = :id_orden;
            $detalles = DetalleOrden::where('id_orden', $orden->id_orden)->get();

            foreach ($detalles as $det) {
                // A. Repuesto propio que salió: vuelve al taller disponible
                if (!empty($det->id_repuesto_saliente)) {
                    $repSaliente = Repuesto::find($det->id_repuesto_saliente);
                    if ($repSaliente) {
                        $repSaliente->update([
                            'id_orden_salida'  => null,
                            'monto_venta_real' => null,
                            'utilidad'         => null,
                        ]);
                        $itemSaliente = Inventario::find($repSaliente->id_inventario);
                        if ($itemSaliente) {
                            $itemSaliente->update([
                                'cantidad_total'  => (int) $itemSaliente->cantidad_total + 1,
                                'cantidad_propia' => (int) $itemSaliente->cantidad_propia + 1,
                            ]);
                            $inventariosAfectados[$itemSaliente->id_inventario] = true;
                        }
                    }
                }

                // B. Repuesto entrante del cliente: se mantiene en repuestos con id_orden_salida = orden.id_orden y se descuenta del inventario del taller
                if (!empty($det->id_repuesto_entrante)) {
                    $repEntrante = Repuesto::find($det->id_repuesto_entrante);
                    if ($repEntrante) {
                        $repEntrante->update([
                            'id_orden_salida'  => $orden->id_orden,
                            'monto_venta_real' => 0.00,
                            'utilidad'         => 0.00,
                        ]);
                        $itemEntrante = Inventario::find($repEntrante->id_inventario);
                        if ($itemEntrante) {
                            $itemEntrante->update([
                                'cantidad_total'  => max(0, (int) $itemEntrante->cantidad_total - 1),
                                'cantidad_propia' => max(0, (int) $itemEntrante->cantidad_propia - 1),
                            ]);
                            $inventariosAfectados[$itemEntrante->id_inventario] = true;
                        }
                    }
                }
            }
        } elseif ($nombreTipo === 'reparacion') {
            // A. Repuestos de cliente que ingresaron: id_orden_salida = id_orden
            // Consulta SQL Raw equivalente:
            // SELECT * FROM "repuestos" WHERE "id_orden_entrada" = :id_orden;
            $repuestosCliente = Repuesto::where('id_orden_entrada', $orden->id_orden)->get();
            foreach ($repuestosCliente as $repCli) {
                $repCli->update([
                    'id_orden_salida'  => $orden->id_orden,
                    'monto_venta_real' => 0.00,
                    'utilidad'         => 0.00,
                ]);

                // Si la orden aún no estaba finalizada, descontar de cantidad_total y cantidad_cliente porque aún figuraban en taller
                if ($estadoOperativoStr !== OrdenEstadoOperativo::FINALIZADA->value) {
                    $itemCli = Inventario::find($repCli->id_inventario);
                    if ($itemCli) {
                        $itemCli->update([
                            'cantidad_total'   => max(0, (int) $itemCli->cantidad_total - 1),
                            'cantidad_cliente' => max(0, (int) $itemCli->cantidad_cliente - 1),
                        ]);
                        $inventariosAfectados[$itemCli->id_inventario] = true;
                    }
                } else {
                    $inventariosAfectados[$repCli->id_inventario] = true;
                }
            }

            // B. Revertir insumos de las reparaciones de esta orden
            // Consulta SQL Raw equivalente:
            // SELECT * FROM "reparaciones" WHERE "id_orden" = :id_orden;
            $reparaciones = Reparacion::where('id_orden', $orden->id_orden)->get();
            $insumosPayload = collect($data['insumos_devueltos'] ?? [])->keyBy('id_reparacion_insumo');

            foreach ($reparaciones as $rep) {
                // Consulta SQL Raw equivalente:
                // SELECT * FROM "reparaciones_insumos" WHERE "id_reparacion" = :id_reparacion;
                $insumosRep = ReparacionInsumo::where('id_reparacion', $rep->id_reparacion)->get();
                foreach ($insumosRep as $insumo) {
                    $cantDevolver = (int) $insumo->cantidad; // Por defecto el 100%

                    // Si el admin especificó cantidades no gastadas explícitas
                    if ($insumosPayload->has($insumo->id_reparacion_insumo)) {
                        $cantDevolver = (int) $insumosPayload[$insumo->id_reparacion_insumo]['cantidad_no_gastada'];
                    }

                    if ($cantDevolver > 0) {
                        $itemInsumo = Inventario::find($insumo->id_inventario);
                        if ($itemInsumo) {
                            $itemInsumo->update([
                                'cantidad_total'  => (int) $itemInsumo->cantidad_total + $cantDevolver,
                                'cantidad_propia' => (int) $itemInsumo->cantidad_propia + $cantDevolver,
                            ]);
                            $inventariosAfectados[$itemInsumo->id_inventario] = true;
                        }
                    }
                }

                // C. Marcar reparaciones como anuladas
                $rep->update([
                    'estado' => ReparacionEstado::ANULADA->value,
                ]);

                // D. Marcar servicios de taller como anulados
                // Consulta SQL Raw equivalente:
                // UPDATE "reparaciones_servicios_taller" SET "estado" = 'en_espera' WHERE "id_reparacion" = :id_reparacion;
                ReparacionServicioTaller::where('id_reparacion', $rep->id_reparacion)->update([
                    'estado' => TallerEstado::ANULADO->value,
                ]);
            }
        }

        // 3. ACTUALIZAR CABECERA DE LA ORDEN
        $orden->update([
            'estado_operativo'   => OrdenEstadoOperativo::ANULADA->value,
            'fecha_anulacion'    => now(),
            'id_admin_anulacion' => $data['id_admin_anulacion'] ?? auth()->id() ?? 1,
            'motivo_anulacion'   => $data['motivo_anulacion'] ?? 'Anulación de orden',
            'last_update'        => now(),
        ]);

        // 4. RECALCULAR MÉTRICAS ECONÓMICAS COMPLETAS EN INVENTARIOS AFECTADOS
        foreach (array_keys($inventariosAfectados) as $idInv) {
            InventarioService::recalcularMetricasCompletas((int) $idInv);
        }

        DB::commit();

        return $orden->fresh();
    }
}
