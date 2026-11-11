<?php

namespace App\Models;

use App\Models\ApiModel;

class InventarioReparacionServicio extends ApiModel
{
    protected $table = 'inventario_reparacion_servicios';
    protected $primaryKey = 'id_inventario_reparacion_servicio';
    public $timestamps = false;

    protected $fillable = [
        'id_inventario',
        'id_servicio_taller',
        'cantidad',
    ];

    public function inventarioPrincipal()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    public function servicioTaller()
    {
        return $this->belongsTo(ServicioTaller::class, 'id_servicio_taller', 'id_servicio_taller');
    }
}