<?php

namespace App\Models;

/**
 * Modelo CotizacionInventario
 * 
 * Línea de detalle de productos de inventario en una cotización (insumos, repuestos o recambios).
 * En reparaciones multi-repuesto, se asocia mediante 'service_tag_repuesto_a_reparar'.
 */
class CotizacionInventario extends ApiModel
{
    protected $table = 'cotizaciones_inventario';
    protected $primaryKey = 'id_cotizacion_inventario';
    public $timestamps = false;

    protected $fillable = [
        'id_cotizacion_inventario',
        'id_cotizacion',
        'id_inventario_insumo_saliente',
        'id_inventario_repuesto_saliente',
        'id_inventario_repuesto_entrante',
        'cantidad',
        'precio_unitario_base',
        'precio_unitario',
        'monto_tasacion',
        'monto_total_linea_sin_iva',
        'porcentaje_iva',
        'monto_iva',
        'monto_total_linea_con_iva',
        'service_tag_repuesto_a_reparar',
    ];

    protected $casts = [
        'cantidad'                  => 'integer',
        'precio_unitario_base'      => 'float',
        'precio_unitario'           => 'float',
        'monto_tasacion'            => 'float',
        'monto_total_linea_sin_iva' => 'float',
        'porcentaje_iva'            => 'float',
        'monto_iva'                 => 'float',
        'monto_total_linea_con_iva' => 'float',
    ];

    /**
     * Cotización cabecera a la que pertenece esta línea.
     * Consulta SQL Raw:
     * SELECT * FROM cotizaciones WHERE id_cotizacion = $this->id_cotizacion LIMIT 1;
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion', 'id_cotizacion');
    }

    /**
     * Ítem de inventario si se trata de un insumo saliente.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = $this->id_inventario_insumo_saliente LIMIT 1;
     */
    public function inventarioInsumoSaliente()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_insumo_saliente', 'id_inventario');
    }

    /**
     * Ítem de inventario si se trata de un repuesto propio saliente.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = $this->id_inventario_repuesto_saliente LIMIT 1;
     */
    public function inventarioRepuestoSaliente()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_repuesto_saliente', 'id_inventario');
    }

    /**
     * Ítem de inventario si se trata de un repuesto usado entrante (recambio).
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = $this->id_inventario_repuesto_entrante LIMIT 1;
     */
    public function inventarioRepuestoEntrante()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario_repuesto_entrante', 'id_inventario');
    }
}