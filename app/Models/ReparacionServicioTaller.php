<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\TallerEstado;

class ReparacionServicioTaller extends ApiModel
{
    protected $table = 'reparaciones_servicios_taller';
    protected $primaryKey = 'id_reparacion_servicio_taller';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_reparacion',
        'id_servicio_taller',
        'id_admin',
        'cantidad',
        'costo_unitario',
        'monto_total_linea',
        'costo_unitario_con_ganancia',
        'monto_total_linea_con_ganancia',
        'porcentaje_iva',
        'monto_iva',
        'estado',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'estado' => TallerEstado::class,
    ];

    public function reparacion()
    {
        return $this->belongsTo(Reparacion::class, 'id_reparacion', 'id_reparacion');
    }

    public function servicioTaller()
    {
        return $this->belongsTo(ServicioTaller::class, 'id_servicio_taller', 'id_servicio_taller');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'id_reparacion_servicio_taller', 'id_reparacion_servicio_taller');
    }
}