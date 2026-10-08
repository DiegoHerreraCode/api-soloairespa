<?php

namespace App\Services;

use App\Models\Reparacion;
use App\Models\Repuesto;
use App\Models\Inventario;
use App\Models\Orden;
use App\Models\ServicioTaller;
use App\Models\ReparacionInsumo;
use App\Models\ReparacionServicioTaller;
use App\Models\Asignacion;
use App\Models\DetalleOrden;
use App\Enums\ReparacionEstado;
use App\Enums\RepuestoEstado;
use App\Enums\TallerEstado;
use App\Enums\OrdenEstadoOperativo;
use App\Services\InventarioService;
use Illuminate\Support\Facades\DB;

/**
 * Service ReparacionService
 * 
 * Gestiona el ciclo completo de reparaciones de taller sobre piezas f�sicas:
 * - Creaci�n de la orden t�cnica (en estado inicial 'pendiente').
 * - Carga y sincronizaci�n din�mica de insumos y servicios de mano de obra.
 * - Rec�lculo autom�tico de costos base, margen comercial e IVA.
 * - Transici�n de estados (pendiente -> en_proceso -> finalizada).
 * - Cierre y liquidaci�n t�cnica: actualiza repuesto a 'reparado', recalcula inventario
 *   y, si pertenece a una orden de cliente, genera autom�ticamente las l�neas en detalles_ordenes
 *   y marca la orden de trabajo como 'finalizada'.
 */
class ReparacionService
{
    /**
     * Retorna todas las reparaciones registradas.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones;
     */
    public static function getAll()
    {
        return Reparacion::get();
    }

    /**
     * Obtiene una reparaci�n espec�fica por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones WHERE id_reparacion = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Reparacion::find($id);
    }

    /**
     * Crea una orden de reparaci�n en taller:
     * 1. Nace en estado 'pendiente' sin fechas de inicio.
     * 2. Registra los insumos y servicios iniciales si fueron enviados.
     * 3. Recalcula y consolida los costos de la reparaci�n.
     *
     * Consulta SQL Raw:
     * INSERT INTO reparaciones (id_repuesto, estado, ...) VALUES (...);
     * INSERT INTO reparaciones_insumos (...) VALUES (...);
     * INSERT INTO reparaciones_servicios_taller (...) VALUES (...);
     * UPDATE reparaciones SET costo_total = ..., costo_total_con_ganancia = ... WHERE id_reparacion = ...;
     */
    public static function create($data)
    {
        DB::beginTransaction();

        $insumos = $data['insumos'] ?? [];
        $servicios = $data['servicios'] ?? [];
        unset($data['insumos'], $data['servicios']);

        // 1. Toda reparaci�n nace siempre con estado "pendiente" y sin fecha de inicio
        $data['estado'] = ReparacionEstado::PENDIENTE->value;
        $data['fecha_inicio'] = null;
        $data['id_admin_fecha_inicio'] = null;
        $data['fecha_fin'] = null;
        $data['id_admin_fecha_fin'] = null;

        // 2. Crear cabecera de la reparaci�n
        $reparacion = Reparacion::create($data);

        // 3. Procesar insumos si fueron proporcionados
        if (!empty($insumos) && is_array($insumos)) {
            foreach ($insumos as $insumoData) {
                $insumoData['id_reparacion'] = $reparacion->id_reparacion;
                $insumoData['id_admin'] = $insumoData['id_admin'] ?? auth()->id() ?? 1;
                self::registrarInsumo($insumoData);
            }
        }

        // 4. Procesar servicios si fueron proporcionados
        if (!empty($servicios) && is_array($servicios)) {
            foreach ($servicios as $servicioData) {
                $servicioData['id_reparacion'] = $reparacion->id_reparacion;
                $servicioData['id_admin'] = $servicioData['id_admin'] ?? auth()->id() ?? 1;
                if (empty($servicioData['estado'])) {
                    $servicioData['estado'] = TallerEstado::PENDIENTE_ASIGNACION->value;
                }
                self::registrarServicio($servicioData);
            }
        }

        // 5. Recalcular costos consolidados de la reparaci�n
        self::recalcularCostosReparacion($reparacion->id_reparacion);

        DB::commit();

        return $reparacion->fresh();
    }

