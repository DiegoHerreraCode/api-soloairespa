<?php

namespace App\Services;

use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;

/**
 * Service ProveedorService
 * 
 * Gestiona el catálogo de empresas proveedoras.
 */
class ProveedorService
{
    /**
     * Retorna todos los proveedores registrados.
     * Consulta SQL Raw:
     * SELECT * FROM proveedores;
     */
    public static function getAll()
    {
        return Proveedor::get();
    }

    /**
     * Obtiene un proveedor por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM proveedores WHERE id_proveedor = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Proveedor::find($id);
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
     * Actualiza la información fiscal o de contacto de un proveedor.
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
