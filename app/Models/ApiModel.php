<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasGeneratedID;

/**
 * Modelo Base ApiModel
 * 
 * Clase abstracta / base que extiende de Eloquent Model e incorpora
 * el Trait HasGeneratedID para asignación automática de IDs enteros secuenciales.
 */
class ApiModel extends Model
{
    use HasGeneratedID;
}
