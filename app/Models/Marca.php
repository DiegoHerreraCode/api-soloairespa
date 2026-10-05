<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo Marca
 * 
 * Almacena las marcas de los equipos y repuestos comercializados o atendidos en taller.
 */
class Marca extends ApiModel
{
    protected $table = 'marcas';
    protected $primaryKey = 'id_marca';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    /**
     * Relación con los modelos pertenecientes a esta marca.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM modelos WHERE id_marca = marcas.id_marca;
     */
    public function modelos()
    {
        return $this->hasMany(Modelo::class, 'id_marca', 'id_marca');
    }
}
