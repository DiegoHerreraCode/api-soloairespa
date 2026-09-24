<?php

namespace App\Traits;

use App\Models\Secuencia;

trait HasGeneratedID
{
    public static function bootHasGeneratedID()
    {
        static::creating(function ($model) {

            //  $datos = request()->all(); 

            // Si el ID ya está seteado, no hacemos nada
            // if ($model->getKey()) {
            //     return;
            // }

            $modeloClassName = get_class($model);

            // Buscar o crear la secuencia para este modelo
            // Usamos lockForUpdate para evitar condiciones de carrera (si la base de datos lo soporta)
            // $secuencia = Secuencia::where('modelo','=' , $modeloClassName)->lockForUpdate()->first();
            $secuencia = Secuencia::lockForUpdate()->find($modeloClassName);

            if (!$secuencia) {
                $secuencia = Secuencia::create([
                    'modelo' => $modeloClassName,
                    'ultimo_id' => 0
                ]);
            }

            $secuencia->increment('ultimo_id');

            // Generar el ID formateado
            // $padding = defined("$modeloClassName::PADDING") ? constant("$modeloClassName::PADDING") : 5;
            // $nuevoId = str_pad($secuencia->ultimo_id, $padding, '0', STR_PAD_LEFT);


            $primaryKey = $model->getKeyName();

            $model->$primaryKey = $secuencia->ultimo_id;

            // $model->$primaryKey = $nuevoId; // $model->id_producto = $nuevoId;

        });

    }
}

/*

En Laravel, si un Trait tiene un método con el nombre boot[NombreDelTrait],
 Eloquent lo ejecutará automáticamente cuando el modelo arranque.

En el método 
boot
 (o booted), puedes engancharte a todo el ciclo de vida del modelo. Laravel ofrece muchos eventos útiles.

Aquí tienes los más comunes separados por categoría:

Creación (INSERT)
creating: Antes de crear (Ideal para generar IDs, validaciones pre-insert).
created: Después de guardar en BD (Ideal para enviar emails de bienvenida, logs).

Actualización (UPDATE)
updating: Antes de actualizar (Ideal para calcular totales si cambiaron precios).
updated: Después de actualizar.

Guardado (INSERT o UPDATE)
saving: Se ejecuta siempre antes de guardar (ya sea crear o actualizar).
saved: Se ejecuta después de cualquiera de los dos.

Eliminación (DELETE)
deleting: Antes de borrar (Ideal para borrar archivos asociados, como imágenes).
deleted: Después de borrar.

Consultas (SELECT)
retrieved: Después de recuperar un registro de la BD.


ejemplo: 

public static function bootHasGeneratedID()
{
    // Generar ID antes de crear
    static::creating(function ($model) { ... });

    // Borrar imagen al eliminar el producto
    static::deleting(function ($model) {
        // lógica para borrar archivo del disco
    });
}

`*/


