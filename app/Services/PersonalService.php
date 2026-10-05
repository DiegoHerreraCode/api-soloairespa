<?php

namespace App\Services;

use App\Models\Personal;
use Illuminate\Support\Facades\DB;

/**
 * Service PersonalService
 * 
 * Gestiona el personal operativo y técnico del taller.
 * Implementa borrado lógico mediante la columna "is_deleted".
 */
class PersonalService
{
    /**
     * Obtiene el personal activo (no eliminado).
     * Consulta SQL Raw:
     * SELECT * FROM personal WHERE is_deleted = false;
     */
    public static function getAll()
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "personal" WHERE "is_deleted" = false;
        return Personal::where('is_deleted', false)->get();
    }

    /**
     * Obtiene un trabajador técnico por ID.
     * Consulta SQL Raw:
     * SELECT * FROM personal WHERE id_personal = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Personal::find($id);
    }

    /**
     * Registra un nuevo integrante en el personal.
     * Consulta SQL Raw:
     * INSERT INTO personal (nombre, rut, correo, num_tlf, direccion, disponible, is_deleted) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $personal = Personal::create($data);
        DB::commit();
        return $personal;
    }

    /**
     * Actualiza datos de un miembro del personal.
     * Consulta SQL Raw:
     * UPDATE personal SET nombre = ..., rut = ..., correo = ..., num_tlf = ..., direccion = ..., disponible = ... WHERE id_personal = $id;
     */
    public static function update($id, $data)
    {
        $personal = Personal::find($id);
        if (!$personal) {
            return null;
        }

        DB::beginTransaction();
        $personal->update($data);
        DB::commit();
        return $personal;
    }

    /**
     * Borrado lógico: marca al trabajador como is_deleted = true en lugar de removerlo de la base de datos.
     * Consulta SQL Raw:
     * UPDATE personal SET is_deleted = true WHERE id_personal = $id;
     */
    public static function delete($id)
    {
        $personal = Personal::find($id);
        if (!$personal) {
            return null;
        }

        DB::beginTransaction();
        $personal->update(['is_deleted' => true]);
        DB::commit();
        return $personal;
    }
}
