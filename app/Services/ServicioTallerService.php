<?php

namespace App\Services;

use App\Models\ServicioTaller;
use Illuminate\Support\Facades\DB;

/**
 * Service ServicioTallerService
 * 
 * Gestiona el catálogo de servicios técnicos y mano de obra del taller.
 */
class ServicioTallerService
{
    /**
     * Retorna todos los servicios de taller.
     * Consulta SQL Raw:
     * SELECT * FROM servicios_taller;
     */
    public static function getAll()
    {
        return ServicioTaller::get();
    }

    /**
     * Obtiene un servicio de taller por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM servicios_taller WHERE id_servicio_taller = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return ServicioTaller::find($id);
    }

    /**
     * Registra un nuevo servicio de taller en el catálogo.
     * Consulta SQL Raw:
     * INSERT INTO servicios_taller (id_tipo_servicio_taller, nombre, descripcion, costo_base, porcentaje_iva, porcentaje_ganancia) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $servicio = ServicioTaller::create($data);
        DB::commit();
        return $servicio;
    }

    /**
     * Actualiza tarifas o descripción de un servicio de taller.
     * Consulta SQL Raw:
     * UPDATE servicios_taller SET nombre = ..., costo_base = ..., porcentaje_ganancia = ... WHERE id_servicio_taller = $id;
     */
    public static function update($id, $data)
    {
        $servicio = ServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        DB::beginTransaction();
        $servicio->update($data);
        DB::commit();
        return $servicio;
    }

    /**
     * Elimina un servicio del catálogo de taller.
     * Consulta SQL Raw:
     * DELETE FROM servicios_taller WHERE id_servicio_taller = $id;
     */
    public static function delete($id)
    {
        $servicio = ServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        DB::beginTransaction();
        $servicio->delete();
        DB::commit();
        return $servicio;
    }
}
