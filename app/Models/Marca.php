<?php

namespace App\Models;

use App\Models\ApiModel;

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

    public function modelos()
    {
        return $this->hasMany(Modelo::class, 'id_marca', 'id_marca');
    }
}
