<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo Personal
 * 
 * Gestiona el personal técnico u operativo de taller encargado de realizar los servicios y reparaciones.
 */
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
