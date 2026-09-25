<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Inventario;
use App\Models\Equipo;
use App\Models\Repuesto;
use App\Enums\InventarioTipo;
use App\Enums\RepuestoEstado;
use Illuminate\Support\Facades\DB;

class CompraService
{
    public static function getAll()
    {
        // $compras = Compra::with(['detalles.inventario'])->get();
        $compras = Compra::get();
        //$compras = Compra::with(['proveedor', 'detalles.inventario.modelo.marca'])->get();
        return $compras;
    }

    public static function getOne($id)
    {
        // $compra = Compra::with(['detalles.inventario'])->find($id);
        $compra = Compra::find($id);
        return $compra;
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $detalles = $data['detalles'] ?? [];
        unset($data['detalles']);

        // 1. Cálculos de línea y totales
        if (!empty($detalles) && is_array($detalles)) {
            $totalGravado = 0.00;
            $totalExento = 0.00;
            $totalIva = 0.00;
            $montoTotal = 0.00;

            foreach ($detalles as &$linea) {
                $cantidad = (int) ($linea['cantidad'] ?? 0);
                $costoUnitario = (float) ($linea['costo_unitario'] ?? 0.00);
                $porcentajeIva = (float) ($linea['porcentaje_iva'] ?? 0.00);

                if (!isset($linea['monto_total_linea_sin_iva'])) {
                    $linea['monto_total_linea_sin_iva'] = $cantidad * $costoUnitario;
                }

                if (!isset($linea['monto_iva'])) {
                    $linea['monto_iva'] = round($linea['monto_total_linea_sin_iva'] * ($porcentajeIva / 100), 2);
                }

                if (!isset($linea['monto_total_linea_con_iva'])) {
                    $linea['monto_total_linea_con_iva'] = $linea['monto_total_linea_sin_iva'] + $linea['monto_iva'];
                }

                if ($porcentajeIva > 0) {
                    $totalGravado += (float) $linea['monto_total_linea_sin_iva'];
                } else {
                    $totalExento += (float) $linea['monto_total_linea_sin_iva'];
                }
                $totalIva += (float) $linea['monto_iva'];
                $montoTotal += (float) $linea['monto_total_linea_con_iva'];
            }
            unset($linea);

            $data['monto_total_gravado'] = $data['monto_total_gravado'] ?? $totalGravado;
            $data['monto_total_exento'] = $data['monto_total_exento'] ?? $totalExento;
            $data['monto_total_iva'] = $data['monto_total_iva'] ?? $totalIva;
            $data['monto_total'] = $data['monto_total'] ?? $montoTotal;
            $data['monto_pendiente'] = $data['monto_pendiente'] ?? $montoTotal;
        }

        // 2. Crear cabecera de Compra
        $compra = Compra::create($data);

        // 3. Procesar líneas de compra, serializados e inventario
        if (!empty($detalles) && is_array($detalles)) {
            foreach ($detalles as $lineaData) {
                $seriales = $lineaData['seriales'] ?? [];
                unset($lineaData['seriales']);

                $lineaData['id_compra'] = $compra->id_compra;
                $detalle = DetalleCompra::create($lineaData);

                // Obtener el item de inventario correspondiente
                $item = Inventario::find($detalle->id_inventario);
                if ($item) {
                    // Actualizar Inventario: Promedio Ponderado, Stock y Precio de Venta
                    self::actualizarInventarioTrasCompra($item, $detalle->cantidad, (float) $detalle->costo_unitario);

                    // Generar Equipos o Repuestos serializados según el tipo de inventario
                    self::generarSerializados($detalle, $item, $seriales);
                }
            }
        }

        DB::commit();

        return $compra->load('detalles');
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

        return $compra->load('detalles');
    }

    public static function delete($id)
    {
        $compra = Compra::find($id);
        if (!$compra) {
            return null;
        }

        DB::beginTransaction();

        // 1. Obtener detalles para revertir inventario, equipos y repuestos
        $detalles = DetalleCompra::where('id_compra', $id)->get();

        foreach ($detalles as $detalle) {
            // Eliminar equipos asociados a esta línea
            Equipo::where('id_detalle_compra', $detalle->id_detalle_compra)->delete();

            // Eliminar repuestos asociados a esta línea
            Repuesto::where('id_detalle_compra', $detalle->id_detalle_compra)->delete();

            // Revertir cantidades en inventario
            $item = Inventario::find($detalle->id_inventario);
            if ($item) {
                $nuevaCantidadTotal = max(0, (int) $item->cantidad_total - (int) $detalle->cantidad);
                $nuevaCantidadPropia = max(0, (int) $item->cantidad_propia - (int) $detalle->cantidad);

                $item->update([
                    'cantidad_total' => $nuevaCantidadTotal,
                    'cantidad_propia' => $nuevaCantidadPropia,
                ]);
            }

            $detalle->delete();
        }

        // 2. Eliminar cabecera de la compra
        $compra->delete();

        DB::commit();

        return $compra;
    }

