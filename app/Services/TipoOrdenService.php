<?php

namespace App\Services;

use App\Models\TipoOrden;
use Illuminate\Support\Facades\DB;

class TipoOrdenService
{
    public static function getAll()
    {
        $tiposOrden = TipoOrden::get();
        return $tiposOrden;
    }

    public static function getOne($id)
    {
        $tipoOrden = TipoOrden::find($id);
        return $tipoOrden;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $tipoOrden = TipoOrden::create($data);
        DB::commit();
        return $tipoOrden;
    }

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
