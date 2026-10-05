<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo Cliente
 * 
 * Gestiona la información de contacto y fiscal de clientes (propietarios de repuestos,
 * compradores o solicitantes de servicios de taller).
 */
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
