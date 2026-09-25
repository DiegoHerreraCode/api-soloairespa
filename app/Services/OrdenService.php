<?php

namespace App\Services;

use App\Models\Orden;
use Illuminate\Support\Facades\DB;

class OrdenService
{
    public static function getAll()
    {
        $ordenes = Orden::with(['cliente', 'admin'])->get();
        return $ordenes;
    }

    public static function getOne($id)
    {
        $orden = Orden::with(['cliente', 'admin'])->find($id);
        return $orden;
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $data['fecha_creacion'] = $data['fecha_creacion'] ?? now();
        $data['last_update']    = now();

        $orden = Orden::create($data);
        DB::commit();
        return $orden;
    }

    public static function update($id, $data)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return null;
        }

        DB::beginTransaction();

        $data['last_update'] = now();

        // Si se está anulando la orden
        if (isset($data['estado_operativo']) && $data['estado_operativo'] === 'anulada') {
            $data['fecha_anulacion'] = now();
        }

        $orden->update($data);
        DB::commit();
        return $orden;
    }

    public static function delete($id)
    {
        $orden = Orden::find($id);
        if (!$orden) {
            return null;
        }

        DB::beginTransaction();
        $orden->delete();
        DB::commit();
        return $orden;
    }
}
