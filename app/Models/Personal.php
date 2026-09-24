<?php

namespace App\Models;

use App\Models\ApiModel;

class Personal extends ApiModel
{
    protected $table = 'personal';
    protected $primaryKey = 'id_personal';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'rut',
        'correo',
        'num_tlf',
        'direccion',
        'disponible',
        'is_deleted',
    ];
}
