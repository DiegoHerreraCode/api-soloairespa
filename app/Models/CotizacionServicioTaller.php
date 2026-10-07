<?php

namespace App\Models;

/**
 * Modelo CotizacionServicioTaller
 * 
 * Línea de detalle de servicios o tareas técnicas de mano de obra en una cotización.
 * En reparaciones multi-repuesto, se asocia mediante 'service_tag_repuesto_a_reparar'.
 */
class CotizacionServicioTaller extends ApiModel
{
    protected $table = 'cotizaciones_servicios_taller';
    protected $primaryKey = 'id_cotizacion_servicio_taller';
    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion_servicio_taller',
        'id_cotizacion',
        'id_servicio_taller',
        'cantidad',
        'precio_unitario_base',
        'precio_unitario',
        'monto_total_linea_sin_iva',
        'porcentaje_iva',
        'monto_iva',
        'monto_total_linea_con_iva',
        'service_tag_repuesto_a_reparar',
    ];

    protected $casts = [
        'cantidad'                  => 'integer',
        'precio_unitario_base'      => 'float',
        'precio_unitario'           => 'float',
        'monto_total_linea_sin_iva' => 'float',
        'porcentaje_iva'            => 'float',
        'monto_iva'                 => 'float',
        'monto_total_linea_con_iva' => 'float',
    ];

    /**
     * Cotización cabecera a la que pertenece esta línea.
     * Consulta SQL Raw:
     * SELECT * FROM cotizaciones WHERE id_cotizacion = $this->id_cotizacion LIMIT 1;
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion', 'id_cotizacion');
    }

    /**
     * Servicio de taller cotizado del catálogo maestro.
     * Consulta SQL Raw:
     * SELECT * FROM servicios_taller WHERE id_servicio_taller = $this->id_servicio_taller LIMIT 1;
     */
    public function servicioTaller()
    {
        return $this->belongsTo(ServicioTaller::class, 'id_servicio_taller', 'id_servicio_taller');
    }
}