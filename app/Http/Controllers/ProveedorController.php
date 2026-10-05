<?php

namespace App\Http\Controllers;

use App\Services\ProveedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ProveedorController
 *
 * Controlador RESTful encargado de gestionar los proveedores de compras de repuestos e insumos.
 * Valida los datos tributarios (RUT) y de contacto para canalizar las operaciones hacia ProveedorService.
 */
class ProveedorController extends Controller
{
    /**
     * Lista todos los proveedores registrados en el sistema.
     *
     * Lógica:
     * 1. Invoca ProveedorService::getAll() para recuperar la colección completa de proveedores.
     * 2. Retorna respuesta JSON con HTTP 200 y mensaje correspondiente.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "proveedores";
     *
     * @return JsonResponse Lista de proveedores.
     */
    public function index(): JsonResponse
    {
        $proveedores = ProveedorService::getAll();
        return $this->successResponse(
            $proveedores,
            $proveedores->isEmpty() ? 'No se encontraron proveedores' : 'Proveedores obtenidos correctamente'
        );
    }

    /**
     * Registra un nuevo proveedor en la base de datos.
     *
     * Lógica:
     * 1. Valida obligatoriedad y formato único de RUT, correo electrónico y teléfono.
     * 2. Envía la carga útil a ProveedorService::create() para asignar el ID y guardar el registro.
     * 3. Devuelve el proveedor creado con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación de unicidad:
     * -- SELECT count(*) FROM "proveedores" WHERE "rut" = '...' LIMIT 1;
     * -- Inserción de registro:
     * -- INSERT INTO "proveedores" ("id_proveedor", "nombre", "rut", "correo", "num_tlf", "direccion", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'rut' => 'required|string|max:100|unique:proveedores,rut',
            'correo' => 'required|email|max:100|unique:proveedores,correo',
            'num_tlf' => 'required|string|max:100|unique:proveedores,num_tlf',
            'direccion' => 'required|string|max:100',
        ]);

        $proveedor = ProveedorService::create($request->all());

        if (!$proveedor) {
            return $this->errorResponse('Proveedor no creado', 404);
        }

        return $this->successResponse($proveedor, 'Proveedor creado correctamente', 201);
    }

    /**
     * Consulta y entrega la información de un proveedor por su ID.
     *
     * Lógica:
     * 1. Busca el proveedor mediante ProveedorService::getOne($id).
     * 2. Si no existe responde con 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "proveedores" WHERE "id_proveedor" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $proveedor = ProveedorService::getOne($id);
        if (!$proveedor) {
            return $this->errorResponse('Proveedor no encontrado', 404);
        }

        return $this->successResponse($proveedor, 'Proveedor obtenido correctamente');
    }

    /**
     * Actualiza los datos de contacto o razón social de un proveedor.
     *
     * Lógica:
     * 1. Valida reglas únicas omitiendo el ID del proveedor actual.
     * 2. Verifica que al menos un campo modificable haya sido enviado.
     * 3. Ejecuta ProveedorService::update($id, $data) y retorna el registro modificado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "proveedores" WHERE "id_proveedor" = :id LIMIT 1;
     * -- UPDATE "proveedores" SET "nombre" = '...', "updated_at" = NOW() WHERE "id_proveedor" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'rut' => "string|max:100|unique:proveedores,rut,{$id},id_proveedor",
            'correo' => "email|max:100|unique:proveedores,correo,{$id},id_proveedor",
            'num_tlf' => "string|max:100|unique:proveedores,num_tlf,{$id},id_proveedor",
            'direccion' => 'string|max:100',
        ]);

        // Validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('rut') && !$request->has('correo') && !$request->has('num_tlf') && !$request->has('direccion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $proveedor = ProveedorService::update($id, $request->all());
        if (!$proveedor) {
            return $this->errorResponse('Proveedor no encontrado', 404);
        }

        return $this->successResponse($proveedor, 'Proveedor actualizado correctamente');
    }

    /**
     * Elimina un proveedor del sistema.
     *
     * Lógica:
     * 1. Invoca ProveedorService::delete($id).
     * 2. Si no se encuentra o falla la eliminación retorna error 404.
     * 3. Retorna el proveedor eliminado con código 200.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "proveedores" WHERE "id_proveedor" = :id LIMIT 1;
     * -- DELETE FROM "proveedores" WHERE "id_proveedor" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $proveedor = ProveedorService::delete($id);
        if (!$proveedor) {
            return $this->errorResponse('Proveedor no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($proveedor, 'Proveedor eliminado correctamente');
    }
}