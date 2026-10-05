<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo Modelo
 * 
 * Representa los modelos específicos de una marca comercial (ej. compresor scroll, pistón, etc.).
 */
class Modelo extends ApiModel
{
    protected $table = 'modelos';
    protected $primaryKey = 'id_modelo';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_marca',
        'nombre',
        'descripcion',
    ];

    /**
     * Relación con la marca a la que pertenece el modelo.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM marcas WHERE id_marca = modelos.id_marca LIMIT 1;
     */
    public function marca()
    {
        return $this->belongsTo(Marca::class, 'id_marca', 'id_marca');
    }

    /**
     * Relación con los ítems de inventario asociados a este modelo.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM inventario WHERE id_modelo = modelos.id_modelo;
     */
    public function inventarios()
    {
        return $this->hasMany(Inventario::class, 'id_modelo', 'id_modelo');
    }
}
