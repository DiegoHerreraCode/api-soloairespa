<?php

namespace App\Services;

use App\Models\Equipo;
use Illuminate\Support\Facades\DB;

/**
 * Service EquipoService
 * 
 * Gestiona el inventario de equipos completos serializados.
 * Utiliza borrado lógico mediante la columna "is_deleted".
 */
class EquipoService
{
    /**
     * Retorna todos los equipos activos (no eliminados).
     * Consulta SQL Raw:
     * SELECT * FROM equipos WHERE is_deleted = false;
     */
    public static function getAll()
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "equipos" WHERE "is_deleted" = false;
        return Equipo::where('is_deleted', false)->get();
    }

    /**
     * Obtiene un equipo activo por su clave primaria.
     * Consulta SQL Raw:
     * SELECT * FROM equipos WHERE id_equipo = $id AND is_deleted = false LIMIT 1;
     */
    public static function getOne($id)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "equipos" WHERE "is_deleted" = false AND "id_equipo" = :id LIMIT 1;
        return Equipo::where('is_deleted', false)->find($id);
    }

    /**
     * Registra un equipo físico en base de datos.
     * Consulta SQL Raw:
     * INSERT INTO equipos (id_inventario, id_detalle_compra, serial, nombre, is_deleted) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $equipo = Equipo::create($data);
        DB::commit();
        return $equipo;
    }

    /**
     * Modifica los datos de un equipo.
     * Consulta SQL Raw:
     * UPDATE equipos SET serial = ..., nombre = ... WHERE id_equipo = $id;
     */
    public static function update($id, $data)
    {
        $equipo = Equipo::find($id);
        if (!$equipo) {
            return null;
        }

        DB::beginTransaction();
        $equipo->update($data);
        DB::commit();
        return $equipo;
    }

    /**
     * Borrado lógico del equipo (marca is_deleted = true).
     * Consulta SQL Raw:
     * UPDATE equipos SET is_deleted = true WHERE id_equipo = $id;
     */
    public static function delete($id)
    {
        $equipo = Equipo::find($id);
        if (!$equipo) {
            return null;
        }

        DB::beginTransaction();
        $equipo->update(['is_deleted' => true]);
        DB::commit();
        return $equipo;
    }
}
