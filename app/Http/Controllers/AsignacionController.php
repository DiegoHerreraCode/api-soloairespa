<?php

namespace App\Http\Controllers;

use App\Services\AsignacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class AsignacionController
 *
 * Controlador RESTful encargado de gestionar las asignaciones de personal técnico
 * a los servicios específicos aplicados durante las reparaciones de taller.
 */
class AsignacionController extends Controller
{
    /**
     * Retorna todas las asignaciones de técnicos a servicios registradas.
     *
     * Lógica:
     * 1. Consulta la lista total mediante AsignacionService::getAll().
     * 2. Devuelve respuesta JSON con HTTP 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "asignaciones";
     *
     * @return JsonResponse Lista de asignaciones.
     */
    public function index(): JsonResponse
    {
        $asignaciones = AsignacionService::getAll();
        return $this->successResponse(
            $asignaciones,
            $asignaciones->isEmpty() ? 'No se encontraron asignaciones' : 'Asignaciones obtenidas correctamente'
        );
    }

    /**
     * Asigna un miembro del personal técnico a un servicio de taller de una reparación.
     *
     * Lógica:
     * 1. Valida que id_reparacion_servicio_taller e id_personal existan en sus respectivas tablas.
     * 2. Invoca AsignacionService::create($data), el cual asigna ID, crea el registro y actualiza
     *    la disponibilidad del técnico a no disponible (disponible = false).
     * 3. Retorna la nueva asignación con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- INSERT INTO "asignaciones" ("id_asignacion", "id_reparacion_servicio_taller", "id_personal", ...) VALUES (...);
     * -- UPDATE "personal" SET "disponible" = false WHERE "id_personal" = :id_personal;
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_reparacion_servicio_taller' => 'required|integer|exists:reparaciones_servicios_taller,id_reparacion_servicio_taller',
            'id_personal'                  => 'required|integer|exists:personal,id_personal',
        ]);

        $data = $request->all();

        $asignacion = AsignacionService::create($data);
        if (!$asignacion) {
            return $this->errorResponse('Asignación no creada', 404);
        }

        return $this->successResponse($asignacion, 'Personal asignado al servicio correctamente', 201);
    }

    /**
     * Consulta y devuelve la información de una asignación por su ID.
     *
     * Lógica:
     * 1. Busca la asignación mediante AsignacionService::getOne($id).
     * 2. Si no existe retorna 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "asignaciones" WHERE "id_asignacion" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $asignacion = AsignacionService::getOne($id);
        if (!$asignacion) {
            return $this->errorResponse('Asignación no encontrada', 404);
        }

        return $this->successResponse($asignacion, 'Asignación obtenida correctamente');
    }

    /**
     * Actualiza los datos o técnico responsable de una asignación.
     *
     * Lógica:
     * 1. Valida las claves foráneas recibidas.
     * 2. Verifica que al menos un campo haya sido provisto.
     * 3. Invoca AsignacionService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "asignaciones" WHERE "id_asignacion" = :id LIMIT 1;
     * -- UPDATE "asignaciones" SET "id_personal" = :nuevo_id WHERE "id_asignacion" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_reparacion_servicio_taller' => 'integer|exists:reparaciones_servicios_taller,id_reparacion_servicio_taller',
            'id_personal'                  => 'integer|exists:personal,id_personal',
        ]);

        if (!$request->has('id_reparacion_servicio_taller') && !$request->has('id_personal')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $asignacion = AsignacionService::update($id, $data);
        if (!$asignacion) {
            return $this->errorResponse('Asignación no encontrada', 404);
        }

        return $this->successResponse($asignacion, 'Asignación actualizada correctamente');
    }

    /**
     * Elimina una asignación liberando la disponibilidad del técnico asignado.
     *
     * Lógica:
     * 1. Llama a AsignacionService::delete($id), que elimina la asignación y restituye
     *    la disponibilidad del personal técnico si no tiene otras asignaciones activas.
     * 2. Devuelve mensaje exitoso con código 200.
     *
     * Consultas SQL ejecutadas internamente:
     * -- DELETE FROM "asignaciones" WHERE "id_asignacion" = :id;
     * -- UPDATE "personal" SET "disponible" = true WHERE "id_personal" = :id_personal;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $asignacion = AsignacionService::delete($id);
        if (!$asignacion) {
            return $this->errorResponse('Asignación no encontrada o no se pudo eliminar', 404);
        }

        return $this->successResponse(null, 'Asignación eliminada exitosamente');
    }
}