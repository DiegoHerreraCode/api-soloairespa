<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\MetodoPago;
use App\Enums\PagoClienteEstado;

class PagoCliente extends ApiModel
{
    protected $table = 'pagos_clientes';
    protected $primaryKey = 'id_pago_cliente';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'id_orden',
        'monto',
        'estado',
        'fecha_pago',
        'metodo_pago',
        'num_referencia',
        'comprobante',
        'fecha_anulacion',
        'id_admin_anulacion',
        'motivo_anulacion',
    ];

    protected $casts = [
        'metodo_pago' => MetodoPago::class,
        'estado'      => PagoClienteEstado::class,
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'id_orden', 'id_orden');
    }

    public function adminAnulacion()
    {
        return $this->belongsTo(Admin::class, 'id_admin_anulacion', 'id_admin');
    }
}
