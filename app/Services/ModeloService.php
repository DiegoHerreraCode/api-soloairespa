<?php

namespace App\Services;

use App\Models\Modelo;
use Illuminate\Support\Facades\DB;

/**
 * Service ModeloService
 * 
 * Gestiona los modelos específicos asociados a cada marca.
 */
class ModeloService
{
    /**
     * Lista todos los modelos cargando con eager loading su marca relacionada.
     * Consulta SQL Raw:
     * SELECT * FROM modelos;
     * SELECT * FROM marcas WHERE id_marca IN (...);
     */
    public static function getAll()
    {
        return Modelo::with('marca')->get();
    }

    /**
     * Obtiene un modelo específico por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM modelos WHERE id_modelo = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Modelo::with('marca')->find($id);
    }

    /**
     * Crea un nuevo modelo vinculado a una marca.
     * Consulta SQL Raw:
     * INSERT INTO modelos (id_marca, nombre, descripcion) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $modelo = Modelo::create($data);
        DB::commit();
        return $modelo;
    }

    /**
     * Actualiza la información de un modelo.
     * Consulta SQL Raw:
     * UPDATE modelos SET id_marca = ..., nombre = ..., descripcion = ... WHERE id_modelo = $id;
     */
    public static function update($id, $data)
    {
        $modelo = Modelo::find($id);
        if (!$modelo) {
            return null;
        }

        DB::beginTransaction();
        $modelo->update($data);
        DB::commit();
        return $modelo;
    }

    /**
     * Elimina un modelo de la base de datos.
     * Consulta SQL Raw:
     * DELETE FROM modelos WHERE id_modelo = $id;
     */
    public static function delete($id)
    {
        $modelo = Modelo::find($id);
        if (!$modelo) {
            return null;
        }

        DB::beginTransaction();
        $modelo->delete();
        DB::commit();
        return $modelo;
    }
}
