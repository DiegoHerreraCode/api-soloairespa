<?php

namespace App\Models;

use App\Models\ApiModel;

class ServicioTaller extends ApiModel
{
    protected $table = 'servicios_taller';
    protected $primaryKey = 'id_servicio_taller';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_tipo_servicio_taller',
        'nombre',
        'descripcion',
        'costo_base',
        'porcentaje_iva',
        'porcentaje_ganancia',
    ];
}