    /**
     * Actualiza la reparaci�n, sincroniza arrays de insumos/servicios y ejecuta el cierre si pasa a 'finalizada'.
     *
     * Consulta SQL Raw:
     * UPDATE reparaciones SET estado = ..., fecha_inicio = ... WHERE id_reparacion = $id;
     * UPDATE ordenes SET estado_operativo = 'en_proceso' WHERE id_orden = ...;
     */
    public static function update($id, $data)
    {
        $reparacion = Reparacion::find($id);
        if (!$reparacion) {
            return null;
        }

        DB::beginTransaction();
        $insumosAfectadosIds = [];

        $insumos = $data['insumos'] ?? null;
        $servicios = $data['servicios'] ?? null;
        unset($data['insumos'], $data['servicios']);

        $nuevoEstado = $data['estado'] ?? null;
        $estadoStr = $nuevoEstado instanceof ReparacionEstado ? $nuevoEstado->value : (string) $nuevoEstado;

        // 1. Si pasa a 'en_proceso'
        if ($estadoStr === 'en_proceso') {
            if (empty($reparacion->fecha_inicio) && !isset($data['fecha_inicio'])) {
                $data['fecha_inicio'] = now();
            }
            if (empty($reparacion->id_admin_fecha_inicio) && !isset($data['id_admin_fecha_inicio'])) {
                $data['id_admin_fecha_inicio'] = auth()->id();
            }

            // Si la reparaci�n pertenece a una orden, poner autom�ticamente la orden en 'en_proceso'
            if (!empty($reparacion->id_orden)) {
                $orden = Orden::find($reparacion->id_orden);
                if ($orden && $orden->estado_operativo === OrdenEstadoOperativo::EN_ESPERA) {
                    $orden->update([
                        'estado_operativo' => OrdenEstadoOperativo::EN_PROCESO->value,
                        'last_update'      => now(),
                    ]);
                }
            }
        }

        // 2. Si pasa a 'finalizada'
        if ($estadoStr === 'finalizada') {
            if (empty($reparacion->fecha_fin) && !isset($data['fecha_fin'])) {
                $data['fecha_fin'] = now();
            }
            if (empty($reparacion->id_admin_fecha_fin) && !isset($data['id_admin_fecha_fin'])) {
                $data['id_admin_fecha_fin'] = auth()->id();
            }
        }

        if (!empty($data)) {
            $reparacion->update($data);
        }

        // Sincronizaci�n inteligente de Insumos si viene el array
        if (is_array($insumos)) {
            $insumosAfectadosIds = self::sincronizarInsumos($reparacion->id_reparacion, $insumos);
        }

        // Sincronizaci�n inteligente de Servicios si viene el array
        if (is_array($servicios)) {
            self::sincronizarServicios($reparacion->id_reparacion, $servicios);
        }

        // Recalcular costos tras cualquier cambio en insumos o servicios
        self::recalcularCostosReparacion($reparacion->id_reparacion);

        // Si la reparaci�n qued� finalizada, ejecutar operaciones de cierre y liquidaci�n
        if ($estadoStr === 'finalizada') {
            self::finalizarReparacionOperaciones($reparacion);
        }

        DB::commit();

        if (!empty($insumosAfectadosIds)) {
            InventarioService::verificarYNotificarStockBajo($insumosAfectadosIds);
        }

        return $reparacion->fresh();
    }

