<?php

namespace App\Models;

use App\Models\ApiModel;

class Admin extends ApiModel
{
    protected $table = 'admins';
    protected $primaryKey = 'id_admin';
    protected $keyType = 'Integer';
    public $incrementing = false;
    public $timestamps = false;

    // (Lista negra): Especificas qué campos NO se pueden guardar masivamente
    // Al poner protected $guarded = []; (un array vacío), 
    // le estás diciendo a Laravel: "No protejas ningún campo, permite que TODOS se puedan guardar masivamente".

    // Ventaja:
    // Es muy cómodo porque no tienes que estar agregando campos al $fillable cada 
    // vez que añades una columna a la base de datos.

    //protected $guarded = [];

    protected $hidden = [
    ];

    // (Lista blanca): Especificas qué campos SI se pueden guardar masivamente
    protected $fillable = [
        'nombre',
        'rut',
        'num_tlf',
        'direccion',
        'id_user',
    ];
}

