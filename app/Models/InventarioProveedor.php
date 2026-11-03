<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo InventarioProveedor
 * 
 * Tabla intermedia que asocia ítems de inventario con los proveedores que los suministran,
 * registrando el código comercial o de catálogo que utiliza dicho proveedor.
 * Su clave primaria se genera mediante la tabla auxiliar secuencias a través de ApiModel.
 */
class InventarioProveedor extends ApiModel
{
    protected $table = 'inventario_proveedores';
    protected $primaryKey = 'id_inventario_proveedor';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'id_inventario',
        'codigo_proveedor',
    ];

    /**
     * Proveedor asociado.
     */
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }

    /**
     * Ítem de inventario asociado.
     */
    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }
}
