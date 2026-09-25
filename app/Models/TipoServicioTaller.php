<?php

namespace App\Models;

use App\Models\ApiModel;

class TipoServicioTaller extends ApiModel
{
    protected $table = 'tipos_servicios_taller';
    protected $primaryKey = 'id_tipo_servicio_taller';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function serviciosTaller()
    {
        return $this->hasMany(ServicioTaller::class, 'id_tipo_servicio_taller', 'id_tipo_servicio_taller');
    }
}
