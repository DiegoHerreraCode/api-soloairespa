<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo DetalleOrden
 * 
 * Línea de detalle de una orden comercial.
 * Soporta ventas directas, insumos, recambios (repuesto saliente y entrante tasado)
 * y entrega de piezas reparadas con su cálculo impositivo.
 */
class DetalleOrden extends ApiModel
{
    protected $table = 'detalles_ordenes';
    protected $primaryKey = 'id_detalle_orden';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_orden',
        'id_inventario_insumo_saliente',
        'id_inventario_repuesto_saliente',
        'id_repuesto_saliente',
        'id_inventario_repuesto_entrante',
        'id_repuesto_entrante',
        'cantidad',
        'precio_unitario',
        'monto_tasacion',
        'monto_total_linea_sin_iva',
        'porcentaje_iva',
        'monto_iva',
        'monto_total_linea_con_iva',
    ];

    /**
     * Orden de trabajo a la que pertenece esta línea.
     * Consulta SQL Raw:
     * SELECT * FROM ordenes WHERE id_orden = detalles_ordenes.id_orden LIMIT 1;
     */
    public function orden()
    {
        return $this->belongsTo(Orden::class, 'id_orden', 'id_orden');
    }

    /**
     * Ítem de inventario si lo que salió fue un insumo no serializado.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = detalles_ordenes.id_inventario_insumo_saliente LIMIT 1;
     */
    public function insumoSaliente()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_insumo_saliente', 'id_inventario');
    }

    /**
     * Ítem maestro de inventario del repuesto que sale.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = detalles_ordenes.id_inventario_repuesto_saliente LIMIT 1;
     */
    public function repuestoSalienteInventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_repuesto_saliente', 'id_inventario');
    }

    /**
     * Pieza física específica serializada que sale del taller hacia el cliente.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos WHERE id_repuesto = detalles_ordenes.id_repuesto_saliente LIMIT 1;
     */
    public function repuestoSaliente()
    {
        return $this->belongsTo(Repuesto::class, 'id_repuesto_saliente', 'id_repuesto');
    }

    /**
     * Ítem maestro de inventario del repuesto que entra tasado por recambio.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = detalles_ordenes.id_inventario_repuesto_entrante LIMIT 1;
     */
    public function repuestoEntranteInventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_repuesto_entrante', 'id_inventario');
    }

    /**
     * Pieza física específica serializada entregada por el cliente como parte de pago.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos WHERE id_repuesto = detalles_ordenes.id_repuesto_entrante LIMIT 1;
     */
    public function repuestoEntrante()
    {
        return $this->belongsTo(Repuesto::class, 'id_repuesto_entrante', 'id_repuesto');
    }
}
