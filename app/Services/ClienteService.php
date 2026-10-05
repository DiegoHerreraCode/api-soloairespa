<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Service ClienteService
 * 
 * Gestiona el catálogo y la persistencia de clientes del sistema.
 */
class ClienteService
{
    /**
     * Retorna todos los clientes registrados.
     * Consulta SQL Raw:
     * SELECT * FROM clientes;
     */
    public static function getAll()
    {
        return Cliente::get();
    }

    /**
     * Obtiene un cliente específico por su ID.
     * Consulta SQL Raw:
     * SELECT * FROM clientes WHERE id_cliente = $id LIMIT 1;
     */
    public static function getOne($id)
    {
        return Cliente::find($id);
    }

    /**
     * Crea un nuevo cliente en base de datos.
     * Consulta SQL Raw:
     * INSERT INTO clientes (nombre, rut, correo, num_tlf, direccion) VALUES (...);
     */
    public static function create($data)
    {
        DB::beginTransaction();
        $cliente = Cliente::create($data);
        DB::commit();
        return $cliente;
    }

    /**
     * Actualiza los datos de un cliente existente.
     * Consulta SQL Raw:
     * UPDATE clientes SET nombre = ..., rut = ..., correo = ..., num_tlf = ..., direccion = ... WHERE id_cliente = $id;
     */
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

    /**
     * Elimina el registro de un cliente.
     * Consulta SQL Raw:
     * DELETE FROM clientes WHERE id_cliente = $id;
     */
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
