<?php

namespace App\Models;

use App\Models\ApiModel;

class Cliente extends ApiModel
{
    protected $table = 'clientes';
    protected $primaryKey = 'id_cliente';
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
