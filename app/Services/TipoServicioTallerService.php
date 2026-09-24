<?php

namespace App\Services;

use App\Models\TipoServicioTaller;
use Illuminate\Support\Facades\DB;

class TipoServicioTallerService
{
    public static function getAll()
    {
        $tipos = TipoServicioTaller::get();
        return $tipos;
    }

    public static function getOne($id)
    {
        $tipo = TipoServicioTaller::find($id);
        return $tipo;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $tipo = TipoServicioTaller::create($data);
        DB::commit();
        return $tipo;
    }

    public static function update($id, $data)
    {
        $tipo = TipoServicioTaller::find($id);
        if (!$tipo) {
            return null;
        }

        DB::beginTransaction();
        $tipo->update($data);
        DB::commit();
        return $tipo;
    }

    public static function delete($id)
    {
        $tipo = TipoServicioTaller::find($id);
        if (!$tipo) {
            return null;
        }

        DB::beginTransaction();
        $tipo->delete();
        DB::commit();
        return $tipo;
    }
}
