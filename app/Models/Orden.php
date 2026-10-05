<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\OrdenEstadoOperativo;
use App\Enums\OrdenEstadoAdmin;

/**
 * Modelo Orden
 * 
 * Cabecera central de operaciones comerciales y técnicas con clientes:
 * - Gestiona órdenes de Venta, Recambio y Reparación.
 * - Controla el doble estado: operativo (en_espera, en_proceso, finalizada, anulada)
 *   y administrativo (pendiente_pago, pagada, anulada).
 * - Registra totales fiscales, saldos pendientes y auditoría de anulación.
 */
class Orden extends ApiModel
{
    protected $table = 'ordenes';
    protected $primaryKey = 'id_orden';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_cliente',
        'id_tipo_orden',
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

    /**
     * Cliente titular de la orden de trabajo.
     * Consulta SQL Raw:
     * SELECT * FROM clientes WHERE id_cliente = ordenes.id_cliente LIMIT 1;
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    /**
     * Tipo de orden (venta, recambio, reparacion).
     * Consulta SQL Raw:
     * SELECT * FROM tipos_ordenes WHERE id_tipo_orden = ordenes.id_tipo_orden LIMIT 1;
     */
    public function tipoOrden()
    {
        return $this->belongsTo(TipoOrden::class, 'id_tipo_orden', 'id_tipo_orden');
    }

    /**
     * Administrador que creó la orden.
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = ordenes.id_admin LIMIT 1;
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'id_admin', 'id_admin');
    }

    /**
     * Administrador que ejecutó la anulación de la orden (si aplica).
     * Consulta SQL Raw:
     * SELECT * FROM admins WHERE id_admin = ordenes.id_admin_anulacion LIMIT 1;
     */
    public function adminAnulacion()
    {
        return $this->belongsTo(Admin::class, 'id_admin_anulacion', 'id_admin');
    }

    /**
     * Repuestos que ingresaron a la empresa mediante esta orden.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos WHERE id_orden_entrada = ordenes.id_orden;
     */
    public function repuestosEntrada()
    {
        return $this->hasMany(Repuesto::class, 'id_orden_entrada', 'id_orden');
    }

    /**
     * Repuestos que salieron de la empresa mediante esta orden.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos WHERE id_orden_salida = ordenes.id_orden;
     */
    public function repuestosSalida()
    {
        return $this->hasMany(Repuesto::class, 'id_orden_salida', 'id_orden');
    }

    /**
     * Reparaciones de taller gestionadas dentro de esta orden.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones WHERE id_orden = ordenes.id_orden;
     */
    public function reparaciones()
    {
        return $this->hasMany(Reparacion::class, 'id_orden', 'id_orden');
    }

    /**
     * Líneas de detalle financiero y de productos/servicios de la orden.
     * Consulta SQL Raw:
     * SELECT * FROM detalles_ordenes WHERE id_orden = ordenes.id_orden;
     */
    public function detalles()
    {
        return $this->hasMany(DetalleOrden::class, 'id_orden', 'id_orden');
    }
}
