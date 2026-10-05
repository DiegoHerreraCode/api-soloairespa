<?php

namespace App\Http\Controllers;

use App\Services\TipoServicioTallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class TipoServicioTallerController
 *
 * Controlador RESTful encargado de gestionar las categorías o tipos de servicios ejecutables en el taller
 * (ejemplos: Mano de Obra Eléctrica, Mecánica de Compresor, Carga de Gas, Tornería).
 */
class TipoServicioTallerController extends Controller
{
    /**
     * Retorna todas las categorías de servicios de taller registradas.
     *
     * Lógica:
     * 1. Consulta la colección completa mediante TipoServicioTallerService::getAll().
     * 2. Retorna respuesta estándar JSON con HTTP 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "tipos_servicios_taller";
     *
     * @return JsonResponse Lista de tipos de servicios.
     */
    public function index(): JsonResponse
    {
        $tipos = TipoServicioTallerService::getAll();
        return $this->successResponse(
            $tipos,
            $tipos->isEmpty() ? 'No se encontraron tipos de servicios de taller' : 'Tipos de servicios obtenidos correctamente'
        );
    }

    /**
     * Registra una nueva categoría de servicio de taller.
     *
     * Lógica:
     * 1. Valida el nombre obligatorio y la descripción opcional.
     * 2. Llama a TipoServicioTallerService::create() para calcular el ID y guardar el registro.
     * 3. Responde con el tipo creado con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- INSERT INTO "tipos_servicios_taller" ("id_tipo_servicio_taller", "nombre", "descripcion", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        $tipo = TipoServicioTallerService::create($data);

        if (!$tipo) {
            return $this->errorResponse('Tipo de servicio no creado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de servicio creado correctamente', 201);
    }

    /**
     * Consulta los datos de un tipo de servicio según su ID.
     *
     * Lógica:
     * 1. Localiza el registro a través de TipoServicioTallerService::getOne($id).
     * 2. Retorna 404 si no existe, o 200 con el objeto si fue localizado.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "tipos_servicios_taller" WHERE "id_tipo_servicio_taller" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $tipo = TipoServicioTallerService::getOne($id);
        if (!$tipo) {
            return $this->errorResponse('Tipo de servicio no encontrado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de servicio obtenido correctamente');
    }

    /**
     * Actualiza el nombre o descripción de un tipo de servicio de taller.
     *
     * Lógica:
     * 1. Valida los campos 'nombre' y 'descripcion'.
     * 2. Comprueba que al menos uno de ellos esté presente en el payload.
     * 3. Invoca TipoServicioTallerService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "tipos_servicios_taller" WHERE "id_tipo_servicio_taller" = :id LIMIT 1;
     * -- UPDATE "tipos_servicios_taller" SET "nombre" = '...', "updated_at" = NOW() WHERE "id_tipo_servicio_taller" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'descripcion' => 'string|max:100',
        ]);

        // Validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('descripcion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $tipo = TipoServicioTallerService::update($id, $data);

        if (!$tipo) {
            return $this->errorResponse('Tipo de servicio no encontrado', 404);
        }

        return $this->successResponse($tipo, 'Tipo de servicio actualizado correctamente');
    }

    /**
     * Elimina un tipo de servicio de taller de la base de datos.
     *
     * Lógica:
     * 1. Ejecuta TipoServicioTallerService::delete($id).
     * 2. Si no se encuentra o falla la eliminación retorna error 404.
     * 3. Responde confirmando la eliminación exitosa.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "tipos_servicios_taller" WHERE "id_tipo_servicio_taller" = :id LIMIT 1;
     * -- DELETE FROM "tipos_servicios_taller" WHERE "id_tipo_servicio_taller" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $deleted = TipoServicioTallerService::delete($id);
        if (!$deleted) {
            return $this->errorResponse('Tipo de servicio no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse(null, 'Tipo de servicio eliminado correctamente');
    }
}