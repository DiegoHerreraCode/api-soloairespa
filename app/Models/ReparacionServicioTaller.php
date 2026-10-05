<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\TallerEstado;

/**
 * Modelo ReparacionServicioTaller
 * 
 * Tarea o servicio individual ejecutado dentro de una reparación en taller.
 * Permite controlar el estado operativo del trabajo técnico y asignar mecánicos responsables.
 */
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

    /**
     * Reparación a la que pertenece esta tarea.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones WHERE id_reparacion = reparaciones_servicios_taller.id_reparacion LIMIT 1;
     */
    public function reparacion()
    {
        return $this->belongsTo(Reparacion::class, 'id_reparacion', 'id_reparacion');
    }

    /**
     * Catálogo del servicio de taller ejecutado.
     * Consulta SQL Raw:
     * SELECT * FROM servicios_taller WHERE id_servicio_taller = reparaciones_servicios_taller.id_servicio_taller LIMIT 1;
     */
    public function servicioTaller()
    {
        return $this->belongsTo(ServicioTaller::class, 'id_servicio_taller', 'id_servicio_taller');
    }

    /**
     * Administrador que asignó o registró el servicio.
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = reparaciones_servicios_taller.id_admin LIMIT 1;
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }

    /**
     * Asignaciones del personal técnico responsable de ejecutar este trabajo.
     * Consulta SQL Raw:
     * SELECT * FROM asignaciones WHERE id_reparacion_servicio_taller = reparaciones_servicios_taller.id_reparacion_servicio_taller;
     */
    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'id_reparacion_servicio_taller', 'id_reparacion_servicio_taller');
    }
}
