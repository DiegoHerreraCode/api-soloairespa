<?php

namespace App\Services;

use App\Models\ReparacionServicioTaller;
use App\Models\Reparacion;
use App\Models\Orden;
use App\Enums\OrdenEstadoOperativo;
use App\Enums\TallerEstado;
use App\Enums\ReparacionEstado;
use App\Models\Asignacion;
use Illuminate\Support\Facades\DB;

/**
 * Service ReparacionServicioTallerService
 * 
 * Gestiona el ciclo de vida de los servicios de mano de obra en taller:
 * - Creación y asignación de técnicos.
 * - Transición a 'en_proceso' (validando que tenga mecánicos asignados y pasando orden/reparación a 'en_proceso').
 * - Transición a 'finalizado' con verificación automática de si toda la reparación concluyó.
 * - Validación de eliminación restringida a estados previos a ejecución.
 */
class ReparacionServicioTallerService
{
    /**
     * Retorna todas las labores de taller registradas.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_servicios_taller;
     */
    public static function getAll()
    {
        return ReparacionServicioTaller::get();
    }

    /**
     * Obtiene una labor de taller por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_servicios_taller WHERE id_reparacion_servicio_taller = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return ReparacionServicioTaller::find($id);
    }

    /**
     * Registra un nuevo servicio en la reparación y recalcula costos consolidados.
     * Consulta SQL Raw:
     * INSERT INTO reparaciones_servicios_taller (id_reparacion, id_servicio_taller, id_admin, estado, ...) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();

        $data['id_admin'] = $data['id_admin'] ?? auth()->id() ?? 1;
        if (empty($data['estado'])) {
            $data['estado'] = TallerEstado::PENDIENTE_ASIGNACION->value;
        }

        $servicio = ReparacionService::registrarServicio($data);

        // Recalcular costos consolidados de la reparación
        ReparacionService::recalcularCostosReparacion($servicio->id_reparacion);

        DB::commit();

        return $servicio;
    }

    /**
     * Actualiza el servicio de taller, gestionando transiciones automáticas de estado:
     * - A 'en_proceso': Requiere mecánicos asignados; transiciona la reparación y la orden a 'en_proceso'.
     * - A 'finalizado': Asigna fecha de fin y verifica si todos los servicios terminaron para finalizar la reparación.
     *
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_servicios_taller WHERE id_reparacion_servicio_taller = $id LIMIT 1;
     * SELECT EXISTS(SELECT 1 FROM asignaciones WHERE id_reparacion_servicio_taller = $id);
     * UPDATE reparaciones SET estado = 'en_proceso' WHERE id_reparacion = ...;
     * UPDATE ordenes SET estado_operativo = 'en_proceso' WHERE id_orden = ...;
     * UPDATE reparaciones_servicios_taller SET estado = ..., fecha_fin = ... WHERE id_reparacion_servicio_taller = $id;
     */
    public static function update($id, $data)
    {
        $servicio = ReparacionServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        DB::beginTransaction();

        $nuevoEstado = $data['estado'] ?? null;
        $estadoStr = $nuevoEstado instanceof TallerEstado ? $nuevoEstado->value : (string) $nuevoEstado;

        // 1. Si se pasa a 'en_proceso' (ejecución del servicio)
        if ($estadoStr === 'en_proceso') {
            // Regla: Requiere tener al menos un mecánico/empleado asignado
            // Consulta SQL Raw equivalente:
            // SELECT EXISTS(SELECT 1 FROM "asignaciones" WHERE "id_reparacion_servicio_taller" = :id);
            $tieneMecanicos = Asignacion::where('id_reparacion_servicio_taller', $id)->exists();
            if (!$tieneMecanicos) {
                return false;
            }

            if (empty($servicio->fecha_inicio) && !isset($data['fecha_inicio'])) {
                $data['fecha_inicio'] = now();
            }

            // Cambiar automáticamente el estado de la reparación a 'en_proceso' si aún estaba pendiente
            $reparacion = Reparacion::find($servicio->id_reparacion);
            if ($reparacion && $reparacion->estado === ReparacionEstado::PENDIENTE) {
                $reparacion->update([
                    'estado'                => ReparacionEstado::EN_PROCESO->value,
                    'fecha_inicio'          => $reparacion->fecha_inicio ?? now(),
                    'id_admin_fecha_inicio' => $reparacion->id_admin_fecha_inicio ?? auth()->id(),
                ]);
            }

            // Cambiar automáticamente el estado operativo de la orden a 'en_proceso' si aún estaba en_espera
            if ($reparacion && !empty($reparacion->id_orden)) {
                $orden = Orden::find($reparacion->id_orden);
                if ($orden && $orden->estado_operativo === OrdenEstadoOperativo::EN_ESPERA) {
                    $orden->update([
                        'estado_operativo' => OrdenEstadoOperativo::EN_PROCESO->value,
                        'last_update'      => now(),
                    ]);
                }
            }
        }

        // 2. Si se pasa a 'finalizado'
        if ($estadoStr === 'finalizado') {
            if (empty($servicio->fecha_fin) && !isset($data['fecha_fin'])) {
                $data['fecha_fin'] = now();
            }
        }

        // Recalcular subtotales si cambia cantidad o costos
        if (isset($data['cantidad']) || isset($data['costo_unitario']) || isset($data['costo_unitario_con_ganancia'])) {
            $cantidad = (int) ($data['cantidad'] ?? $servicio->cantidad);
            $costoUnitario = (float) ($data['costo_unitario'] ?? $servicio->costo_unitario);
            $costoConGanancia = (float) ($data['costo_unitario_con_ganancia'] ?? $servicio->costo_unitario_con_ganancia);
            $porcentajeIva = (float) ($data['porcentaje_iva'] ?? $servicio->porcentaje_iva);

            $data['monto_total_linea'] = round($cantidad * $costoUnitario, 2);
            $data['monto_total_linea_con_ganancia'] = round($cantidad * $costoConGanancia, 2);
            $data['monto_iva'] = round($data['monto_total_linea_con_ganancia'] * ($porcentajeIva / 100), 2);
        }

        $servicio->update($data);

        // Recalcular costos consolidados de la reparación
        ReparacionService::recalcularCostosReparacion($servicio->id_reparacion);

        // Si este servicio finalizó, verificar si la reparación completa ya concluyó todas sus labores
        if ($estadoStr === 'finalizado') {
            self::verificarFinalizacionReparacion($servicio->id_reparacion);
        }

        DB::commit();

        return $servicio->fresh();
    }

