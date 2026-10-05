<?php

namespace App\Services;

use App\Models\PagoCliente;
use App\Models\Orden;
use App\Services\OrdenService;
use App\Enums\OrdenEstadoAdmin;
use App\Enums\PagoClienteEstado;
use Illuminate\Support\Facades\DB;

/**
 * Service PagoClienteService
 * 
 * Gestiona los cobros y amortizaciones realizados por los clientes sobre sus órdenes de trabajo:
 * - Valida que en órdenes de reparación sólo se pueda cobrar tras haber finalizado el trabajo operativo.
 * - Registra pagos y descuenta automáticamente el monto pendiente de la orden.
 * - Actualiza el estado administrativo a 'pagada' si el saldo pendiente llega a cero.
 * - Soporta anulación de pagos con registro de auditoría (motivo, fecha y administrador).
 */
class PagoClienteService
{
    /**
     * Retorna todos los pagos de clientes registrados.
     * Consulta SQL Raw:
     * SELECT * FROM pagos_clientes;
     */
    public static function getAll()
    {
        return PagoCliente::get();
    }

    /**
     * Obtiene un pago de cliente por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM pagos_clientes WHERE id_pago_cliente = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return PagoCliente::find($id);
    }

    /**
     * Obtiene el historial de pagos asociados a una orden de trabajo junto a los datos del cliente.
     * Consulta SQL Raw:
     * SELECT * FROM pagos_clientes WHERE id_orden = $idOrden;
     * SELECT * FROM clientes WHERE id_cliente IN (...);
     */
    public static function getByOrden($idOrden)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "pagos_clientes" WHERE "id_orden" = :idOrden;
        return PagoCliente::with('cliente')->where('id_orden', $idOrden)->get();
    }

    /**
     * Registra un pago de cliente:
     * 1. Verifica que si es reparación, la orden ya esté finalizada operativamente.
     * 2. Inserta el pago en la base de datos.
     * 3. Suma los pagos activos y recalcula el saldo pendiente de la orden.
     * 4. Transiciona a 'pagada' si el saldo queda en cero.
     *
     * Consulta SQL Raw:
     * SELECT * FROM ordenes WHERE id_orden = ... LIMIT 1;
     * INSERT INTO pagos_clientes (id_cliente, id_orden, monto, ...) VALUES (...);
     * SELECT SUM(monto) FROM pagos_clientes WHERE id_orden = ... AND estado != 'anulado';
     * UPDATE ordenes SET monto_pendiente = ..., estado_administrativo = ... WHERE id_orden = ...;
     */
    public static function create($data)
    {
        // En órdenes de tipo reparación sólo se permite registrar pagos cuando el estado operativo es finalizada
        $ordenCheck = Orden::with('tipoOrden')->find($data['id_orden'] ?? null);
        if ($ordenCheck) {
            $nombreTipo = $ordenCheck->tipoOrden ? strtolower(trim($ordenCheck->tipoOrden->nombre)) : '';
            $estadoOperativo = $ordenCheck->estado_operativo instanceof \App\Enums\OrdenEstadoOperativo
                ? $ordenCheck->estado_operativo->value
                : (string) $ordenCheck->estado_operativo;

            if ($nombreTipo === 'reparacion' && $estadoOperativo !== 'finalizada') {
                return false;
            }
        }

        DB::beginTransaction();

        if (!empty($data['fecha_pago'])) {
            $data['fecha_pago'] = strlen($data['fecha_pago']) === 10
                ? $data['fecha_pago'] . ' ' . now()->format('H:i:s')
                : $data['fecha_pago'];
        } else {
            $data['fecha_pago'] = now();
        }

        $pago = PagoCliente::create($data);

        // Actualizar saldo pendiente de la orden y su estado administrativo
        $orden = Orden::find($pago->id_orden);
        if ($orden) {
            // Consulta SQL Raw equivalente:
            // SELECT COALESCE(SUM("monto"), 0) FROM "pagos_clientes" WHERE "id_orden" = :id_orden AND "estado" != 'anulado';
            $totalPagado = (float) PagoCliente::where('id_orden', $orden->id_orden)->where('estado', '!=', PagoClienteEstado::ANULADO->value)->sum('monto');
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

    /**
     * Modifica los datos de un pago y recalcula el saldo de la orden.
     * Consulta SQL Raw:
     * UPDATE pagos_clientes SET monto = ..., metodo_pago = ... WHERE id_pago_cliente = $id;
     * SELECT SUM(monto) FROM pagos_clientes WHERE id_orden = ... AND estado != 'anulado';
     * UPDATE ordenes SET monto_pendiente = ..., estado_administrativo = ... WHERE id_orden = ...;
     */
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
            // Consulta SQL Raw equivalente:
            // SELECT COALESCE(SUM("monto"), 0) FROM "pagos_clientes" WHERE "id_orden" = :id_orden AND "estado" != 'anulado';
            $totalPagado = (float) PagoCliente::where('id_orden', $orden->id_orden)->where('estado', '!=', PagoClienteEstado::ANULADO->value)->sum('monto');
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

    /**
     * Elimina físicamente un pago y reajusta el saldo de la orden asociada.
     * Consulta SQL Raw:
     * DELETE FROM pagos_clientes WHERE id_pago_cliente = $id;
     * SELECT SUM(monto) FROM pagos_clientes WHERE id_orden = ... AND estado != 'anulado';
     * UPDATE ordenes SET monto_pendiente = ..., estado_administrativo = ... WHERE id_orden = ...;
     */
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
            // Consulta SQL Raw equivalente:
            // SELECT COALESCE(SUM("monto"), 0) FROM "pagos_clientes" WHERE "id_orden" = :idOrden AND "estado" != 'anulado';
            $totalPagado = (float) PagoCliente::where('id_orden', $idOrden)->where('estado', '!=', PagoClienteEstado::ANULADO->value)->sum('monto');
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

    /**
     * Anula lógicamente un pago de cliente con auditoría y restituye el saldo pendiente en la orden.
     *
     * Consulta SQL Raw:
     * UPDATE pagos_clientes SET estado = 'anulado', fecha_anulacion = now(), id_admin_anulacion = ..., motivo_anulacion = ... WHERE id_pago_cliente = $id;
     * SELECT SUM(monto) FROM pagos_clientes WHERE id_orden = ... AND estado != 'anulado';
     * UPDATE ordenes SET monto_pendiente = ..., estado_administrativo = 'pendiente_pago' WHERE id_orden = ...;
     */
    public static function anular($id, array $data)
    {
        $pago = PagoCliente::find($id);
        if (!$pago) {
            return null;
        }

        $estadoStr = $pago->estado instanceof PagoClienteEstado ? $pago->estado->value : (string) $pago->estado;
        if ($estadoStr === PagoClienteEstado::ANULADO->value) {
            return false;
        }

        DB::beginTransaction();

        $pago->update([
            'estado'              => PagoClienteEstado::ANULADO->value,
            'fecha_anulacion'     => now(),
            'id_admin_anulacion'  => $data['id_admin_anulacion'] ?? auth()->id() ?? 1,
            'motivo_anulacion'    => $data['motivo_anulacion'] ?? 'Anulación de pago',
        ]);

        // Recalcular saldo pendiente de la orden considerando únicamente pagos activos (no anulados)
        $orden = Orden::find($pago->id_orden);
        if ($orden) {
            $totalPagado = (float) PagoCliente::where('id_orden', $orden->id_orden)
                ->where('estado', '!=', PagoClienteEstado::ANULADO->value)
                ->sum('monto');

            $nuevoPendiente = max(0, (float) $orden->monto_total - $totalPagado);
            $estadoAdmin = ($nuevoPendiente <= 0 && (float) $orden->monto_total > 0) ? OrdenEstadoAdmin::PAGADA : OrdenEstadoAdmin::PENDIENTE_PAGO;

            $orden->update([
                'monto_pendiente'       => round($nuevoPendiente, 2),
                'estado_administrativo' => $estadoAdmin,
                'last_update'           => now(),
            ]);
        }

        DB::commit();

        return $pago->fresh();
    }
}
