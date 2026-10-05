<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\ReparacionEstado;

/**
 * Modelo Reparacion
 * 
 * Gestiona el proceso técnico de reacondicionamiento o reparación de una pieza física (repuesto).
 * Acumula costos directos de mano de obra (servicios de taller) e insumos gastados,
 * calculando el costo total base y el valor comercial con margen de ganancia e IVA.
 */
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

    /**
     * Pieza física que se repara.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos WHERE id_repuesto = reparaciones.id_repuesto LIMIT 1;
     */
    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'id_repuesto', 'id_repuesto');
    }

    /**
     * Orden de trabajo a la que está vinculada la reparación (si proviene de una orden de cliente).
     * Consulta SQL Raw:
     * SELECT * FROM ordenes WHERE id_orden = reparaciones.id_orden LIMIT 1;
     */
    public function orden()
    {
        return $this->belongsTo(Orden::class, 'id_orden', 'id_orden');
    }

    /**
     * Administrador que dio inicio al proceso de reparación.
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = reparaciones.id_admin_fecha_inicio LIMIT 1;
     */
    public function adminInicio()
    {
        return $this->belongsTo(Admin::class, 'id_admin_fecha_inicio', 'id_admin');
    }

    /**
     * Administrador que marcó la finalización de la reparación.
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = reparaciones.id_admin_fecha_fin LIMIT 1;
     */
    public function adminFin()
    {
        return $this->belongsTo(Admin::class, 'id_admin_fecha_fin', 'id_admin');
    }

    /**
     * Materiales e insumos consumidos durante esta reparación.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_insumos WHERE id_reparacion = reparaciones.id_reparacion;
     */
    public function insumos()
    {
        return $this->hasMany(ReparacionInsumo::class, 'id_reparacion', 'id_reparacion');
    }

    /**
     * Servicios y tareas de taller ejecutadas durante esta reparación.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones_servicios_taller WHERE id_reparacion = reparaciones.id_reparacion;
     */
    public function serviciosTaller()
    {
        return $this->hasMany(ReparacionServicioTaller::class, 'id_reparacion', 'id_reparacion');
    }
}
