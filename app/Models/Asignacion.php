<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo Asignacion
 * 
 * Vincula un servicio específico de una reparación en taller con el personal técnico responsable.
 */
class Asignacion extends ApiModel
{
    protected $table = 'asignaciones';
    protected $primaryKey = 'id_asignacion';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_reparacion_servicio_taller',
        'id_personal',
    ];

    /**
     * Relación con el servicio de taller en la reparación.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM reparaciones_servicios_taller WHERE id_reparacion_servicio_taller = asignaciones.id_reparacion_servicio_taller LIMIT 1;
     */
    public function reparacionServicioTaller()
    {
        return $this->belongsTo(ReparacionServicioTaller::class, 'id_reparacion_servicio_taller', 'id_reparacion_servicio_taller');
    }

    /**
     * Relación con el personal técnico asignado.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM personal WHERE id_personal = asignaciones.id_personal LIMIT 1;
     */
    public function personal()
    {
        return $this->belongsTo(Personal::class, 'id_personal', 'id_personal');
    }
}
