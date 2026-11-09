<?php

namespace App\Services;

use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;

/**
 * Service ProveedorService
 * 
 * Gestiona el catalogo de empresas proveedoras y sus productos asociados.
 */
class ProveedorService
{
    /**
     * Retorna todos los proveedores registrados con sus items homologados.
     * Consulta SQL Raw:
     * SELECT * FROM proveedores;
     */
    public static function getAll()
    {
        return Proveedor::with(['inventarioProveedores'])->get();
    }

    /**
     * Obtiene un proveedor por su ID con sus items homologados.
     * Consulta SQL Raw:
     * SELECT * FROM proveedores WHERE id_proveedor = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Proveedor::with(['inventarioProveedores.inventario'])->find($id);
    }

    /**
     * Registra un nuevo proveedor.
     * Consulta SQL Raw:
     * INSERT INTO proveedores (nombre, rut, correo, num_tlf, direccion) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();

        $itemsData = $data['items'] ?? null;
        unset($data['items']);

        $proveedor = Proveedor::create($data);

        if (is_array($itemsData)) {
            self::sincronizarItems($proveedor->id_proveedor, $itemsData);
        }

        DB::commit();
        return $proveedor->load('inventarioProveedores.inventario');
    }

    /**
     * Actualiza la informacion fiscal o de contacto de un proveedor.
     * Consulta SQL Raw:
     * UPDATE proveedores SET nombre = ..., rut = ..., correo = ..., num_tlf = ..., direccion = ... WHERE id_proveedor = $id;
     */
    public static function update($id, $data)
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return null;
        }

        DB::beginTransaction();

        $itemsData = $data['items'] ?? null;
        unset($data['items']);

        $proveedor->update($data);

        if (is_array($itemsData)) {
            self::sincronizarItems($proveedor->id_proveedor, $itemsData);
        }

        DB::commit();
        return $proveedor->load('inventarioProveedores.inventario');
    }

    /**
     * Sincroniza las relaciones inventario_proveedores para un proveedor.
     */
    protected static function sincronizarItems($idProveedor, array $itemsData)
    {
        $inventarioIdsMantener = [];

        foreach ($itemsData as $iData) {
            $idInv = $iData['id_inventario'] ?? null;
            $codProv = trim($iData['codigo_proveedor'] ?? '');
            $monto = isset($iData['ultimo_monto_compra']) ? (float) $iData['ultimo_monto_compra'] : 0.00;

            if (!$idInv || empty($codProv)) {
                continue;
            }

            $inventarioIdsMantener[] = $idInv;

            $invProv = \App\Models\InventarioProveedor::where('id_proveedor', $idProveedor)
                ->where('id_inventario', $idInv)
                ->first();

            if ($invProv) {
                $invProv->update([
                    'codigo_proveedor' => $codProv,
                    'ultimo_monto_compra' => $monto,
                ]);
            } else {
                \App\Models\InventarioProveedor::create([
                    'id_proveedor' => $idProveedor,
                    'id_inventario' => $idInv,
                    'codigo_proveedor' => $codProv,
                    'ultimo_monto_compra' => $monto,
                ]);
            }
        }

        // Eliminar homologaciones que fueron removidas de la lista si se pasó un array definido
        \App\Models\InventarioProveedor::where('id_proveedor', $idProveedor)
            ->whereNotIn('id_inventario', $inventarioIdsMantener)
            ->delete();
    }

    /**
     * Elimina el registro de un proveedor.
     * Consulta SQL Raw:
     * DELETE FROM proveedores WHERE id_proveedor = $id;
     */
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