    /**
     * Elimina una reparaci�n revirtiendo los consumos de insumos al inventario y limpiando asignaciones.
     * Consulta SQL Raw:
     * UPDATE inventario SET cantidad_total = ..., cantidad_propia = ... WHERE id_inventario = ...;
     * DELETE FROM reparaciones_insumos WHERE id_reparacion = $id;
     * DELETE FROM asignaciones WHERE id_reparacion_servicio_taller IN (...);
     * DELETE FROM reparaciones_servicios_taller WHERE id_reparacion = $id;
     * DELETE FROM reparaciones WHERE id_reparacion = $id;
     */
    public static function delete($id)
    {
        $reparacion = Reparacion::find($id);
        if (!$reparacion) {
            return null;
        }

        DB::beginTransaction();

        // Revertir stock de insumos antes de eliminar
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "reparaciones_insumos" WHERE "id_reparacion" = :id;
        $insumosAfectadosIds = [];
        $insumos = ReparacionInsumo::where('id_reparacion', $id)->get();
        foreach ($insumos as $insumo) {
            $item = Inventario::find($insumo->id_inventario);
            if ($item) {
                $item->update([
                    'cantidad_total'  => (int) $item->cantidad_total + (int) $insumo->cantidad,
                    'cantidad_propia' => (int) $item->cantidad_propia + (int) $insumo->cantidad,
                ]);
            }
            $insumosAfectadosIds[] = $insumo->id_inventario;
            $insumo->delete();
        }

        // Eliminar servicios y sus asignaciones
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "reparaciones_servicios_taller" WHERE "id_reparacion" = :id;
        $servicios = ReparacionServicioTaller::where('id_reparacion', $id)->get();
        foreach ($servicios as $s) {
            // Consulta SQL Raw equivalente:
            // DELETE FROM "asignaciones" WHERE "id_reparacion_servicio_taller" = :id_servicio;
            Asignacion::where('id_reparacion_servicio_taller', $s->id_reparacion_servicio_taller)->delete();
            $s->delete();
        }

        $reparacion->delete();

        DB::commit();

        if (!empty($insumosAfectadosIds)) {
            InventarioService::verificarYNotificarStockBajo(array_values(array_unique($insumosAfectadosIds)));
        }

        return $reparacion;
    }

    /**
     * Sincroniza el listado de insumos de una reparaci�n:
     * - Edita existentes y ajusta diferencia de stock en inventario.
     * - Crea nuevos y descuenta stock.
     * - Elimina los omitidos y devuelve su stock al inventario.
     */
    public static function sincronizarInsumos(int $idReparacion, array $insumosPayload): array
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "reparaciones_insumos" WHERE "id_reparacion" = :idReparacion;
        $insumosActuales = ReparacionInsumo::where('id_reparacion', $idReparacion)->get()->keyBy('id_reparacion_insumo');
        $idsConservados = [];
        $idsInventariosAfectados = [];

        foreach ($insumosPayload as $insumoData) {
            $idInsumo = $insumoData['id_reparacion_insumo'] ?? null;

            // Auto-matching: si no vino id_reparacion_insumo, buscar por id_inventario entre los existentes no reclamados
            if (!$idInsumo && !empty($insumoData['id_inventario'])) {
                $existente = $insumosActuales->first(function ($item, $key) use ($insumoData, $idsConservados) {
                    return $item->id_inventario == $insumoData['id_inventario'] && !in_array($key, $idsConservados);
                });
                if ($existente) {
                    $idInsumo = $existente->id_reparacion_insumo;
                }
            }

            if ($idInsumo && isset($insumosActuales[$idInsumo])) {
                $idsConservados[] = $idInsumo;
                $idsInventariosAfectados[] = $insumosActuales[$idInsumo]->id_inventario;
                ReparacionInsumoService::update($idInsumo, $insumoData);
            } else {
                $insumoData['id_reparacion'] = $idReparacion;
                $insumoData['id_admin'] = $insumoData['id_admin'] ?? auth()->id() ?? 1;
                $nuevo = self::registrarInsumo($insumoData);
                $idsConservados[] = $nuevo->id_reparacion_insumo;
                $idsInventariosAfectados[] = $nuevo->id_inventario;
            }
        }

