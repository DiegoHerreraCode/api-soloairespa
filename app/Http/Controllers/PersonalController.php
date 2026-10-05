<?php

namespace App\Http\Controllers;

use App\Services\PersonalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class PersonalController
 *
 * Controlador RESTful encargado de gestionar los técnicos y trabajadores operativos del taller.
 * Coordina altas, bajas lógicas, consultas y actualizaciones de disponibilidad mediante PersonalService.
 */
class PersonalController extends Controller
{
    /**
     * Retorna el listado completo de personal registrado.
     *
     * Lógica:
     * 1. Consulta el personal registrado a través de PersonalService::getAll().
     * 2. Retorna respuesta JSON con la colección de trabajadores.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "personal";
     *
     * @return JsonResponse Lista de trabajadores.
     */
    public function index(): JsonResponse
    {
        $personal = PersonalService::getAll();
        return $this->successResponse(
            $personal,
            $personal->isEmpty() ? 'No se encontró personal' : 'Personal obtenido correctamente'
        );
    }

    /**
     * Registra un nuevo técnico o empleado en el sistema.
     *
     * Lógica:
     * 1. Valida obligatoriedad y unicidad del RUT, correo electrónico y teléfono.
     * 2. Invoca PersonalService::create() para generar el ID y guardar en base de datos.
     * 3. Retorna el registro creado con HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación de unicidad:
     * -- SELECT count(*) FROM "personal" WHERE "rut" = '...' LIMIT 1;
     * -- SELECT count(*) FROM "personal" WHERE "correo" = '...' LIMIT 1;
     * -- Inserción de registro:
     * -- INSERT INTO "personal" ("id_personal", "nombre", "rut", "correo", "num_tlf", "direccion", "disponible", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'rut' => 'required|string|max:100|unique:personal,rut',
            'correo' => 'required|email|max:100|unique:personal,correo',
            'num_tlf' => 'required|string|max:50|unique:personal,num_tlf',
            'direccion' => 'required|string|max:100',
            'disponible' => 'nullable|boolean',
        ]);

        $personal = PersonalService::create($request->all());

        if (!$personal) {
            return $this->errorResponse('Personal no creado', 404);
        }
        return $this->successResponse($personal, 'Personal creado correctamente', 201);
    }

    /**
     * Consulta y devuelve la información de un empleado por su ID.
     *
     * Lógica:
     * 1. Busca el trabajador vía PersonalService::getOne($id).
     * 2. Si no se localiza devuelve error 404; si existe, entrega el recurso con HTTP 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "personal" WHERE "id_personal" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $personal = PersonalService::getOne($id);
        if (!$personal) {
            return $this->errorResponse('Personal no encontrado', 404);
        }

        return $this->successResponse($personal, 'Personal obtenido correctamente');
    }

    /**
     * Actualiza los datos de un empleado (incluyendo estado de disponibilidad o baja lógica).
     *
     * Lógica:
     * 1. Valida reglas únicas omitiendo el ID actual.
     * 2. Verifica que se proporcione al menos un campo modificable.
     * 3. Invoca PersonalService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT count(*) FROM "personal" WHERE "correo" = '...' AND "id_personal" != :id LIMIT 1;
     * -- SELECT * FROM "personal" WHERE "id_personal" = :id LIMIT 1;
     * -- UPDATE "personal" SET "disponible" = false, "updated_at" = NOW() WHERE "id_personal" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'rut' => "string|max:100|unique:personal,rut,{$id},id_personal",
            'correo' => "email|max:100|unique:personal,correo,{$id},id_personal",
            'num_tlf' => "string|max:50|unique:personal,num_tlf,{$id},id_personal",
            'direccion' => 'string|max:100',
            'disponible' => 'boolean',
            'is_deleted' => 'boolean',
        ]);

        // Validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('rut') && !$request->has('correo') && !$request->has('num_tlf') && !$request->has('direccion') && !$request->has('disponible') && !$request->has('is_deleted')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $personal = PersonalService::update($id, $request->all());
        if (!$personal) {
            return $this->errorResponse('Personal no encontrado', 404);
        }

        return $this->successResponse($personal, 'Personal actualizado correctamente');
    }

    /**
     * Elimina o marca como eliminado a un empleado.
     *
     * Lógica:
     * 1. Invoca PersonalService::delete($id).
     * 2. Si no existe o no pudo eliminarse retorna error 404.
     * 3. Retorna el modelo eliminado con HTTP 200.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "personal" WHERE "id_personal" = :id LIMIT 1;
     * -- DELETE FROM "personal" WHERE "id_personal" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $personal = PersonalService::delete($id);
        if (!$personal) {
            return $this->errorResponse('Personal no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($personal, 'Personal eliminado correctamente');
    }
}