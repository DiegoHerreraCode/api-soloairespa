<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo ReparacionInsumo
 * 
 * Registra cada insumo consumido en una reparación específica (aceites, gas, selladores, etc.).
 * Almacena costos unitarios de inventario y valores de cobro final con margen comercial.
 */
class ReparacionInsumo extends ApiModel
{
    protected $table = 'reparaciones_insumos';
    protected $primaryKey = 'id_reparacion_insumo';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_reparacion',
        'id_inventario',
        'id_admin',
        'cantidad',
        'costo_unitario',
        'monto_total_linea',
        'costo_unitario_con_ganancia',
        'monto_total_linea_con_ganancia',
        'porcentaje_iva',
        'monto_iva',
    ];

    /**
     * Reparación a la cual se cargó el insumo.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones WHERE id_reparacion = reparaciones_insumos.id_reparacion LIMIT 1;
     */
    public function reparacion()
    {
        return $this->belongsTo(Reparacion::class, 'id_reparacion', 'id_reparacion');
    }

    /**
     * Ítem de inventario de tipo insumo que fue descargado.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = reparaciones_insumos.id_inventario LIMIT 1;
     */
    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    /**
     * Administrador que autorizó o cargó el consumo del insumo.
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = reparaciones_insumos.id_admin LIMIT 1;
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }
}
