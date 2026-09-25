<?php

namespace App\Models;

use App\Models\ApiModel;

class Equipo extends ApiModel
{
    protected $table = 'equipos';
    protected $primaryKey = 'id_equipo';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_inventario',
        'id_detalle_compra',
        'serial',
        'nombre',
        'is_deleted',
    ];

    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    public function detalleCompra()
    {
        return $this->belongsTo(DetalleCompra::class, 'id_detalle_compra', 'id_detalle_compra');
    }
}
