<?php

namespace App\Services;

use App\Models\Compra;
use Illuminate\Support\Facades\DB;

class CompraService
{
    public static function getAll()
    {
        $compras = Compra::get();
        return $compras;
    }

    public static function getOne($id)
    {
        $compra = Compra::find($id);
        return $compra;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $compra = Compra::create($data);
        DB::commit();
        return $compra;
    }

    public static function update($id, $data)
    {
        $compra = Compra::find($id);
        if (!$compra) {
            return null;
        }

        DB::beginTransaction();
        $compra->update($data);
        DB::commit();
        return $compra;
    }

    public static function delete($id)
    {
        $compra = Compra::find($id);
        if (!$compra) {
            return null;
        }

        DB::beginTransaction();
        $compra->delete();
        DB::commit();
        return $compra;
    }
}
