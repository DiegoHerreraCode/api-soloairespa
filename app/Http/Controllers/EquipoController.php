<?php

namespace App\Http\Controllers;

use App\Services\EquipoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class EquipoController
 *
 * Controlador RESTful encargado de gestionar las unidades físicas individuales de equipos
 * compradas o ingresadas al inventario, identificadas por serial único.
 */
class EquipoController extends Controller
{
    /**
     * Retorna el listado completo de equipos físicos registrados.
     *
     * Lógica:
     * 1. Consulta todos los registros mediante EquipoService::getAll().
     * 2. Devuelve respuesta JSON con status 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "equipos";
     *
     * @return JsonResponse Lista de equipos.
     */
    public function index(): JsonResponse
    {
        $equipos = EquipoService::getAll();
        return $this->successResponse(
            $equipos,
            $equipos->isEmpty() ? 'No se encontraron equipos' : 'Equipos obtenidos correctamente'
        );
    }

    /**
     * Registra un nuevo equipo con su número de serial y detalle de compra asociado.
     *
     * Lógica:
     * 1. Valida que id_inventario e id_detalle_compra existan, además de serial y nombre obligatorios.
     * 2. Llama a EquipoService::create($data) para asignar ID e insertar en la base de datos.
     * 3. Retorna el nuevo registro con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación de existencia:
     * -- SELECT count(*) FROM "inventario" WHERE "id_inventario" = :id LIMIT 1;
     * -- SELECT count(*) FROM "detalles_compras" WHERE "id_detalle_compra" = :id LIMIT 1;
     * -- Inserción de registro:
     * -- INSERT INTO "equipos" ("id_equipo", "id_inventario", "id_detalle_compra", "serial", "nombre", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_inventario' => 'required|integer|exists:inventario,id_inventario',
            'id_detalle_compra' => 'required|integer|exists:detalles_compras,id_detalle_compra',
            'serial' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'is_deleted' => 'nullable|boolean',
        ]);

        $data = $request->all();

        $equipo = EquipoService::create($data);
        if (!$equipo) {
            return $this->errorResponse('Equipo no creado', 404);
        }

        return $this->successResponse($equipo, 'Equipo creado correctamente', 201);
    }

    /**
     * Consulta y entrega la información de un equipo por su ID.
     *
     * Lógica:
     * 1. Busca el equipo mediante EquipoService::getOne($id).
     * 2. Si no existe retorna 404, de lo contrario devuelve el equipo con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "equipos" WHERE "id_equipo" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $equipo = EquipoService::getOne($id);
        if (!$equipo) {
            return $this->errorResponse('Equipo no encontrado', 404);
        }

        return $this->successResponse($equipo, 'Equipo obtenido correctamente');
    }

    /**
     * Actualiza la información de un equipo existente.
     *
     * Lógica:
     * 1. Valida los campos opcionales enviados.
     * 2. Comprueba que al menos un campo modificable haya sido provisto.
     * 3. Invoca EquipoService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "equipos" WHERE "id_equipo" = :id LIMIT 1;
     * -- UPDATE "equipos" SET "serial" = '...', "updated_at" = NOW() WHERE "id_equipo" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_inventario' => 'integer|exists:inventario,id_inventario',
            'id_detalle_compra' => 'integer|exists:detalles_compras,id_detalle_compra',
            'serial' => 'string|max:100',
            'nombre' => 'string|max:100',
            'is_deleted' => 'boolean',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_inventario') &&
            !$request->has('id_detalle_compra') &&
            !$request->has('serial') &&
            !$request->has('nombre') &&
            !$request->has('is_deleted')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $equipo = EquipoService::update($id, $data);
        if (!$equipo) {
            return $this->errorResponse('Equipo no encontrado', 404);
        }

        return $this->successResponse($equipo, 'Equipo actualizado correctamente');
    }

    /**
     * Elimina un equipo de la base de datos.
     *
     * Lógica:
     * 1. Llama a EquipoService::delete($id).
     * 2. Si no existe o no pudo eliminarse retorna error 404.
     * 3. Retorna el equipo eliminado con HTTP 200.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "equipos" WHERE "id_equipo" = :id LIMIT 1;
     * -- DELETE FROM "equipos" WHERE "id_equipo" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $equipo = EquipoService::delete($id);
        if (!$equipo) {
            return $this->errorResponse('Equipo no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($equipo, 'Equipo eliminado correctamente');
    }
}