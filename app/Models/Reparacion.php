<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\ReparacionEstado;

class Reparacion extends ApiModel
{
    protected $table = 'reparaciones';
    protected $primaryKey = 'id_reparacion';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_repuesto',
        'id_orden',
        'estado',
        'fecha_inicio',
        'id_admin_fecha_inicio',
        'fecha_fin',
        'id_admin_fecha_fin',
        'costo_servicios_base',
        'costo_insumos_base',
        'costo_servicios_con_ganancia',
        'costo_insumos_con_ganancia',
        'costo_total',
        'costo_total_con_ganancia',
        'monto_total_iva',
    ];

    protected $casts = [
        'estado' => ReparacionEstado::class,
    ];

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'id_repuesto', 'id_repuesto');
    }

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'id_orden', 'id_orden');
    }

    public function adminInicio()
    {
        return $this->belongsTo(Admin::class, 'id_admin_fecha_inicio', 'id_admin');
    }

    public function adminFin()
    {
        return $this->belongsTo(Admin::class, 'id_admin_fecha_fin', 'id_admin');
    }

    public function insumos()
    {
        return $this->hasMany(ReparacionInsumo::class, 'id_reparacion', 'id_reparacion');
    }

    public function serviciosTaller()
    {
        return $this->hasMany(ReparacionServicioTaller::class, 'id_reparacion', 'id_reparacion');
    }
}