<?php

namespace App\Services;

use App\Models\Inventario;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    public static function getAll()
    {
        $items = Inventario::get();
        return $items;
    }

    public static function getOne($id)
    {
        $item = Inventario::find($id);
        return $item;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $item = Inventario::create($data);
        DB::commit();
        return $item;
    }

    public static function update($id, $data)
    {
        $item = Inventario::find($id);
        if (!$item) {
            return null;
        }

        DB::beginTransaction();
        $item->update($data);
        DB::commit();
        return $item;
    }

    public static function delete($id)
    {
        $item = Inventario::find($id);
        if (!$item) {
            return null;
        }

        DB::beginTransaction();
        $item->delete();
        DB::commit();
        return $item;
    }
}
