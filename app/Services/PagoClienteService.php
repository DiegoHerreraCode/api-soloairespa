<?php

namespace App\Services;

use App\Models\PagoCliente;
use App\Models\Orden;
use App\Enums\OrdenEstadoAdmin;
use Illuminate\Support\Facades\DB;

class PagoClienteService
{
    public static function getAll()
    {
        $pagos = PagoCliente::with(['cliente', 'orden'])->get();
        return $pagos;
    }

    public static function getOne($id)
    {
        $pago = PagoCliente::with(['cliente', 'orden'])->find($id);
        return $pago;
    }

    public static function getByOrden($idOrden)
    {
        $pagos = PagoCliente::with('cliente')->where('id_orden', $idOrden)->get();
        return $pagos;
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $data['fecha_pago'] = $data['fecha_pago'] ?? now();

        $pago = PagoCliente::create($data);

        // Actualizar saldo pendiente de la orden y su estado administrativo
        $orden = Orden::find($pago->id_orden);
        if ($orden) {
            $totalPagado = (float) PagoCliente::where('id_orden', $orden->id_orden)->sum('monto');
            $nuevoPendiente = max(0, (float) $orden->monto_total - $totalPagado);

            $estadoAdmin = ($nuevoPendiente <= 0) ? OrdenEstadoAdmin::PAGADA : OrdenEstadoAdmin::PENDIENTE_PAGO;

            $orden->update([
                'monto_pendiente'       => round($nuevoPendiente, 2),
                'estado_administrativo' => $estadoAdmin,
                'last_update'           => now(),
            ]);
        }

        DB::commit();
        return $pago;
    }

    public static function update($id, $data)
    {
        $pago = PagoCliente::find($id);
        if (!$pago) {
            return null;
        }

        DB::beginTransaction();
        $pago->update($data);

        // Recalcular saldo pendiente de la orden si se modificó el monto
        $orden = Orden::find($pago->id_orden);
        if ($orden) {
            $totalPagado = (float) PagoCliente::where('id_orden', $orden->id_orden)->sum('monto');
            $nuevoPendiente = max(0, (float) $orden->monto_total - $totalPagado);

            $estadoAdmin = ($nuevoPendiente <= 0) ? OrdenEstadoAdmin::PAGADA : OrdenEstadoAdmin::PENDIENTE_PAGO;

            $orden->update([
                'monto_pendiente'       => round($nuevoPendiente, 2),
                'estado_administrativo' => $estadoAdmin,
                'last_update'           => now(),
            ]);
        }

        DB::commit();
        return $pago;
    }

    public static function delete($id)
    {
        $pago = PagoCliente::find($id);
        if (!$pago) {
            return null;
        }

        DB::beginTransaction();
        $idOrden = $pago->id_orden;
        $pago->delete();

        // Recalcular saldo pendiente de la orden tras eliminar el pago
        $orden = Orden::find($idOrden);
        if ($orden) {
            $totalPagado = (float) PagoCliente::where('id_orden', $idOrden)->sum('monto');
            $nuevoPendiente = max(0, (float) $orden->monto_total - $totalPagado);

            $estadoAdmin = ($nuevoPendiente <= 0) ? OrdenEstadoAdmin::PAGADA : OrdenEstadoAdmin::PENDIENTE_PAGO;

            $orden->update([
                'monto_pendiente'       => round($nuevoPendiente, 2),
                'estado_administrativo' => $estadoAdmin,
                'last_update'           => now(),
            ]);
        }

        DB::commit();
        return $pago;
    }
}
