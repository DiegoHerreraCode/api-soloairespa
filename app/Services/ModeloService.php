<?php

namespace App\Services;

use App\Models\Modelo;
use Illuminate\Support\Facades\DB;

class ModeloService
{
    public static function getAll()
    {
        $modelos = Modelo::get();
        return $modelos;
    }

    public static function getOne($id)
    {
        $modelo = Modelo::find($id);
        return $modelo;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $modelo = Modelo::create($data);
        DB::commit();
        return $modelo;
    }

    public static function update($id, $data)
    {
        $modelo = Modelo::find($id);
        if (!$modelo) {
            return null;
        }

        DB::beginTransaction();
        $modelo->update($data);
        DB::commit();
        return $modelo;
    }

    public static function delete($id)
    {
        $modelo = Modelo::find($id);
        if (!$modelo) {
            return null;
        }

        DB::beginTransaction();
        $modelo->delete();
        DB::commit();
        return $modelo;
    }
}
