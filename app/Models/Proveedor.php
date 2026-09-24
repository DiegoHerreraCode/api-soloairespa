<?php

namespace App\Models;

use App\Models\ApiModel;

class Proveedor extends ApiModel
{
    protected $table = 'proveedores';
    protected $primaryKey = 'id_proveedor';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'rut',
        'correo',
        'num_tlf',
        'direccion',
    ];
}
