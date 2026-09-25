<?php

namespace App\Models;

use App\Models\ApiModel;

class DetalleCompra extends ApiModel
{
    protected $table = 'detalles_compras';
    protected $primaryKey = 'id_detalle_compra';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_compra',
        'id_inventario',
        'cantidad',
        'costo_unitario',
        'monto_total_linea_sin_iva',
        'porcentaje_iva',
        'monto_iva',
        'monto_total_linea_con_iva',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra', 'id_compra');
    }

    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    public function equipos()
    {
        return $this->hasMany(Equipo::class, 'id_detalle_compra', 'id_detalle_compra');
    }

    public function repuestos()
    {
        return $this->hasMany(Repuesto::class, 'id_detalle_compra', 'id_detalle_compra');
    }
}
