<?php

namespace App\Models;

use App\Models\ApiModel;

class DetalleOrden extends ApiModel
{
    protected $table = 'detalles_ordenes';
    protected $primaryKey = 'id_detalle_orden';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_orden',
        'id_inventario_insumo_saliente',
        'id_inventario_repuesto_saliente',
        'id_inventario_repuesto_entrante',
        'cantidad',
        'precio_unitario',
        'monto_tasacion',
        'monto_total_linea_sin_iva',
        'porcentaje_iva',
        'monto_iva',
        'monto_total_linea_con_iva',
    ];

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'id_orden', 'id_orden');
    }

    public function insumoSaliente()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_insumo_saliente', 'id_inventario');
    }

    public function repuestoSaliente()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_repuesto_saliente', 'id_inventario');
    }

    public function repuestoEntrante()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_repuesto_entrante', 'id_inventario');
    }
}