<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\ReparacionServicioTaller;
use App\Enums\TallerEstado;
use Illuminate\Support\Facades\DB;

/**
 * Service AsignacionService
 * 
 * Gestiona la asignación de mecánicos/técnicos a las tareas de taller.
 * Actualiza automáticamente el estado del servicio:
 * - Si tiene al menos un mecánico: pasa de 'pendiente_asignacion' a 'en_espera'.
 * - Si se retiran todos los mecánicos: regresa a 'pendiente_asignacion'.
 */
class AsignacionService
{
    /**
     * Retorna todas las asignaciones de trabajo técnico registradas.
     * Consulta SQL Raw:
     * SELECT * FROM asignaciones;
     */
    public static function getAll()
    {
        return Asignacion::get();
    }

    /**
     * Obtiene una asignación por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM asignaciones WHERE id_asignacion = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Asignacion::find($id);
    }

    /**
     * Registra la asignación de un empleado a un servicio de taller y actualiza el estado operativo.
     * Consulta SQL Raw:
     * INSERT INTO asignaciones (id_reparacion_servicio_taller, id_personal) VALUES (...);
     * SELECT * FROM reparaciones_servicios_taller WHERE id_reparacion_servicio_taller = ... LIMIT 1;
     * UPDATE reparaciones_servicios_taller SET estado = 'en_espera' WHERE id_reparacion_servicio_taller = ...;
     */
    public static function create($data)
    {
        DB::beginTransaction();

        $asignacion = Asignacion::create($data);

        // Al asignar al menos un empleado, el servicio pasa de 'pendiente_asignacion' a 'en_espera'
        $servicio = ReparacionServicioTaller::find($asignacion->id_reparacion_servicio_taller);
        if ($servicio) {
            $estadoStr = $servicio->estado instanceof TallerEstado ? $servicio->estado->value : (string) $servicio->estado;
            if ($estadoStr === 'pendiente_asignacion') {
                $servicio->update([
                    'estado' => TallerEstado::EN_ESPERA->value,
                ]);
            }
        }

        DB::commit();

        return $asignacion;
    }

    /**
     * Modifica los datos de una asignación existente.
     * Consulta SQL Raw:
     * UPDATE asignaciones SET id_personal = ... WHERE id_asignacion = $id;
     */
    public static function update($id, $data)
    {
        $asignacion = Asignacion::find($id);
        if (!$asignacion) {
            return null;
        }

        DB::beginTransaction();
        $asignacion->update($data);
        DB::commit();

        return $asignacion;
    }

    /**
     * Elimina la asignación de un mecánico. Si la tarea queda sin personal asignado,
     * regresa el estado del servicio a 'pendiente_asignacion'.
     * Consulta SQL Raw:
     * DELETE FROM asignaciones WHERE id_asignacion = $id;
     * SELECT EXISTS(SELECT 1 FROM asignaciones WHERE id_reparacion_servicio_taller = ...);
     * UPDATE reparaciones_servicios_taller SET estado = 'pendiente_asignacion' WHERE id_reparacion_servicio_taller = ...;
     */
    public static function delete($id)
    {
        $asignacion = Asignacion::find($id);
        if (!$asignacion) {
            return null;
        }

        DB::beginTransaction();
        $idServicio = $asignacion->id_reparacion_servicio_taller;
        $asignacion->delete();

        // Si el servicio se queda sin mecánicos y estaba en 'en_espera', regresa a 'pendiente_asignacion'
        $servicio = ReparacionServicioTaller::find($idServicio);
        if ($servicio) {
            // Consulta SQL Raw equivalente:
            // SELECT EXISTS(SELECT 1 FROM "asignaciones" WHERE "id_reparacion_servicio_taller" = :idServicio);
            $quedanMecanicos = Asignacion::where('id_reparacion_servicio_taller', $idServicio)->exists();
            $estadoStr = $servicio->estado instanceof TallerEstado ? $servicio->estado->value : (string) $servicio->estado;
            if (!$quedanMecanicos && $estadoStr === 'en_espera') {
                $servicio->update([
                    'estado' => TallerEstado::PENDIENTE_ASIGNACION->value,
                ]);
            }
        }

        DB::commit();

        return $asignacion;
    }
}
