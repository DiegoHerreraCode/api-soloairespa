<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Secuencia
 * 
 * Almacena el último ID consecutivo asignado para cada clase de modelo de la aplicación.
 * Permite autoincrementables concurrentes seguros mediante lockForUpdate().
 */
class Secuencia extends Model
{
    protected $table = 'secuencias';

    protected $primaryKey = 'modelo';
    protected $keyType = 'string';

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'modelo',
        'ultimo_id',
    ];
}
