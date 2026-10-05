<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

/**
 * Class AdminController
 *
 * Controlador RESTful encargado de gestionar las operaciones CRUD sobre los administradores del sistema.
 * Valida los datos entrantes en las peticiones HTTP y delega la lógica de negocio y persistencia a AdminService.
 */
class AdminController extends Controller
{
    /**
     * Obtiene y retorna el listado completo de administradores registrados.
     *
     * Lógica:
     * 1. Consulta la totalidad de administradores delegando en AdminService::getAll().
     * 2. Retorna una respuesta JSON estandarizada con código 200 y mensaje dinámico según si existen registros.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "admins";
     *
     * @return JsonResponse Lista de administradores en formato JSON.
     */
    public function index(): JsonResponse
    {
        $admins = AdminService::getAll();
        return $this->successResponse(
            $admins,
            $admins->isEmpty() ? 'No se encontraron admins' : 'Admins obtenidos correctamente'
        );
    }

    /**
     * Valida y almacena un nuevo administrador en el sistema.
     *
     * Lógica:
     * 1. Valida obligatoriedad y unicidad del RUT y número telefónico para evitar duplicados en la base de datos.
     * 2. Envía la información validada a AdminService::create() para calcular el nuevo ID e insertar el registro.
     * 3. Si la inserción falla, retorna un error 404/500; de lo contrario, responde con el modelo creado (200 OK).
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación de unicidad:
     * -- SELECT count(*) FROM "admins" WHERE "rut" = '...' LIMIT 1;
     * -- SELECT count(*) FROM "admins" WHERE "num_tlf" = '...' LIMIT 1;
     * -- Inserción de registro:
     * -- INSERT INTO "admins" ("id_admin", "nombre", "rut", "num_tlf", "direccion", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request Petición HTTP con nombre, rut, num_tlf y direccion.
     * @return JsonResponse Registro del nuevo administrador creado.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'rut' => 'required|string|max:11|unique:admins,rut',
            'num_tlf' => 'required|string|max:11|unique:admins,num_tlf',
            'direccion' => 'required|string|max:100',
        ]);

        $data = $request->all();

        $admin = AdminService::create($data);
        if (!$admin) {
            return $this->errorResponse('Admin no creado', 404);
        }

        return $this->successResponse($admin, 'Admin creado correctamente');
    }

    /**
     * Consulta y retorna los datos detallados de un administrador específico mediante su ID.
     *
     * Lógica:
     * 1. Busca el registro del administrador mediante AdminService::getOne($id).
     * 2. Si no existe, retorna una respuesta de error 404 ('Admin no encontrado').
     * 3. Si existe, retorna los datos del administrador en formato JSON.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "admins" WHERE "id_admin" = :id LIMIT 1;
     *
     * @param int|string $id Identificador único del administrador.
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $admin = AdminService::getOne($id);
        if (!$admin) {
            return $this->errorResponse('Admin no encontrado', 404);
        }
        return $this->successResponse($admin, 'Admin obtenido correctamente');
    }

    /**
     * Valida y actualiza los datos de un administrador existente.
     *
     * Lógica:
     * 1. Valida los campos recibidos permitiendo omitir el ID actual en reglas unique (RUT, teléfono).
     * 2. Comprueba que al menos un campo modificable haya sido enviado en el payload; caso contrario retorna error 400.
     * 3. Invoca AdminService::update($id, $data) para actualizar en base de datos.
     * 4. Si el registro no fue encontrado retorna error 404, de lo contrario retorna el objeto actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT count(*) FROM "admins" WHERE "rut" = '...' AND "id_admin" != :id LIMIT 1;
     * -- SELECT * FROM "admins" WHERE "id_admin" = :id LIMIT 1;
     * -- UPDATE "admins" SET "nombre" = '...', "updated_at" = NOW() WHERE "id_admin" = :id;
     *
     * @param Request $request Datos parciales o completos a actualizar.
     * @param int|string $id Identificador del administrador a modificar.
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'rut' => "string|max:100|unique:admins,rut,{$id},id_admin",
            'num_tlf' => "string|max:50|unique:admins,num_tlf,{$id},id_admin",
            'direccion' => 'string|max:100',
        ]);

        // Validar que al menos un campo sea modificado en el cuerpo de la petición
        if (!$request->has('nombre') && !$request->has('rut') && !$request->has('num_tlf') && !$request->has('direccion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $admin = AdminService::update($id, $data);
        if (!$admin) {
            return $this->errorResponse('Admin no encontrado', 404);
        }

        return $this->successResponse($admin, 'Admin actualizado correctamente');
    }

    /**
     * Elimina un administrador existente según su identificador único.
     *
     * Lógica:
     * 1. Invoca AdminService::delete($id) que busca el modelo y ejecuta delete().
     * 2. Si el registro no existe o no pudo ser eliminado retorna error 404.
     * 3. Si se elimina con éxito, retorna el registro eliminado con mensaje de confirmación.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "admins" WHERE "id_admin" = :id LIMIT 1;
     * -- DELETE FROM "admins" WHERE "id_admin" = :id;
     *
     * @param int|string $id Identificador del administrador.
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $admin = AdminService::delete($id);
        if (!$admin) {
            return $this->errorResponse('Admin no encontrado', 404);
        }
        return $this->successResponse($admin, 'Admin eliminado correctamente');
    }
}