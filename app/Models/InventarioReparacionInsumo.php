<?php

namespace App\Models;

use App\Models\ApiModel;

class InventarioReparacionInsumo extends ApiModel
{
    protected $table = 'inventario_reparacion_insumos';
    protected $primaryKey = 'id_inventario_reparacion_insumo';
    public $timestamps = false;

    protected $fillable = [
        'id_inventario',
        'id_insumo',
        'cantidad',
    ];

    public function inventarioPrincipal()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    public function insumo()
    {
        return $this->belongsTo(Inventario::class, 'id_insumo', 'id_inventario');
    }
}