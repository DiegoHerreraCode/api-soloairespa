<?php

namespace App\Services;

use App\Models\Repuesto;
use Illuminate\Support\Facades\DB;

class RepuestoService
{
    public static function getAll()
    {
        return Repuesto::get();
    }

    public static function getOne($id)
    {
        return Repuesto::find($id);
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $repuesto = Repuesto::create($data);

        DB::commit();

        return $repuesto;
    }

    public static function update($id, $data)
    {
        $repuesto = Repuesto::find($id);
        if (!$repuesto) {
            return null;
        }

        DB::beginTransaction();

        $repuesto->update($data);

        DB::commit();

        return $repuesto;
    }

    public static function delete($id)
    {
        $repuesto = Repuesto::find($id);
        if (!$repuesto) {
            return null;
        }

        DB::beginTransaction();
        $repuesto->delete();
        DB::commit();

        return $repuesto;
    }
}