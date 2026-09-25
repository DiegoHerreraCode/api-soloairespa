<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\OrdenEstadoOperativo;
use App\Enums\OrdenEstadoAdmin;

class Orden extends ApiModel
{
    protected $table = 'ordenes';
    protected $primaryKey = 'id_orden';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'id_admin',
        'fecha_creacion',
        'monto_total_gravado',
        'monto_total_exento',
        'monto_total_iva',
        'monto_total',
        'monto_pendiente',
        'fecha_entrega_reparacion',
        'estado_operativo',
        'estado_administrativo',
        'last_update',
        'fecha_anulacion',
        'id_admin_anulacion',
        'motivo_anulacion',
    ];

    protected $casts = [
        'estado_operativo'      => OrdenEstadoOperativo::class,
        'estado_administrativo' => OrdenEstadoAdmin::class,
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }

    public function adminAnulacion()
    {
        return $this->belongsTo(Admin::class, 'id_admin_anulacion', 'id_admin');
    }

    public function repuestosEntrada()
    {
        return $this->hasMany(Repuesto::class, 'id_orden_entrada', 'id_orden');
    }

    public function repuestosSalida()
    {
        return $this->hasMany(Repuesto::class, 'id_orden_salida', 'id_orden');
    }
}
