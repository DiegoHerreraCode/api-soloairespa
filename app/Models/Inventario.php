<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Enums\InventarioTipo;
use App\Enums\InventarioCondicion;

/**
 * Modelo Inventario
 * 
 * Representa el catálogo maestro y existencias de la empresa:
 * - Equipos completos, repuestos (nuevos/usados) e insumos.
 * - Registra stocks consolidados (cantidad_total, cantidad_propia, cantidad_cliente).
 * - Mantiene métricas económicas históricas (_min, _prom, _max de compra, venta y reparación).
 * - Calcula el precio de venta unitario sugerido según el margen de ganancia.
 */
class Inventario extends ApiModel
{
    protected $table = 'inventario';
    protected $primaryKey = 'id_inventario';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_modelo',
        'sku',
        'nombre',
        'tipo',
        'condicion',
        'cantidad_total',
        'cantidad_propia',
        'cantidad_cliente',
        'stock_minimo',
        'monto_compra_min',
        'monto_compra_prom',
        'monto_compra_max',
        'monto_venta_min',
        'monto_venta_prom',
        'monto_venta_max',
        'monto_reparacion_min',
        'monto_reparacion_prom',
        'monto_reparacion_max',
        'ultimo_monto_compra',
        'monto_venta_unitario',
        'porcentaje_iva',
        'porcentaje_ganancia',
    ];

    protected $casts = [
        'tipo'      => InventarioTipo::class,
        'condicion' => InventarioCondicion::class,
    ];

    /**
     * Modelo y marca técnica a la que pertenece este ítem.
     * Consulta SQL Raw:
     * SELECT * FROM modelos WHERE id_modelo = inventario.id_modelo LIMIT 1;
     */
    public function modelo()
    {
        return $this->belongsTo(Modelo::class, 'id_modelo', 'id_modelo');
    }

    /**
     * Historial de compras donde se adquirió este ítem.
     * Consulta SQL Raw:
     * SELECT * FROM detalles_compras WHERE id_inventario = inventario.id_inventario;
     */
    public function detallesCompras()
    {
        return $this->hasMany(DetalleCompra::class, 'id_inventario', 'id_inventario');
    }

    /**
     * Equipos físicos serializados asociados a este ítem.
     * Consulta SQL Raw:
     * SELECT * FROM equipos WHERE id_inventario = inventario.id_inventario;
     */
    public function equipos()
    {
        return $this->hasMany(Equipo::class, 'id_inventario', 'id_inventario');
    }

    /**
     * Repuestos físicos individuales (nuevos o usados) registrados bajo este ítem.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos WHERE id_inventario = inventario.id_inventario;
     */
    public function repuestos()
    {
        return $this->hasMany(Repuesto::class, 'id_inventario', 'id_inventario');
    }
}
