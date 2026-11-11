<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\InventarioTipo;
use App\Enums\InventarioCondicion;

/**
 * Modelo Inventario
 * 
 * Catálogo maestro y existencias de la empresa.
 */
class Inventario extends ApiModel
{
    protected $table = 'inventario';
    protected $primaryKey = 'id_inventario';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_modelo',
        'sku',
        'nombre',
        'tipo',
        'condicion',
        'cantidad_total',
        'cantidad_propia',
        'cantidad_cliente',
        'stock_minimo',
        'monto_compra_min',
        'monto_compra_prom',
        'monto_compra_max',
        'monto_venta_min',
        'monto_venta_prom',
        'monto_venta_max',
        'monto_reparacion_min',
        'monto_reparacion_prom',
        'monto_reparacion_max',
        'ultimo_monto_compra',
        'monto_venta_unitario',
        'monto_venta_reparacion_base',
        'porcentaje_iva',
        'porcentaje_ganancia',
    ];

    protected $casts = [
        'tipo' => InventarioTipo::class,
        'condicion' => InventarioCondicion::class,
    ];

    public function modelo()
    {
        return $this->belongsTo(Modelo::class, 'id_modelo', 'id_modelo');
    }

    public function inventarioProveedores()
    {
        return $this->hasMany(InventarioProveedor::class, 'id_inventario', 'id_inventario');
    }

    public function reparacionInsumosBase()
    {
        return $this->hasMany(InventarioReparacionInsumo::class, 'id_inventario', 'id_inventario');
    }

    public function reparacionServiciosBase()
    {
        return $this->hasMany(InventarioReparacionServicio::class, 'id_inventario', 'id_inventario');
    }
}