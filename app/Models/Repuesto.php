<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\RepuestoEstado;

/**
 * Modelo Repuesto
 * 
 * Pieza física serializada individual (compresor, válvula, etc.).
 * Controla propiedad (taller vs cliente), trazabilidad de entrada y salida,
 * costo de adquisición (compra o tasación) y costos de reparación acumulados.
 */
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
        'service_tag',
        'estado',
        'propietario',
        'id_orden_entrada',
        'id_orden_salida',
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

    /**
     * Ítem maestro de inventario al que pertenece la pieza.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = repuestos.id_inventario LIMIT 1;
     */
    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    /**
     * Orden de trabajo por la cual ingresó el repuesto (recambio o recepción para reparación).
     * Consulta SQL Raw:
     * SELECT * FROM ordenes WHERE id_orden = repuestos.id_orden_entrada LIMIT 1;
     */
    public function ordenEntrada()
    {
        return $this->belongsTo(Orden::class, 'id_orden_entrada', 'id_orden');
    }

    /**
     * Orden de trabajo por la cual salió el repuesto (venta, recambio o entrega final).
     * Consulta SQL Raw:
     * SELECT * FROM ordenes WHERE id_orden = repuestos.id_orden_salida LIMIT 1;
     */
    public function ordenSalida()
    {
        return $this->belongsTo(Orden::class, 'id_orden_salida', 'id_orden');
    }

    /**
     * Línea de compra al proveedor por la cual ingresó (si fue adquirido nuevo).
     * Consulta SQL Raw:
     * SELECT * FROM detalles_compras WHERE id_detalle_compra = repuestos.id_detalle_compra LIMIT 1;
     */
    public function detalleCompra()
    {
        return $this->belongsTo(DetalleCompra::class, 'id_detalle_compra', 'id_detalle_compra');
    }

    /**
     * Reparaciones y servicios de mantenimiento ejecutados sobre esta pieza física.
     * Consulta SQL Raw:
     * SELECT * FROM reparaciones WHERE id_repuesto = repuestos.id_repuesto;
     */
    public function reparaciones()
    {
        return $this->hasMany(Reparacion::class, 'id_repuesto', 'id_repuesto');
    }
}
