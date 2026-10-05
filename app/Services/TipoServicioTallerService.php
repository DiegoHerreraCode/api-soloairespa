<?php

namespace App\Services;

use App\Models\TipoServicioTaller;
use Illuminate\Support\Facades\DB;

/**
 * Service TipoServicioTallerService
 * 
 * Gestiona las categorías para clasificar las labores técnicas de taller.
 */
class TipoServicioTallerService
{
    /**
     * Lista todas las categorías de servicios de taller.
     * Consulta SQL Raw:
     * SELECT * FROM tipos_servicios_taller;
     */
    public static function getAll()
    {
        return TipoServicioTaller::get();
    }

    /**
     * Obtiene una categoría de taller por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM tipos_servicios_taller WHERE id_tipo_servicio_taller = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return TipoServicioTaller::find($id);
    }

    /**
     * Registra una nueva categoría de taller.
     * Consulta SQL Raw:
     * INSERT INTO tipos_servicios_taller (nombre, descripcion) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $tipo = TipoServicioTaller::create($data);
        DB::commit();
        return $tipo;
    }

    /**
     * Modifica el nombre o descripción de la categoría.
     * Consulta SQL Raw:
     * UPDATE tipos_servicios_taller SET nombre = ..., descripcion = ... WHERE id_tipo_servicio_taller = $id;
     */
    public static function update($id, $data)
    {
        $tipo = TipoServicioTaller::find($id);
        if (!$tipo) {
            return null;
        }

        DB::beginTransaction();
        $tipo->update($data);
        DB::commit();
        return $tipo;
    }

    /**
     * Elimina una categoría de taller.
     * Consulta SQL Raw:
     * DELETE FROM tipos_servicios_taller WHERE id_tipo_servicio_taller = $id;
     */
    public static function delete($id)
    {
        $tipo = TipoServicioTaller::find($id);
        if (!$tipo) {
            return null;
        }

        DB::beginTransaction();
        $tipo->delete();
        DB::commit();
        return $tipo;
    }
}
