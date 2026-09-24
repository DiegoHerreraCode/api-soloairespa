<?php

namespace App\Models;

use App\Models\ApiModel;

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
}
