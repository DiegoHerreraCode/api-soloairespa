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
        $proveedor = Proveedor::create($data);
        DB::commit();
        return $proveedor;
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
        $proveedor->update($data);
        DB::commit();
        return $proveedor;
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