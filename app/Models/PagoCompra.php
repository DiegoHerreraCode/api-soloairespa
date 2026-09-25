<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\MetodoPago;
use App\Enums\PagoCompraEstado;

class PagoCompra extends ApiModel
{
    protected $table = 'pagos_compras';
    protected $primaryKey = 'id_pago_compra';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_compra',
        'id_admin',
        'monto_a_pagar',
        'porcentaje_monto_total',
        'fecha_pago',
        'fecha_pago_acordada',
        'metodo_pago',
        'num_referencia',
        'comprobante',
        'estado',
    ];

    protected $casts = [
        'metodo_pago' => MetodoPago::class,
        'estado'      => PagoCompraEstado::class,
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra', 'id_compra');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }
}
