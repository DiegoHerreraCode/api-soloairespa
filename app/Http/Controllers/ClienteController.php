<?php

namespace App\Http\Controllers;

use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ClienteController
 *
 * Controlador RESTful encargado de gestionar los clientes comerciales del taller.
 * Expone los endpoints para listar, registrar, consultar detalle, actualizar y eliminar clientes.
 */
class ClienteController extends Controller
{
    /**
     * Lista todos los clientes registrados en la base de datos.
     *
     * Lógica:
     * 1. Consulta la colección de clientes mediante ClienteService::getAll().
     * 2. Retorna respuesta JSON exitosa indicando si hay o no registros disponibles.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "clientes";
     *
     * @return JsonResponse Lista de clientes.
     */
    public function index(): JsonResponse
    {
        $clientes = ClienteService::getAll();
        return $this->successResponse(
            $clientes,
            $clientes->isEmpty() ? 'No se encontraron clientes' : 'Clientes obtenidos correctamente'
        );
    }

    /**
     * Registra un nuevo cliente con sus datos de contacto y facturación.
     *
     * Lógica:
     * 1. Valida obligatoriedad y formato de RUT, correo, teléfono y dirección, exigiendo unicidad.
     * 2. Envía la data a ClienteService::create() para asignar el ID autoincremental e insertar en la tabla.
     * 3. Retorna el cliente creado con código HTTP 201 (Created).
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación de unicidad:
     * -- SELECT count(*) FROM "clientes" WHERE "rut" = '...' LIMIT 1;
     * -- SELECT count(*) FROM "clientes" WHERE "correo" = '...' LIMIT 1;
     * -- Inserción en la base de datos:
     * -- INSERT INTO "clientes" ("id_cliente", "nombre", "rut", "correo", "num_tlf", "direccion", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'rut' => 'required|string|max:100|unique:clientes,rut',
            'correo' => 'required|email|max:100|unique:clientes,correo',
            'num_tlf' => 'required|string|max:50|unique:clientes,num_tlf',
            'direccion' => 'required|string|max:100',
        ]);

        $cliente = ClienteService::create($request->all());

        if (!$cliente) {
            return $this->errorResponse('Cliente no creado', 404);
        }

        return $this->successResponse($cliente, 'Cliente creado correctamente', 201);
    }

    /**
     * Retorna la información de un cliente específico por su ID.
     *
     * Lógica:
     * 1. Busca el cliente mediante ClienteService::getOne($id).
     * 2. Si no existe, responde con error HTTP 404; si existe, entrega el recurso con HTTP 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "clientes" WHERE "id_cliente" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $cliente = ClienteService::getOne($id);
        if (!$cliente) {
            return $this->errorResponse('Cliente no encontrado', 404);
        }

        return $this->successResponse($cliente, 'Cliente obtenido correctamente');
    }

    /**
     * Actualiza la información de un cliente existente.
     *
     * Lógica:
     * 1. Valida los campos enviados ignorando el ID del cliente en reglas de unicidad.
     * 2. Verifica que al menos un campo haya sido provisto para actualizar.
     * 3. Ejecuta ClienteService::update($id, $data) y retorna el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT count(*) FROM "clientes" WHERE "correo" = '...' AND "id_cliente" != :id LIMIT 1;
     * -- SELECT * FROM "clientes" WHERE "id_cliente" = :id LIMIT 1;
     * -- UPDATE "clientes" SET "nombre" = '...', "updated_at" = NOW() WHERE "id_cliente" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'rut' => "string|max:100|unique:clientes,rut,{$id},id_cliente",
            'correo' => "email|max:100|unique:clientes,correo,{$id},id_cliente",
            'num_tlf' => "string|max:50|unique:clientes,num_tlf,{$id},id_cliente",
            'direccion' => 'string|max:100',
        ]);

        // Validar que al menos un campo sea provisto para actualizar
        if (!$request->has('nombre') && !$request->has('rut') && !$request->has('correo') && !$request->has('num_tlf') && !$request->has('direccion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $cliente = ClienteService::update($id, $data);
        if (!$cliente) {
            return $this->errorResponse('Cliente no encontrado', 404);
        }

        return $this->successResponse($cliente, 'Cliente actualizado correctamente');
    }

    /**
     * Elimina un cliente de la base de datos si no tiene restricciones de integridad referencial.
     *
     * Lógica:
     * 1. Llama a ClienteService::delete($id).
     * 2. Si no se localiza el cliente o la eliminación falla, devuelve error 404.
     * 3. Si se elimina con éxito, devuelve el cliente eliminado.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "clientes" WHERE "id_cliente" = :id LIMIT 1;
     * -- DELETE FROM "clientes" WHERE "id_cliente" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $cliente = ClienteService::delete($id);
        if (!$cliente) {
            return $this->errorResponse('Cliente no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($cliente, 'Cliente eliminado correctamente');
    }
}