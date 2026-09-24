<?php

namespace App\Services;

use App\Models\Marca;
use Illuminate\Support\Facades\DB;

class MarcaService
{
    public static function getAll()
    {
        $marcas = Marca::get();
        return $marcas;
    }

    public static function getOne($id)
    {
        $marca = Marca::find($id);
        return $marca;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $marca = Marca::create($data);
        DB::commit();
        return $marca;
    }

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
