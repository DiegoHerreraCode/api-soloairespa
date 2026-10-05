<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\MetodoPago;
use App\Enums\PagoClienteEstado;

/**
 * Modelo PagoCliente
 * 
 * Gestiona los pagos / abonos efectuados por los clientes a sus órdenes de trabajo.
 * Permite control de amortizaciones, trazabilidad de comprobantes y flujo de anulación con auditoría.
 */
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

    /**
     * Cliente que realizó el pago.
     * Consulta SQL Raw:
     * SELECT * FROM clientes WHERE id_cliente = pagos_clientes.id_cliente LIMIT 1;
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    /**
     * Orden de trabajo a la cual se abona el monto.
     * Consulta SQL Raw:
     * SELECT * FROM ordenes WHERE id_orden = pagos_clientes.id_orden LIMIT 1;
     */
    public function orden()
    {
        return $this->belongsTo(Orden::class, 'id_orden', 'id_orden');
    }

    /**
     * Administrador que anuló el pago (en caso de anulación).
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = pagos_clientes.id_admin_anulacion LIMIT 1;
     */
    public function adminAnulacion()
    {
        return $this->belongsTo(Admin::class, 'id_admin_anulacion', 'id_admin');
    }
}
