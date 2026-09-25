<?php

namespace App\Services;

use App\Models\Equipo;
use Illuminate\Support\Facades\DB;

class EquipoService
{
    public static function getAll()
    {
        $equipos = Equipo::with(['inventario.modelo.marca', 'detalleCompra.compra'])->where('is_deleted', false)->get();
        return $equipos;
    }

    public static function getOne($id)
    {
        $equipo = Equipo::with(['inventario.modelo.marca', 'detalleCompra.compra'])->where('is_deleted', false)->find($id);
        return $equipo;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $equipo = Equipo::create($data);
        DB::commit();
        return $equipo;
    }

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

    public static function delete($id)
    {
        $equipo = Equipo::find($id);
        if (!$equipo) {
            return null;
        }

        DB::beginTransaction();
        // Borrado lógico según la columna is_deleted del DDL
        $equipo->update(['is_deleted' => true]);
        DB::commit();
        return $equipo;
    }
}
