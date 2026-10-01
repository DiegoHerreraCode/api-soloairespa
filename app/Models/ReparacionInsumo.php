<?php

namespace App\Models;

use App\Models\ApiModel;

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

    public function reparacion()
    {
        return $this->belongsTo(Reparacion::class, 'id_reparacion', 'id_reparacion');
    }

    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }
}