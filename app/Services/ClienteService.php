<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    public static function getAll()
    {
        $clientes = Cliente::get();
        return $clientes;
    }

    public static function getOne($id)
    {
        $cliente = Cliente::find($id);
        return $cliente;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $cliente = Cliente::create($data);
        DB::commit();
        return $cliente;
    }

    public static function update($id, $data)
    {
        $cliente = Cliente::find($id);
        if (!$cliente) {
            return null;
        }
        DB::beginTransaction();
        $cliente->update($data);
        DB::commit();
        return $cliente;
    }

    public static function delete($id)
    {
        $cliente = Cliente::find($id);
        if (!$cliente) {
            return null;
        }
        DB::beginTransaction();
        $cliente->delete();
        DB::commit();
        return $cliente;
    }
}
