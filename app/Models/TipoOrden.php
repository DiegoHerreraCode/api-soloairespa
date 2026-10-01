<?php

namespace App\Models;

use App\Models\ApiModel;

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
    public function ordenes()
    {
        return $this->hasMany(Orden::class, 'id_tipo_orden', 'id_tipo_orden');
    }
}