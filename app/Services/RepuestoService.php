<?php

namespace App\Services;

use App\Models\Repuesto;
use Illuminate\Support\Facades\DB;

/**
 * Service RepuestoService
 * 
 * Gestiona las operaciones de persistencia directa sobre la tabla de piezas serializadas (repuestos).
 */
class RepuestoService
{
    /**
     * Lista todos los repuestos físicos individuales.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos;
     */
    public static function getAll()
    {
        return Repuesto::get();
    }

    /**
     * Obtiene un repuesto por su identificador primario.
     * Consulta SQL Raw:
     * SELECT * FROM repuestos WHERE id_repuesto = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Repuesto::find($id);
    }

    /**
     * Registra una nueva pieza de repuesto serializada.
     * Consulta SQL Raw:
     * INSERT INTO repuestos (id_inventario, serial, nombre, estado, propietario, costo_adquisicion, ...) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $repuesto = Repuesto::create($data);
        DB::commit();
        return $repuesto;
    }

    /**
     * Actualiza atributos físicos o económicos de un repuesto.
     * Consulta SQL Raw:
     * UPDATE repuestos SET estado = ..., costo_total = ..., utilidad = ... WHERE id_repuesto = $id;
     */
    public static function update($id, $data)
    {
        $repuesto = Repuesto::find($id);
        if (!$repuesto) {
            return null;
        }

        DB::beginTransaction();
        $repuesto->update($data);
        DB::commit();
        return $repuesto;
    }

    /**
     * Elimina el registro de un repuesto.
     * Consulta SQL Raw:
     * DELETE FROM repuestos WHERE id_repuesto = $id;
     */
    public static function delete($id)
    {
        $repuesto = Repuesto::find($id);
        if (!$repuesto) {
            return null;
        }

        DB::beginTransaction();
        $repuesto->delete();
        DB::commit();
        return $repuesto;
    }
}
