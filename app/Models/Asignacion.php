<?php

namespace App\Models;

use App\Models\ApiModel;

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

    public function reparacionServicioTaller()
    {
        return $this->belongsTo(ReparacionServicioTaller::class, 'id_reparacion_servicio_taller', 'id_reparacion_servicio_taller');
    }

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'id_personal', 'id_personal');
    }
}