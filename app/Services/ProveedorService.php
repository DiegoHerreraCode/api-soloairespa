<?php

namespace App\Services;

use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;

class ProveedorService
{
    public static function getAll()
    {
        $proveedores = Proveedor::get();
        return $proveedores;
    }

    public static function getOne($id)
    {
        $proveedor = Proveedor::find($id);
        return $proveedor;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $proveedor = Proveedor::create($data);
        DB::commit();
        return $proveedor;
    }

    public static function update($id, $data)
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return null;
        }

        DB::beginTransaction();
        $proveedor->update($data);
        DB::commit();
        return $proveedor;
    }

    public static function delete($id)
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return null;
        }

        DB::beginTransaction();
        $proveedor->delete();
        DB::commit();
        return $proveedor;
    }
}
