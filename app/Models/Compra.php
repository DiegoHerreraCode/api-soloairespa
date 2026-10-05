<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\CompraTipoPago;
use App\Enums\CompraEstado;

/**
 * Modelo Compra
 * 
 * Cabecera de órdenes de compra a proveedores externos.
 * Registra totales económicos, montos pendientes y esquema de cuotas/pagos programados.
 */
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

    /**
     * Proveedor al cual se emite la compra.
     * Consulta SQL Raw:
     * SELECT * FROM proveedores WHERE id_proveedor = compras.id_proveedor LIMIT 1;
     */
    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }

    /**
     * Administrador responsable del registro de la compra.
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = compras.id_admin LIMIT 1;
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }

    /**
     * Líneas de detalle de la compra (insumos, repuestos nuevos o equipos).
     * Consulta SQL Raw:
     * SELECT * FROM detalles_compras WHERE id_compra = compras.id_compra;
     */
    public function detalles()
    {
        return $this->hasMany(DetalleCompra::class, 'id_compra', 'id_compra');
    }

    /**
     * Cuotas o pagos programados / ejecutados de esta compra.
     * Consulta SQL Raw:
     * SELECT * FROM pagos_compras WHERE id_compra = compras.id_compra;
     */
    public function pagosCompras()
    {
        return $this->hasMany(PagoCompra::class, 'id_compra', 'id_compra');
    }
}
