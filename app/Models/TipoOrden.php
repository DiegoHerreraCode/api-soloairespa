<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo TipoOrden
 * 
 * Define la tipología de órdenes comerciales del sistema:
 * - Venta (comercialización de insumos o repuestos disponibles)
 * - Recambio (entrega de repuesto bueno a cambio de tasación de repuesto usado del cliente)
 * - Reparación (recepción de equipo de cliente para mantenimiento correctivo en taller)
 */
class TipoOrden extends ApiModel
{
    protected $table = 'tipos_ordenes';
    protected $primaryKey = 'id_tipo_orden';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * Relación con todas las órdenes pertenecientes a este tipo.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM ordenes WHERE id_tipo_orden = tipos_ordenes.id_tipo_orden;
     */
    public function ordenes()
    {
        return $this->hasMany(Orden::class, 'id_tipo_orden', 'id_tipo_orden');
    }
}
