<?php

namespace App\Http\Controllers;

use App\Services\ModeloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ModeloController
 *
 * Controlador RESTful encargado de gestionar los modelos vinculados a cada marca comercial.
 * Valida la existencia de la marca asociada y delega la lógica de negocio a ModeloService.
 */
class ModeloController extends Controller
{
    /**
     * Retorna todos los modelos registrados en el catálogo.
     *
     * Lógica:
     * 1. Obtiene la lista completa de modelos vía ModeloService::getAll().
     * 2. Retorna respuesta estándar JSON con código 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "modelos";
     *
     * @return JsonResponse Lista de modelos.
     */
    public function index(): JsonResponse
    {
        $modelos = ModeloService::getAll();
        return $this->successResponse(
            $modelos,
            $modelos->isEmpty() ? 'No se encontraron modelos' : 'Modelos obtenidos correctamente'
        );
    }

    /**
     * Registra un nuevo modelo vinculado a una marca existente.
     *
     * Lógica:
     * 1. Valida que id_marca exista en la tabla marcas, y que nombre y descripción no superen 100 caracteres.
     * 2. Envía los datos a ModeloService::create() para calcular ID e insertar el registro.
     * 3. Retorna el nuevo modelo con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación de clave foránea:
     * -- SELECT count(*) FROM "marcas" WHERE "id_marca" = :id_marca LIMIT 1;
     * -- Inserción de registro:
     * -- INSERT INTO "modelos" ("id_modelo", "id_marca", "nombre", "descripcion", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_marca' => 'required|integer|exists:marcas,id_marca',
            'nombre' => 'required|string|max:100',
            'descripcion' => 'required|string|max:100',
        ]);

        $data = $request->all();

        $modelo = ModeloService::create($data);
        if (!$modelo) {
            return $this->errorResponse('Modelo no creado', 404);
        }

        return $this->successResponse($modelo, 'Modelo creado correctamente', 201);
    }

    /**
     * Consulta los datos de un modelo específico según su ID.
     *
     * Lógica:
     * 1. Llama a ModeloService::getOne($id).
     * 2. Si no se encuentra retorna 404, de lo contrario devuelve el modelo con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "modelos" WHERE "id_modelo" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $modelo = ModeloService::getOne($id);
        if (!$modelo) {
            return $this->errorResponse('Modelo no encontrado', 404);
        }

        return $this->successResponse($modelo, 'Modelo obtenido correctamente');
    }

    /**
     * Actualiza los datos de un modelo existente.
     *
     * Lógica:
     * 1. Valida id_marca, nombre y descripción.
     * 2. Comprueba que al menos uno de los tres campos haya sido proporcionado.
     * 3. Invoca ModeloService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "modelos" WHERE "id_modelo" = :id LIMIT 1;
     * -- UPDATE "modelos" SET "nombre" = '...', "updated_at" = NOW() WHERE "id_modelo" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_marca' => 'integer|exists:marcas,id_marca',
            'nombre' => 'string|max:100',
            'descripcion' => 'string|max:100',
        ]);

        // Validar que al menos un campo sea modificado
        if (!$request->has('id_marca') && !$request->has('nombre') && !$request->has('descripcion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $modelo = ModeloService::update($id, $data);
        if (!$modelo) {
            return $this->errorResponse('Modelo no encontrado', 404);
        }

        return $this->successResponse($modelo, 'Modelo actualizado correctamente');
    }

    /**
     * Elimina un modelo de la base de datos.
     *
     * Lógica:
     * 1. Ejecuta ModeloService::delete($id).
     * 2. Si no se encuentra o falla la supresión retorna 404.
     * 3. Retorna el modelo eliminado con mensaje exitoso.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "modelos" WHERE "id_modelo" = :id LIMIT 1;
     * -- DELETE FROM "modelos" WHERE "id_modelo" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $modelo = ModeloService::delete($id);
        if (!$modelo) {
            return $this->errorResponse('Modelo no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($modelo, 'Modelo eliminado correctamente');
    }
}