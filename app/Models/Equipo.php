<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo Equipo
 * 
 * Representa equipos físicos completos con serial individual adquiridos mediante compras.
 */
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

    /**
     * Ítem de inventario maestro que define el tipo y modelo de este equipo.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = equipos.id_inventario LIMIT 1;
     */
    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    /**
     * Línea de compra por la cual ingresó el equipo.
     * Consulta SQL Raw:
     * SELECT * FROM detalles_compras WHERE id_detalle_compra = equipos.id_detalle_compra LIMIT 1;
     */
    public function detalleCompra()
    {
        return $this->belongsTo(DetalleCompra::class, 'id_detalle_compra', 'id_detalle_compra');
    }
}
