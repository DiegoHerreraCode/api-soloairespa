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

    /**
     * Homologaciones de códigos de este proveedor con ítems de inventario.
     */
    public function inventarioProveedores()
    {
        return $this->hasMany(InventarioProveedor::class, 'id_proveedor', 'id_proveedor');
    }

    /**
     * Ítems de inventario que suministra este proveedor.
     */
    public function inventario()
    {
        return $this->belongsToMany(Inventario::class, 'inventario_proveedores', 'id_proveedor', 'id_inventario')
            ->withPivot('id_inventario_proveedor', 'codigo_proveedor');
    }
}
