<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasGeneratedID;
use App\Traits\HasImage;

/**
 * Modelo Base ApiModel
 * 
 * Clase abstracta / base que extiende de Eloquent Model e incorpora
 * el Trait HasGeneratedID para asignaci�n autom�tica de IDs enteros secuenciales.
 * el trait HasImage permite que los modelos que hereden de este puedan tener imagenes.
 */
class ApiModel extends Model
{
    use HasGeneratedID, HasImage;
}
