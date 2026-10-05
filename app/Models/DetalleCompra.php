<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo DetalleCompra
 * 
 * Cada ítem adquirido en una compra a proveedor.
 * Almacena cantidades, costos unitarios, desglose tributario y genera seriales si aplica.
 */
class DetalleCompra extends ApiModel
{
    protected $table = 'detalles_compras';
    protected $primaryKey = 'id_detalle_compra';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_compra',
        'id_inventario',
        'cantidad',
        'costo_unitario',
        'monto_total_linea_sin_iva',
        'porcentaje_iva',
        'monto_iva',
        'monto_total_linea_con_iva',
    ];

    /**
     * Cabecera de compra asociada.
     * Consulta SQL Raw:
     * SELECT * FROM compras WHERE id_compra = detalles_compras.id_compra LIMIT 1;
     */
    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra', 'id_compra');
    }

    /**
     * Registro maestro de inventario correspondiente a esta línea.
     * Consulta SQL Raw:
     * SELECT * FROM inventario WHERE id_inventario = detalles_compras.id_inventario LIMIT 1;
     */
    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario', 'id_inventario');
    }

    /**
     * Equipos físicos serializados creados a partir de esta línea de compra.
     * Consulta SQL Raw:
     * SELECT * FROM equipos WHERE id_detalle_compra = detalles_compras.id_detalle_compra;
     */
    public function equipos()
    {
        return $this->hasMany(Equipo::class, 'id_detalle_compra', 'id_detalle_compra');
    }

    /**
     * Repuestos nuevos serializados creados a partir de esta línea de compra.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos WHERE id_detalle_compra = detalles_compras.id_detalle_compra;
     */
    public function repuestos()
    {
        return $this->hasMany(Repuesto::class, 'id_detalle_compra', 'id_detalle_compra');
    }
}