        // Eliminar insumos que estaban antes pero ya no vienen en el payload
        foreach ($insumosActuales as $idInsumo => $insumo) {
            if (!in_array($idInsumo, $idsConservados)) {
                $idsInventariosAfectados[] = $insumo->id_inventario;
                ReparacionInsumoService::delete($idInsumo);
            }
        }
        return array_values(array_unique($idsInventariosAfectados));
    }

    /**
     * Sincroniza el listado de servicios de taller de una reparaci�n:
     * - Edita existentes.
     * - Crea nuevos con estado 'pendiente_asignacion'.
     * - Elimina los que ya no vienen (siempre que est�n en pendiente_asignacion o en_espera).
     */
    public static function sincronizarServicios(int $idReparacion, array $serviciosPayload)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "reparaciones_servicios_taller" WHERE "id_reparacion" = :idReparacion;
        $serviciosActuales = ReparacionServicioTaller::where('id_reparacion', $idReparacion)->get()->keyBy('id_reparacion_servicio_taller');
        $idsConservados = [];

        foreach ($serviciosPayload as $servicioData) {
            $idServicio = $servicioData['id_reparacion_servicio_taller'] ?? null;

            // Auto-matching: si no vino id_reparacion_servicio_taller, buscar por id_servicio_taller entre los existentes no reclamados
            if (!$idServicio && !empty($servicioData['id_servicio_taller'])) {
                $existente = $serviciosActuales->first(function ($item, $key) use ($servicioData, $idsConservados) {
                    return $item->id_servicio_taller == $servicioData['id_servicio_taller'] && !in_array($key, $idsConservados);
                });
                if ($existente) {
                    $idServicio = $existente->id_reparacion_servicio_taller;
                }
            }

            if ($idServicio && isset($serviciosActuales[$idServicio])) {
                $idsConservados[] = $idServicio;
                ReparacionServicioTallerService::update($idServicio, $servicioData);
            } else {
                $servicioData['id_reparacion'] = $idReparacion;
                $servicioData['id_admin'] = $servicioData['id_admin'] ?? auth()->id() ?? 1;
                if (empty($servicioData['estado'])) {
                    $servicioData['estado'] = TallerEstado::PENDIENTE_ASIGNACION->value;
                }
                $nuevo = self::registrarServicio($servicioData);
                $idsConservados[] = $nuevo->id_reparacion_servicio_taller;
            }
        }

        // Eliminar servicios que ya no vienen en el payload
        foreach ($serviciosActuales as $idServicio => $servicio) {
            if (!in_array($idServicio, $idsConservados)) {
                ReparacionServicioTallerService::delete($idServicio);
            }
        }
    }

    /**
     * Registra un insumo en la reparaci�n y descuenta el stock de inventario propio.
     * Consulta SQL Raw:
     * INSERT INTO reparaciones_insumos (...) VALUES (...);
     * UPDATE inventario SET cantidad_total = ..., cantidad_propia = ... WHERE id_inventario = ...;
     */
    public static function registrarInsumo(array $data)
    {
        $item = Inventario::find($data['id_inventario']);
        $cantidad = (int) ($data['cantidad'] ?? 1);
        $costoUnitario = (float) ($data['costo_unitario'] ?? ($item ? $item->monto_compra_prom : 0.00));
        $porcentajeGanancia = (float) ($item ? $item->porcentaje_ganancia : 0.00);

        if (!isset($data['costo_unitario'])) {
            $data['costo_unitario'] = $costoUnitario;
        }

        if (!isset($data['monto_total_linea'])) {
            $data['monto_total_linea'] = round($cantidad * $costoUnitario, 2);
        }

        if (!isset($data['costo_unitario_con_ganancia'])) {
            if ($porcentajeGanancia > 0 && $porcentajeGanancia < 100) {
                $data['costo_unitario_con_ganancia'] = round($costoUnitario / (1 - ($porcentajeGanancia / 100)), 2);
            } else {
                $data['costo_unitario_con_ganancia'] = $costoUnitario;
            }
        }

        if (!isset($data['monto_total_linea_con_ganancia'])) {
            $data['monto_total_linea_con_ganancia'] = round($cantidad * (float) $data['costo_unitario_con_ganancia'], 2);
        }

        $porcentajeIva = (float) ($data['porcentaje_iva'] ?? 19.00);
        $data['porcentaje_iva'] = $porcentajeIva;

        if (!isset($data['monto_iva'])) {
            $data['monto_iva'] = round((float) $data['monto_total_linea_con_ganancia'] * ($porcentajeIva / 100), 2);
        }

        $insumo = ReparacionInsumo::create($data);

        // Descontar inventario (stock propio del taller consumido)
        if ($item) {
            $nuevoTotal = max(0, (int) $item->cantidad_total - $cantidad);
            $nuevoPropio = max(0, (int) $item->cantidad_propia - $cantidad);
            $item->update([
                'cantidad_total'  => $nuevoTotal,
                'cantidad_propia' => $nuevoPropio,
            ]);
        }

        return $insumo;
    }

    /**
     * Registra un servicio de taller en la reparaci�n calculando su subtotal comercial con margen e IVA.
     * Consulta SQL Raw:
     * INSERT INTO reparaciones_servicios_taller (...) VALUES (...);
     */
    public static function registrarServicio(array $data)
    {
        $servicioTaller = ServicioTaller::find($data['id_servicio_taller'] ?? null);
        $cantidad = (int) ($data['cantidad'] ?? 1);
        $costoUnitario = (float) ($data['costo_unitario'] ?? ($servicioTaller ? $servicioTaller->costo_base : 0.00));

        $ganancia = (float) ($servicioTaller ? $servicioTaller->porcentaje_ganancia : 0.00);
        $costoConGanancia = (float) ($data['costo_unitario_con_ganancia'] ?? (($ganancia > 0 && $ganancia < 100) ? round($costoUnitario / (1 - ($ganancia / 100)), 2) : $costoUnitario));

        if (!isset($data['costo_unitario'])) {
            $data['costo_unitario'] = $costoUnitario;
        }

        if (!isset($data['monto_total_linea'])) {
            $data['monto_total_linea'] = round($cantidad * $costoUnitario, 2);
        }

        if (!isset($data['costo_unitario_con_ganancia'])) {
            $data['costo_unitario_con_ganancia'] = $costoConGanancia;
        }

        if (!isset($data['monto_total_linea_con_ganancia'])) {
            $data['monto_total_linea_con_ganancia'] = round($cantidad * $costoConGanancia, 2);
        }

        $porcentajeIva = (float) ($data['porcentaje_iva'] ?? 19.00);
        $data['porcentaje_iva'] = $porcentajeIva;

        if (!isset($data['monto_iva'])) {
            $data['monto_iva'] = round((float) $data['monto_total_linea_con_ganancia'] * ($porcentajeIva / 100), 2);
        }

        return ReparacionServicioTaller::create($data);
    }

    /**
     * Recalcula y actualiza los costos consolidados de la reparaci�n (base, margen comercial e IVA).
     * Consulta SQL Raw:
     * SELECT SUM(monto_total_linea) FROM reparaciones_insumos WHERE id_reparacion = $idReparacion;
     * SELECT SUM(monto_total_linea) FROM reparaciones_servicios_taller WHERE id_reparacion = $idReparacion;
     * UPDATE reparaciones SET costo_insumos_base = ..., costo_servicios_base = ..., costo_total = ..., costo_total_con_ganancia = ... WHERE id_reparacion = $idReparacion;
     */
    public static function recalcularCostosReparacion($idReparacion)
    {
        $reparacion = Reparacion::find($idReparacion);
        if (!$reparacion) {
            return;
        }

        // Consulta SQL Raw equivalente:
        // SELECT * FROM "reparaciones_insumos" WHERE "id_reparacion" = :idReparacion;
        $insumos = ReparacionInsumo::where('id_reparacion', $idReparacion)->get();
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "reparaciones_servicios_taller" WHERE "id_reparacion" = :idReparacion;
        $servicios = ReparacionServicioTaller::where('id_reparacion', $idReparacion)->get();

        $costoInsumosBase = (float) $insumos->sum('monto_total_linea');
        $costoInsumosConGanancia = (float) $insumos->sum('monto_total_linea_con_ganancia');
        $ivaInsumos = (float) $insumos->sum('monto_iva');

        $costoServiciosBase = (float) $servicios->sum('monto_total_linea');
        $costoServiciosConGanancia = (float) $servicios->sum('monto_total_linea_con_ganancia');
        $ivaServicios = (float) $servicios->sum('monto_iva');

        $costoTotal = $costoInsumosBase + $costoServiciosBase;
        $costoTotalConGanancia = $costoInsumosConGanancia + $costoServiciosConGanancia;
        $montoTotalIva = $ivaInsumos + $ivaServicios;

        $reparacion->update([
            'costo_insumos_base'          => round($costoInsumosBase, 2),
            'costo_insumos_con_ganancia'  => round($costoInsumosConGanancia, 2),
            'costo_servicios_base'        => round($costoServiciosBase, 2),
            'costo_servicios_con_ganancia'=> round($costoServiciosConGanancia, 2),
            'costo_total'                 => round($costoTotal, 2),
            'costo_total_con_ganancia'    => round($costoTotalConGanancia, 2),
            'monto_total_iva'             => round($montoTotalIva, 2),
        ]);
    }

    /**
     * Operaciones ejecutadas cuando la reparaci�n finaliza:
     * 1. Actualiza el repuesto f�sico a estado 'reparado' y consolida sus costos acumulados.
     * 2. Recalcula m�tricas de reparaci�n en la tabla inventario.
     * 3. Si pertenece a una orden de cliente y todos los repuestos est�n listos:
     *    - Genera las l�neas de cobro en detalles_ordenes con los importes con ganancia.
     *    - Asigna salida al repuesto y descuenta el stock de cliente.
     *    - Consolida los totales de la orden y pasa su estado operativo a 'finalizada'.
     *
     * Consulta SQL Raw:
     * UPDATE repuestos SET estado = 'reparado', costo_reparacion_base = ..., costo_total = ... WHERE id_repuesto = ...;
     * INSERT INTO detalles_ordenes (...) VALUES (...);
     * UPDATE ordenes SET monto_total = ..., estado_operativo = 'finalizada' WHERE id_orden = ...;
     */
    public static function finalizarReparacionOperaciones(Reparacion $reparacion)
    {
        $repuesto = Repuesto::find($reparacion->id_repuesto);
        if ($repuesto) {
            $costoBaseReparacion = (float) ($reparacion->costo_total ?? 0.00);
            $costoAdquisicion = (float) ($repuesto->costo_adquisicion ?? 0.00);
            $costoConGanancia = (float) ($reparacion->costo_total_con_ganancia ?? 0.00);

            $repuesto->update([
                'estado'                        => RepuestoEstado::REPARADO->value,
                'costo_reparacion_base'         => $costoBaseReparacion,
                'costo_reparacion_con_ganancia' => $costoConGanancia,
                'costo_total'                   => $costoAdquisicion + $costoBaseReparacion,
            ]);

            // Actualizar inventario con m�tricas completas seg�n la Gu�a de Validaci�n Manual
            $item = Inventario::find($repuesto->id_inventario);
            if ($item) {
                InventarioService::actualizarPorReparacion($item, $costoBaseReparacion);
            }
        }

        // Si la reparaci�n pertenece a una orden de cliente
        if (!empty($reparacion->id_orden)) {
            $orden = Orden::find($reparacion->id_orden);
            if ($orden) {
                // Verificar si todav�a existen repuestos ingresados en esta orden sin haber culminado su reparaci�n
                // Consulta SQL Raw equivalente:
                // SELECT EXISTS(SELECT 1 FROM "repuestos" WHERE "id_orden_entrada" = :id_orden AND "estado" != 'reparado');
                $quedanRepuestosSinReparar = Repuesto::where('id_orden_entrada', $orden->id_orden)
                    ->where('estado', '!=', RepuestoEstado::REPARADO->value)
                    ->exists();

                if ($quedanRepuestosSinReparar) {
                    // A�n hay repuestos por reparar: la orden permanece en proceso y no se generan l�neas de orden todav�a
                    return;
                }

                // SI TODOS LOS REPUESTOS YA EST�N REPARADOS:
                // Obtener todas las reparaciones finalizadas de esta orden
                // Consulta SQL Raw equivalente:
                // SELECT * FROM "reparaciones" WHERE "id_orden" = :id_orden AND "estado" = 'finalizada';
                $reparacionesOrden = Reparacion::where('id_orden', $orden->id_orden)
                    ->where('estado', ReparacionEstado::FINALIZADA->value)
                    ->get();

                $totalGravado = 0.00;
                $totalExento = 0.00;
                $totalIva = 0.00;
                $totalNeto = 0.00;

                // Grabar los N registros en detalles_ordenes (uno por cada reparaci�n / repuesto)
                foreach ($reparacionesOrden as $rep) {
                    $repuestoAsociado = Repuesto::find($rep->id_repuesto);
                    $itemInventario = $repuestoAsociado ? Inventario::find($repuestoAsociado->id_inventario) : null;
                    $subtotalLinea = (float) ($rep->costo_total_con_ganancia ?? 0.00);
                    $ivaLinea = (float) ($rep->monto_total_iva ?? 0.00);
                    $totalLinea = $subtotalLinea + $ivaLinea;

                    $porcentajeIva = 0.00;
                    if ($subtotalLinea > 0 && $ivaLinea > 0) {
                        $porcentajeIva = (float) ($itemInventario->porcentaje_iva ?? round(($ivaLinea / $subtotalLinea) * 100, 2));
                    }

                    // Grabar registro en detalles_ordenes con id_repuesto_saliente
                    DetalleOrden::create([
                        'id_orden'                        => $orden->id_orden,
                        'id_inventario_insumo_saliente'   => null,
                        'id_inventario_repuesto_saliente' => $repuestoAsociado ? $repuestoAsociado->id_inventario : null,
                        'id_repuesto_saliente'            => $repuestoAsociado ? $repuestoAsociado->id_repuesto : null,
                        'id_inventario_repuesto_entrante' => null,
                        'id_repuesto_entrante'            => null,
                        'cantidad'                        => 1,
                        'precio_unitario'                 => $subtotalLinea,
                        'monto_tasacion'                  => 0.00,
                        'monto_total_linea_sin_iva'       => $subtotalLinea,
                        'porcentaje_iva'                  => $porcentajeIva,
                        'monto_iva'                       => $ivaLinea,
                        'monto_total_linea_con_iva'       => $totalLinea,
                    ]);

                    // Asignar salida contable y f�sica del repuesto reparado
                    if ($repuestoAsociado) {
                        $costoTotalRep = (float) $repuestoAsociado->costo_total;
                        $repuestoAsociado->update([
                            'id_orden_salida'  => $orden->id_orden,
                            'monto_venta_real' => $subtotalLinea,
                            'utilidad'         => round($subtotalLinea - $costoTotalRep, 2),
                        ]);
                    }

                    // Descontar inventario del cliente (cantidad_total, cantidad_cliente) y registrar m�tricas hist�ricas
                    if ($itemInventario) {
                        InventarioService::actualizarPorReparacionEntrega($itemInventario, 1, $subtotalLinea);
                    }

                    if ($porcentajeIva > 0) {
                        $totalGravado += $subtotalLinea;
                    } else {
                        $totalExento += $subtotalLinea;
                    }
                    $totalIva += $ivaLinea;
                    $totalNeto += $totalLinea;
                }

                // Actualizar montos definitivos consolidados y marcar la orden como finalizada
                $orden->update([
                    'monto_total_gravado' => round($totalGravado, 2),
                    'monto_total_exento'  => round($totalExento, 2),
                    'monto_total_iva'     => round($totalIva, 2),
                    'monto_total'         => round($totalNeto, 2),
                    'monto_pendiente'     => round($totalNeto, 2),
                    'estado_operativo'    => OrdenEstadoOperativo::FINALIZADA->value,
                    'last_update'         => now(),
                ]);
            }
        }
    }
}
