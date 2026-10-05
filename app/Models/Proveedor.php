<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo Proveedor
 * 
 * Gestiona los proveedores a los cuales se les compran insumos, equipos y repuestos nuevos.
 */
class Proveedor extends ApiModel
{
    protected $table = 'proveedores';
    protected $primaryKey = 'id_proveedor';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'rut',
        'correo',
        'num_tlf',
        'direccion',
    ];

    /**
     * Relación con las órdenes de compra efectuadas a este proveedor.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM compras WHERE id_proveedor = proveedores.id_proveedor;
     */
    public function compras()
    {
        return $this->hasMany(Compra::class, 'id_proveedor', 'id_proveedor');
    }
}
