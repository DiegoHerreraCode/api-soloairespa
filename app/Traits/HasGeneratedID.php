<?php

namespace App\Traits;

use App\Models\Secuencia;

/**
 * Trait HasGeneratedID
 * 
 * Generador automático de claves primarias enteras secuenciales personalizadas.
 * Se apoya en la tabla "secuencias" bloqueando el registro mediante lockForUpdate
 * para garantizar la concurrencia segura y evitar duplicidad de IDs al crear registros.
 */
trait HasGeneratedID
{
    /**
     * Método de arranque del Trait reconocido automáticamente por Eloquent (boot[TraitName]).
     * Se engancha al evento del ciclo de vida "creating" antes de que el INSERT llegue a la base de datos.
     */
    public static function bootHasGeneratedID()
    {
        static::creating(function ($model) {
            // Obtenemos el nombre completo de la clase del modelo actual (ej: "App\Models\Cliente")
            $modeloClassName = get_class($model);

            // 1. Bloqueamos y consultamos la secuencia para evitar condiciones de carrera concurrentes
            // Consulta SQL Raw equivalente:
            // SELECT * FROM secuencias WHERE modelo = 'App\Models\Cliente' FOR UPDATE;
            $secuencia = Secuencia::lockForUpdate()->find($modeloClassName);

            // 2. Si es la primera vez que este modelo genera un ID, inicializamos su secuencia en 0
            if (!$secuencia) {
                // Consulta SQL Raw equivalente:
                // INSERT INTO secuencias (modelo, ultimo_id) VALUES ('App\Models\Cliente', 0);
                $secuencia = Secuencia::create([
                    'modelo'    => $modeloClassName,
                    'ultimo_id' => 0
                ]);
            }

            // 3. Incrementamos el contador atómicamente en 1
            // Consulta SQL Raw equivalente:
            // UPDATE secuencias SET ultimo_id = ultimo_id + 1 WHERE modelo = 'App\Models\Cliente';
            $secuencia->increment('ultimo_id');

            // 4. Asignamos el nuevo ID numérico generado a la clave primaria del modelo que se está creando
            $primaryKey = $model->getKeyName();
            $model->$primaryKey = $secuencia->ultimo_id;
        });
    }
}
