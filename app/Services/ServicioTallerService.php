<?php

namespace App\Services;

use App\Models\ServicioTaller;
use Illuminate\Support\Facades\DB;

class ServicioTallerService
{
    public static function getAll()
    {
        $servicios = ServicioTaller::get();
        return $servicios;
    }

    public static function getOne($id)
    {
        $servicio = ServicioTaller::find($id);
        return $servicio;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $servicio = ServicioTaller::create($data);
        DB::commit();
        return $servicio;
    }

    public static function update($id, $data)
    {
        $servicio = ServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        DB::beginTransaction();
        $servicio->update($data);
        DB::commit();
        return $servicio;
    }

    public static function delete($id)
    {
        $servicio = ServicioTaller::find($id);
        if (!$servicio) {
            return null;
        }

        DB::beginTransaction();
        $servicio->delete();
        DB::commit();
        return $servicio;
    }
}
