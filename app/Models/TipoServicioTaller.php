<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo TipoServicioTaller
 * 
 * Agrupación o categoría para los servicios de taller (mecánico, eléctrico, torneado, etc.).
 */
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

    /**
     * Relación con los servicios específicos asociados a este tipo.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM servicios_taller WHERE id_tipo_servicio_taller = tipos_servicios_taller.id_tipo_servicio_taller;
     */
    public function serviciosTaller()
    {
        return $this->hasMany(ServicioTaller::class, 'id_tipo_servicio_taller', 'id_tipo_servicio_taller');
    }
}
