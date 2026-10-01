<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\ReparacionServicioTaller;
use App\Enums\TallerEstado;
use Illuminate\Support\Facades\DB;

class AsignacionService
{
    public static function getAll()
    {
        return Asignacion::get();
    }

    public static function getOne($id)
    {
        return Asignacion::find($id);
    }

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