    /**
     * Actualiza cantidades y costos promedio ponderado en Inventario.
     * Fórmula margen sobre ventas: PrecioVenta = CostoPromedio / (1 - (PorcentajeGanancia / 100))
     */
    private static function actualizarInventarioTrasCompra(Inventario $item, int $cantidadComprada, float $costoUnitario)
    {
        $stockActual = (int) $item->cantidad_total;
        $costoPromActual = (float) ($item->monto_compra_prom ?? $item->ultimo_monto_compra ?? $costoUnitario);

        // Nuevo stock
        $nuevoStockTotal = $stockActual + $cantidadComprada;
        $nuevoStockPropio = (int) $item->cantidad_propia + $cantidadComprada;

        // Promedio Ponderado: (StockAnterior * CostoPromAnterior + CantidadNueva * CostoNuevo) / NuevoStockTotal
        if ($nuevoStockTotal > 0) {
            $nuevoCostoProm = (($stockActual * $costoPromActual) + ($cantidadComprada * $costoUnitario)) / $nuevoStockTotal;
        } else {
            $nuevoCostoProm = $costoUnitario;
        }

        $minCompra = is_null($item->monto_compra_min) ? $costoUnitario : min((float) $item->monto_compra_min, $costoUnitario);
        $maxCompra = is_null($item->monto_compra_max) ? $costoUnitario : max((float) $item->monto_compra_max, $costoUnitario);

        // Cálculo del Precio de Venta Unitario basado en el margen sobre ventas
        $porcentajeGanancia = (float) ($item->porcentaje_ganancia ?? 0.00);
        $precioVentaUnitario = $item->monto_venta_unitario;

        if ($porcentajeGanancia > 0 && $porcentajeGanancia < 100) {
            // PrecioVenta = Costo / (1 - Ganancia/100)
            $precioVentaUnitario = round($nuevoCostoProm / (1 - ($porcentajeGanancia / 100)), 2);
        } elseif ($porcentajeGanancia == 0 && !empty($nuevoCostoProm)) {
            $precioVentaUnitario = round($nuevoCostoProm, 2);
        }

        $item->update([
            'cantidad_total' => $nuevoStockTotal,
            'cantidad_propia' => $nuevoStockPropio,
            'ultimo_monto_compra' => round($costoUnitario, 2),
            'monto_compra_prom' => round($nuevoCostoProm, 2),
            'monto_compra_min' => round($minCompra, 2),
            'monto_compra_max' => round($maxCompra, 2),
            'monto_venta_unitario' => $precioVentaUnitario,
        ]);
    }

    /**
     * Genera registros en Equipos o Repuestos según el tipo de inventario.
     */
    private static function generarSerializados(DetalleCompra $detalle, Inventario $item, array $seriales)
    {
        $tipo = $item->tipo instanceof InventarioTipo ? $item->tipo->value : (string) $item->tipo;
        $cantidad = (int) $detalle->cantidad;

        // Si es EQUIPO
        if ($tipo === 'equipo') {
            for ($i = 0; $i < $cantidad; $i++) {
                $serialData = $seriales[$i] ?? null;
                $numSerial = is_array($serialData) ? ($serialData['serial'] ?? null) : $serialData;
                $nombreEquipo = is_array($serialData) ? ($serialData['nombre'] ?? $item->nombre) : $item->nombre;

                if (empty($numSerial)) {
                    $numSerial = $item->sku . '-' . date('Y') . '-' . str_pad($detalle->id_detalle_compra . ($i + 1), 3, '0', STR_PAD_LEFT);
                }

                Equipo::create([
                    'id_inventario' => $item->id_inventario,
                    'id_detalle_compra' => $detalle->id_detalle_compra,
                    'serial' => $numSerial,
                    'nombre' => $nombreEquipo,
                    'is_deleted' => false,
                ]);
            }
        }
        // Si es COMPRESOR o VALVULA -> Se consideran Repuestos serializados
        elseif ($tipo === 'compresor' || $tipo === 'valvula') {
            for ($i = 0; $i < $cantidad; $i++) {
                $serialData = $seriales[$i] ?? null;
                $numSerial = is_array($serialData) ? ($serialData['serial'] ?? null) : $serialData;
                $nombreRepuesto = is_array($serialData) ? ($serialData['nombre'] ?? $item->nombre) : $item->nombre;

                if (empty($numSerial)) {
                    $numSerial = 'SN-' . $item->sku . '-' . str_pad($detalle->id_detalle_compra . ($i + 1), 3, '0', STR_PAD_LEFT);
                }

                Repuesto::create([
                    'id_inventario' => $item->id_inventario,
                    'id_detalle_compra' => $detalle->id_detalle_compra,
                    'serial' => $numSerial,
                    'nombre' => $nombreRepuesto,
                    'estado' => RepuestoEstado::NUEVO,
                    'propietario' => true,
                    'costo_adquisicion' => $detalle->costo_unitario,
                    'costo_reparacion_base' => 0.00,
                    'costo_total' => $detalle->costo_unitario,
                    'is_deleted' => false,
                ]);
            }
        }
        // Si tipo === 'insumo', solo se actualizó el stock en inventario y no se serializa.
    }
}
