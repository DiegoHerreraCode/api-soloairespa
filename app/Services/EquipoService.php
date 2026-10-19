<?php

namespace App\Services;

use App\Models\Equipo;
use App\Models\Inventario;
use Illuminate\Support\Facades\DB;

/**
 * Service EquipoService
 * 
 * Gestiona el inventario de equipos completos serializados.
 * Utiliza borrado l�gico mediante la columna "is_deleted".
 */
class EquipoService
{
    /**
     * Retorna todos los equipos activos (no eliminados).
     * Consulta SQL Raw:
     * SELECT * FROM equipos;
     */
    public static function getAll()
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "equipos";
        return Equipo::get();
    }

    /**
     * Obtiene un equipo activo por su clave primaria.
     * Consulta SQL Raw:
     * SELECT * FROM equipos WHERE id_equipo = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "equipos" WHERE "id_equipo" = :id LIMIT 1;
        return Equipo::find($id);
    }

    /**
     * Registra un equipo f�sico en base de datos.
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
        // si cambio is_deleted de true a false actualizar cantidad en tabla inventario
        if ($equipo->wasChanged('is_deleted') && $equipo->is_deleted == false) {
            $inventario = Inventario::find($equipo->id_inventario);
            if ($inventario) {
                $inventario->cantidad_total++;
                $inventario->cantidad_propia++;
                $inventario->save();

                if ($inventario->cantidad_propia < $inventario->stock_minimo) {
                    // notificar si el stock es bajo
                    InventarioService::verificarYNotificarStockBajo([$inventario->id_inventario]);
                }
            }
        }
        DB::commit();
        return $equipo;
    }

    /**
     * Borrado l�gico del equipo.
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
        // actualizar cantidad en tabla inventario
        $inventario = Inventario::find($equipo->id_inventario);
        if ($inventario) {
            $inventario->cantidad_total--;
            $inventario->cantidad_propia--;
            $inventario->save();

            if ($inventario->cantidad_propia < $inventario->stock_minimo) {
                // notificar si el stock es bajo
                InventarioService::verificarYNotificarStockBajo([$inventario->id_inventario]);
            }
        }
        DB::commit();
        return $equipo;
    }
}
