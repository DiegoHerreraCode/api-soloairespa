<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\RepuestoEstado;

class Repuesto extends ApiModel
{
    protected $table = 'repuestos';
    protected $primaryKey = 'id_repuesto';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_inventario',
        'id_detalle_compra',
        'serial',
        'nombre',
        'estado',
        'propietario',
        'id_orden_entrada',
        'id_orden_salida',
        'is_deleted',
        'costo_adquisicion',
        'costo_reparacion_base',
        'costo_total',
        'costo_reparacion_con_ganancia',
        'monto_venta_real',
        'utilidad',
    ];

    protected $casts = [
        'estado' => RepuestoEstado::class,
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
