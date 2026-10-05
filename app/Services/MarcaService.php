<?php

namespace App\Services;

use App\Models\Marca;
use Illuminate\Support\Facades\DB;

/**
 * Service MarcaService
 * 
 * Gestiona el cat�logo de marcas comerciales.
 */
class MarcaService
{
    /**
     * Retorna el listado completo de marcas.
     * Consulta SQL Raw:
     * SELECT * FROM marcas;
     */
    public static function getAll()
    {
        return Marca::get();
    }

    /**
     * Obtiene una marca por su clave primaria.
     * Consulta SQL Raw:
     * SELECT * FROM marcas WHERE id_marca = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Marca::find($id);
    }

    /**
     * Registra una nueva marca.
     * Consulta SQL Raw:
     * INSERT INTO marcas (nombre) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $marca = Marca::create($data);
        DB::commit();
        return $marca;
    }

    /**
     * Modifica el nombre de una marca.
     * Consulta SQL Raw:
     * UPDATE marcas SET nombre = ... WHERE id_marca = $id;
     */
    public static function update($id, $data)
    {
        $marca = Marca::find($id);
        if (!$marca) {
            return null;
        }

        DB::beginTransaction();
        $marca->update($data);
        DB::commit();
        return $marca;
    }

    /**
     * Elimina una marca de la base de datos.
     * Consulta SQL Raw:
     * DELETE FROM marcas WHERE id_marca = $id;
     */
    public static function delete($id)
    {
        $marca = Marca::find($id);
        if (!$marca) {
            return null;
        }

        DB::beginTransaction();
        $marca->delete();
        DB::commit();
        return $marca;
    }
}
