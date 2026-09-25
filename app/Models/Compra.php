<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\CompraTipoPago;
use App\Enums\CompraEstado;

class Compra extends ApiModel
{
    protected $table = 'compras';
    protected $primaryKey = 'id_compra';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'id_admin',
        'num_factura_boleta',
        'fecha_compra',
        'tipo_pago',
        'estado',
        'monto_total_gravado',
        'monto_total_exento',
        'monto_total_iva',
        'monto_total',
        'monto_pendiente',
        'num_pagos',
    ];

    protected $casts = [
        'tipo_pago' => CompraTipoPago::class,
        'estado'    => CompraEstado::class,
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class, 'id_compra', 'id_compra');
    }

    public function pagosCompras()
    {
        return $this->hasMany(PagoCompra::class, 'id_compra', 'id_compra');
    }
}
