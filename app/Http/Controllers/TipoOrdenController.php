<?php

namespace App\Http\Controllers;

use App\Services\TipoOrdenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class TipoOrdenController
 *
 * Controlador RESTful encargado de gestionar los tipos de órdenes del taller
 * (por ejemplo: Venta directa, Reparación, Recambio).
 * Delega las operaciones sobre la tabla tipos_ordenes a TipoOrdenService.
 */
class TipoOrdenController extends Controller
{
    /**
     * Retorna el listado completo de tipos de órdenes disponibles.
     *
     * Lógica:
     * 1. Obtiene la lista completa llamando a TipoOrdenService::getAll().
     * 2. Devuelve respuesta JSON con status 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "tipos_ordenes";
     *
     * @return JsonResponse Lista de tipos de órdenes.
     */
    public function index(): JsonResponse
    {
        $tipos = TipoOrdenService::getAll();
        return $this->successResponse(
            $tipos,
            $tipos->isEmpty() ? 'No se encontraron tipos de órdenes' : 'Tipos de órdenes obtenidos correctamente'
        );
    }

    /**
     * Registra un nuevo tipo de orden en el sistema.
     *
     * Lógica:
     * 1. Valida el nombre (obligatorio hasta 50 chars) y la descripción (opcional hasta 100 chars).
     * 2. Invoca TipoOrdenService::create($data) para asignar ID e insertar el registro.
     * 3. Retorna el nuevo tipo de orden creado con HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- INSERT INTO "tipos_ordenes" ("id_tipo_orden", "nombre", "descripcion", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:50',
            'descripcion' => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        $tipo = TipoOrdenService::create($data);
        if (!$tipo) {
            return $this->errorResponse('Tipo de orden no creado', 404);
        }
        return $this->successResponse($tipo, 'Tipo de orden creado correctamente', 201);
    }

    /**
     * Retorna los datos de un tipo de orden específico según su identificador.
     *
     * Lógica:
     * 1. Busca el tipo de orden vía TipoOrdenService::getOne($id).
     * 2. Si no existe retorna 404; si existe, entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "tipos_ordenes" WHERE "id_tipo_orden" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $tipo = TipoOrdenService::getOne($id);
        if (!$tipo) {
            return $this->errorResponse('Tipo de orden no encontrado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de orden obtenido correctamente');
    }

    /**
     * Actualiza el nombre o descripción de un tipo de orden existente.
     *
     * Lógica:
     * 1. Valida los datos recibidos.
     * 2. Comprueba que al menos uno de los campos ('nombre', 'descripcion') esté presente.
     * 3. Invoca TipoOrdenService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "tipos_ordenes" WHERE "id_tipo_orden" = :id LIMIT 1;
     * -- UPDATE "tipos_ordenes" SET "nombre" = '...', "updated_at" = NOW() WHERE "id_tipo_orden" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:50',
            'descripcion' => 'nullable|string|max:100',
        ]);

        // Validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('descripcion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $tipo = TipoOrdenService::update($id, $data);
        if (!$tipo) {
            return $this->errorResponse('Tipo de orden no encontrado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de orden actualizado correctamente');
    }

    /**
     * Elimina un tipo de orden de la base de datos.
     *
     * Lógica:
     * 1. Llama a TipoOrdenService::delete($id).
     * 2. Si no existe o no pudo eliminarse retorna 404.
     * 3. Retorna el objeto eliminado con mensaje exitoso.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "tipos_ordenes" WHERE "id_tipo_orden" = :id LIMIT 1;
     * -- DELETE FROM "tipos_ordenes" WHERE "id_tipo_orden" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $tipo = TipoOrdenService::delete($id);
        if (!$tipo) {
            return $this->errorResponse('Tipo de orden no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($tipo, 'Tipo de orden eliminado correctamente');
    }
}