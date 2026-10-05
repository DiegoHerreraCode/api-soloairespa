<?php

namespace App\Services;

use App\Models\PagoCompra;
use App\Models\Compra;
use App\Enums\CompraEstado;
use App\Enums\PagoCompraEstado;
use Illuminate\Support\Facades\DB;

/**
 * Service PagoCompraService
 * 
 * Gestiona los abonos y cuotas pagadas a proveedores por concepto de compras.
 * Mantiene actualizado en tiempo real el saldo pendiente y el estado de la compra:
 * - 'por_pagar': Ningún abono realizado.
 * - 'pagada_parcial': Se han efectuado pagos pero aún existe saldo pendiente.
 * - 'pagada': Saldo pendiente liquidado a cero.
 */
class PagoCompraService
{
    /**
     * Retorna todos los registros de pagos a proveedores.
     * Consulta SQL Raw:
     * SELECT * FROM pagos_compras;
     */
    public static function getAll()
    {
        return PagoCompra::get();
    }

    /**
     * Obtiene un pago de compra específico por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM pagos_compras WHERE id_pago_compra = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return PagoCompra::find($id);
    }

    /**
     * Obtiene todos los pagos asociados a una compra específica junto al administrador que los gestionó.
     * Consulta SQL Raw:
     * SELECT * FROM pagos_compras WHERE id_compra = $idCompra;
     * SELECT * FROM admins WHERE id_admin IN (...);
     */
    public static function getByCompra($idCompra)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "pagos_compras" WHERE "id_compra" = :idCompra;
        return PagoCompra::with('admin')->where('id_compra', $idCompra)->get();
    }

    /**
     * Registra un pago de compra y recalcula el saldo pendiente de la compra.
     * Consulta SQL Raw:
     * INSERT INTO pagos_compras (id_compra, id_admin, monto_a_pagar, estado, ...) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();

        $pago = PagoCompra::create($data);

        // Si el pago nace como 'realizado', actualizamos saldo pendiente y estado de la compra
        self::recalcularSaldoCompra($pago->id_compra);

        DB::commit();
        return $pago;
    }

    /**
     * Actualiza la información de un pago (ej. marcar como 'realizado' o cambiar monto) y recalcula la compra.
     * Consulta SQL Raw:
     * UPDATE pagos_compras SET estado = ..., fecha_pago = ... WHERE id_pago_compra = $id;
     */
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

    /**
     * Elimina un registro de pago y recalcula el saldo pendiente de la compra afectada.
     * Consulta SQL Raw:
     * DELETE FROM pagos_compras WHERE id_pago_compra = $id;
     */
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

    /**
     * Recalcula el monto pendiente de la compra sumando únicamente los pagos en estado 'realizado'
     * y actualiza su estado contable ('por_pagar', 'pagada_parcial' o 'pagada').
     *
     * Consulta SQL Raw:
     * SELECT * FROM compras WHERE id_compra = $idCompra LIMIT 1;
     * SELECT SUM(monto_a_pagar) FROM pagos_compras WHERE id_compra = $idCompra AND estado = 'realizado';
     * UPDATE compras SET monto_pendiente = ..., estado = ... WHERE id_compra = $idCompra;
     */
    public static function recalcularSaldoCompra($idCompra)
    {
        $compra = Compra::find($idCompra);
        if (!$compra) {
            return;
        }

        // Sumar solo los pagos que ya fueron ejecutados ('realizado')
        // Consulta SQL Raw equivalente:
        // SELECT COALESCE(SUM("monto_a_pagar"), 0) FROM "pagos_compras" WHERE "id_compra" = :idCompra AND "estado" = 'realizado';
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
            'estado'          => $estadoCompra,
        ]);
    }
}
