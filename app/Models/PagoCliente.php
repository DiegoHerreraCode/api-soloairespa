<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\MetodoPago;
use App\Enums\PagoClienteEstado;

/**
 * Modelo PagoCliente
 * 
 * Gestiona los pagos / abonos efectuados por los clientes a sus �rdenes de trabajo.
 * Permite control de amortizaciones, trazabilidad de comprobantes y flujo de anulaci�n con auditor�a.
 */
class PagoCliente extends ApiModel
{
    const IMAGE_PATH = 'pagos_clientes';
    const IMAGE_FIELD = 'image'; // campo que guarda el nombre original de la imagen
    const IMAGE_PATH_FIELD = 'imagePath'; // campo que guarda la ruta relativa de la imagen
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
        'image',
        'imagePath',
        'fecha_anulacion',
        'id_admin_anulacion',
        'motivo_anulacion',
    ];

    protected $casts = [
        'metodo_pago' => MetodoPago::class,
        'estado' => PagoClienteEstado::class,
    ];

    /**
     * Cliente que realiz� el pago.
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
     * Administrador que anul� el pago (en caso de anulaci�n).
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = pagos_clientes.id_admin_anulacion LIMIT 1;
     */
    public function adminAnulacion()
    {
        return $this->belongsTo(Admin::class, 'id_admin_anulacion', 'id_admin');
    }
}