    /**
     * Elimina un servicio asignado. Solo es permitido si no ha empezado a ejecutarse
     * (estados 'pendiente_asignacion' o 'en_espera').
     *
     * Consulta SQL Raw:
     * DELETE FROM asignaciones WHERE id_reparacion_servicio_taller = $id;
     * DELETE FROM reparaciones_servicios_taller WHERE id_reparacion_servicio_taller = $id;
     */
    public static function delete($id)
    {
        $servicio = ReparacionServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        $estadoStr = $servicio->estado instanceof TallerEstado ? $servicio->estado->value : (string) $servicio->estado;

        // Regla: sólo se puede eliminar si se encuentra en pendiente_asignacion o en_espera
        if (!in_array($estadoStr, ['pendiente_asignacion', 'en_espera'])) {
            return false;
        }

        DB::beginTransaction();

        $idReparacion = $servicio->id_reparacion;

        // Eliminar asignaciones vinculadas a este servicio antes de eliminarlo
        // Consulta SQL Raw equivalente:
        // DELETE FROM "asignaciones" WHERE "id_reparacion_servicio_taller" = :id;
        Asignacion::where('id_reparacion_servicio_taller', $id)->delete();

        $servicio->delete();

        // Recalcular costos de la reparación
        ReparacionService::recalcularCostosReparacion($idReparacion);

        // Verificar si tras eliminar este servicio, los que quedan ya están todos finalizados
        self::verificarFinalizacionReparacion($idReparacion);

        DB::commit();

        return $servicio;
    }

    /**
     * Verifica si todas las labores de la reparación alcanzaron el estado 'finalizado'.
     * Si no queda ningún trabajo pendiente, transiciona automáticamente la reparación a 'finalizada'.
     *
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_servicios_taller WHERE id_reparacion = $idReparacion;
     * SELECT * FROM reparaciones WHERE id_reparacion = $idReparacion LIMIT 1;
     * UPDATE reparaciones SET estado = 'finalizada', fecha_fin = now() WHERE id_reparacion = $idReparacion;
     */
    public static function verificarFinalizacionReparacion($idReparacion)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "reparaciones_servicios_taller" WHERE "id_reparacion" = :idReparacion;
        $servicios = ReparacionServicioTaller::where('id_reparacion', $idReparacion)->get();

        if ($servicios->isEmpty()) {
            return;
        }

        // Evalúa si absolutamente todos los servicios de la colección tienen estado 'finalizado'
        $todosFinalizados = $servicios->every(function ($s) {
            $st = $s->estado instanceof TallerEstado ? $s->estado->value : (string) $s->estado;
            return $st === 'finalizado';
        });

        if ($todosFinalizados) {
            $reparacion = Reparacion::find($idReparacion);
            if ($reparacion && $reparacion->estado !== ReparacionEstado::FINALIZADA) {
                ReparacionService::update($idReparacion, [
                    'estado' => ReparacionEstado::FINALIZADA->value,
                ]);
            }
        }
    }
}
