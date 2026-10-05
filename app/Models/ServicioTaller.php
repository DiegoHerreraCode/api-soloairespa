<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo ServicioTaller
 * 
 * Catálogo de servicios de mano de obra y taller (ej: desarme, embobinado, limpieza, rectificación).
 * Define costos base, IVA y margen de ganancia estándar.
 */
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

    /**
     * Relación con la categoría o tipo de servicio de taller.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM tipos_servicios_taller WHERE id_tipo_servicio_taller = servicios_taller.id_tipo_servicio_taller LIMIT 1;
     */
    public function tipoServicioTaller()
    {
        return $this->belongsTo(TipoServicioTaller::class, 'id_tipo_servicio_taller', 'id_tipo_servicio_taller');
    }
}
