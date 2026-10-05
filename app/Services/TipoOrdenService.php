<?php

namespace App\Services;

use App\Models\TipoOrden;
use Illuminate\Support\Facades\DB;

/**
 * Service TipoOrdenService
 * 
 * Gestiona los tipos de órdenes del sistema (venta, recambio, reparación).
 */
class TipoOrdenService
{
    /**
     * Lista todos los tipos de órdenes disponibles.
     * Consulta SQL Raw:
     * SELECT * FROM tipos_ordenes;
     */
    public static function getAll()
    {
        return TipoOrden::get();
    }

    /**
     * Obtiene un tipo de orden por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM tipos_ordenes WHERE id_tipo_orden = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return TipoOrden::find($id);
    }

    /**
     * Registra un nuevo tipo de orden.
     * Consulta SQL Raw:
     * INSERT INTO tipos_ordenes (nombre, descripcion) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $tipoOrden = TipoOrden::create($data);
        DB::commit();
        return $tipoOrden;
    }

    /**
     * Actualiza la información de un tipo de orden.
     * Consulta SQL Raw:
     * UPDATE tipos_ordenes SET nombre = ..., descripcion = ... WHERE id_tipo_orden = $id;
     */
    public static function update($id, $data)
    {
        $tipoOrden = TipoOrden::find($id);
        if (!$tipoOrden) {
            return null;
        }

        DB::beginTransaction();
        $tipoOrden->update($data);
        DB::commit();
        return $tipoOrden;
    }

    /**
     * Elimina un tipo de orden.
     * Consulta SQL Raw:
     * DELETE FROM tipos_ordenes WHERE id_tipo_orden = $id;
     */
    public static function delete($id)
    {
        $tipoOrden = TipoOrden::find($id);
        if (!$tipoOrden) {
            return null;
        }

        DB::beginTransaction();
        $tipoOrden->delete();
        DB::commit();
        return $tipoOrden;
    }
}
