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

class ReparacionServicioTallerService
{
    public static function getAll()
    {
        return ReparacionServicioTaller::get();
    }

    public static function getOne($id)
    {
        return ReparacionServicioTaller::find($id);
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $data['id_admin'] = $data['id_admin'] ?? auth()->id() ?? 1;
        if (empty($data['estado'])) {
            $data['estado'] = TallerEstado::PENDIENTE_ASIGNACION->value;
        }

        $servicio = ReparacionService::registrarServicio($data);

        // Recalcular costos de la reparación
        ReparacionService::recalcularCostosReparacion($servicio->id_reparacion);

        DB::commit();

        return $servicio;
    }

    public static function update($id, $data)
    {
        $servicio = ReparacionServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        DB::beginTransaction();

        $nuevoEstado = $data['estado'] ?? null;
        $estadoStr = $nuevoEstado instanceof TallerEstado ? $nuevoEstado->value : (string) $nuevoEstado;

        // Si se pasa a 'en_proceso' (ejecución del servicio)
        if ($estadoStr === 'en_proceso') {
            // Regla: Requiere tener al menos un mecánico/empleado asignado
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
                    'estado' => ReparacionEstado::EN_PROCESO->value,
                    'fecha_inicio' => $reparacion->fecha_inicio ?? now(),
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

        // Si se pasa a 'finalizado'
        if ($estadoStr === 'finalizado') {
            if (empty($servicio->fecha_fin) && !isset($data['fecha_fin'])) {
                $data['fecha_fin'] = now();
            }
        }

        // Recalcular totales si cambia cantidad o costos
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

        // Recalcular costos de la reparación
        ReparacionService::recalcularCostosReparacion($servicio->id_reparacion);

        // Si este servicio finalizó, verificar si la reparación completa ya finalizó
        if ($estadoStr === 'finalizado') {
            self::verificarFinalizacionReparacion($servicio->id_reparacion);
        }

        DB::commit();

        return $servicio->fresh();
    }

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
     * Verifica si todos los servicios de la reparación están finalizados.
     * Si no queda ningún servicio pendiente, transiciona la reparación a 'finalizada'.
     */
    public static function verificarFinalizacionReparacion($idReparacion)
    {
        $servicios = ReparacionServicioTaller::where('id_reparacion', $idReparacion)->get();

        if ($servicios->isEmpty()) {
            return;
        }

        // $servicios->every(callback) es un método de las colecciones de Laravel que evalúa si todos los elementos 
        // de la colección cumplen una condición. En cada iteración toma un servicio ($s), extrae su valor de texto de 
        // estado ($st) y verifica si es igual a 'finalizado'. Si todos son 'finalizado', devuelve true.

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


//EQUIVALENTE: 

// public static function verificarFinalizacionReparacion($idReparacion)
// {
//     // 1. Si no hay servicios registrados, salimos
//     $totalServicios = ReparacionServicioTaller::where('id_reparacion', $idReparacion)->count();
//     if ($totalServicios === 0) {
//         return;
//     }

//     // 2. ¿Existe algún servicio cuyo estado NO sea 'finalizado'?
//     $quedanServiciosPendientes = ReparacionServicioTaller::where('id_reparacion', $idReparacion)
//         ->where('estado', '!=', TallerEstado::FINALIZADO->value)
//         ->exists();

//     // 3. Si NO quedan servicios pendientes, significa que TODOS finalizaron
//     if (!$quedanServiciosPendientes) {
//         $reparacion = Reparacion::find($idReparacion);
//         if ($reparacion && $reparacion->estado !== ReparacionEstado::FINALIZADA) {
//             ReparacionService::update($idReparacion, [
//                 'estado' => ReparacionEstado::FINALIZADA->value,
//             ]);
//         }
//     }
// }
