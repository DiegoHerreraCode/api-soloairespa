<?php

namespace App\Services;

use App\Models\PagoCompra;
use App\Models\Compra;
use App\Enums\CompraEstado;
use App\Enums\PagoCompraEstado;
use Illuminate\Support\Facades\DB;

class PagoCompraService
{
    public static function getAll()
    {
        $pagos = PagoCompra::with(['compra', 'admin'])->get();
        return $pagos;
    }

    public static function getOne($id)
    {
        $pago = PagoCompra::with(['compra', 'admin'])->find($id);
        return $pago;
    }

    public static function getByCompra($idCompra)
    {
        $pagos = PagoCompra::with('admin')->where('id_compra', $idCompra)->get();
        return $pagos;
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $pago = PagoCompra::create($data);

        // Si el pago nace como 'realizado', actualizamos saldo pendiente y estado de la compra
        self::recalcularSaldoCompra($pago->id_compra);

        DB::commit();
        return $pago;
    }

    public static function update($id, $data)
    {
        $pago = PagoCompra::find($id);
        if (!$pago) {
            return null;
        }

        DB::beginTransaction();
        $pago->update($data);

        self::recalcularSaldoCompra($pago->id_compra);

        DB::commit();
        return $pago;
    }

    public static function delete($id)
    {
        $pago = PagoCompra::find($id);
        if (!$pago) {
            return null;
        }

        DB::beginTransaction();
        $idCompra = $pago->id_compra;
        $pago->delete();

        self::recalcularSaldoCompra($idCompra);

        DB::commit();
        return $pago;
    }

    public static function recalcularSaldoCompra($idCompra)
    {
        $compra = Compra::find($idCompra);
        if (!$compra) {
            return;
        }

        // Sumar solo los pagos que ya fueron ejecutados ('realizado')
        $totalPagado = (float) PagoCompra::where('id_compra', $idCompra)
            ->where('estado', PagoCompraEstado::REALIZADO->value)
            ->sum('monto_a_pagar');

        $nuevoPendiente = max(0, (float) $compra->monto_total - $totalPagado);

        if ($totalPagado <= 0) {
            $estadoCompra = CompraEstado::POR_PAGAR;
        } elseif ($nuevoPendiente <= 0) {
            $estadoCompra = CompraEstado::PAGADA;
        } else {
            $estadoCompra = CompraEstado::PAGADA_PARCIAL;
        }

        $compra->update([
            'monto_pendiente' => round($nuevoPendiente, 2),
            'estado' => $estadoCompra,
        ]);
    }
}
