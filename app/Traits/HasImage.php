<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

trait HasImage
{
    public static function bootHasImage()
    {
        static::saving(function (Model $model) {

            $modeloClassName = get_class($model);

            // Obtener el path de almacenamiento del modelo (por defecto 'images')
            $path = defined($modeloClassName.'::IMAGE_PATH') ? constant($modeloClassName.'::IMAGE_PATH') : null;
            if (! $path) { // el modelo no maneja imagenes
                return;
            }

            // Definir el nombre del campo en el request (por defecto 'image')
            // Se puede sobrescribir en el modelo con const IMAGE_FIELD = 'mi_campo_file';
            $imageField = defined($modeloClassName.'::IMAGE_FIELD') ? constant($modeloClassName.'::IMAGE_FIELD') : 'image';
            $imagePathField = defined($modeloClassName.'::IMAGE_PATH_FIELD') ? constant($modeloClassName.'::IMAGE_PATH_FIELD') : 'imagePath';

            // Verificar si hay un archivo en el request actual
            // el request lo tomo usando el helper request(), que es el que se encarga de obtener los datos del request
            // aqui laravel no puede inyectar el request, por eso se usa el helper request()
            $request = request();
            if ($request->hasFile($imageField)) {
                $file = $request->file($imageField);

                $disk = config('filesystems.default') === 's3' ? 's3' : 'public';

                // Eliminar imagen anterior si existe (caso Update)
                if ($model->exists) { // si el modelo no es nuevo
                    // Asumimos que el modelo tiene un campo 'imagePath' para la ruta relativa
                    // Si el nombre del atributo es otro, habría que parametrizarlo también

                    if ($model->$imagePathField && Storage::disk($disk)->exists($model->$imagePathField)) {
                        Storage::disk($disk)->delete($model->$imagePathField);
                    }
                }

                // Guardar la nueva imagen
                $nombreOriginal = time().'-'.$file->getClientOriginalName();
                $imagePath = $file->storeAs($path, $nombreOriginal, $disk);

                // Asignar los valores al modelo
                $model->$imageField = $nombreOriginal;
                $model->$imagePathField = $imagePath;
            }
        });

        static::deleting(function (Model $model) {
            $modeloClassName = get_class($model);
            $imagePathField = defined($modeloClassName.'::IMAGE_PATH_FIELD') ? constant($modeloClassName.'::IMAGE_PATH_FIELD') : 'imagePath';
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            // Eliminar imagen al borrar el modelo
            if ($model->$imagePathField && Storage::disk($disk)->exists($model->$imagePathField)) {
                Storage::disk($disk)->delete($model->$imagePathField);
            }
        });
    }
}
