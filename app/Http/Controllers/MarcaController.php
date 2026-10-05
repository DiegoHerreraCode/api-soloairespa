<?php

namespace App\Http\Controllers;

use App\Services\MarcaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class MarcaController
 *
 * Controlador RESTful encargado de gestionar las marcas de vehículos y repuestos atendidos en el taller.
 * Expone las operaciones CRUD comunicándose directamente con MarcaService.
 */
class MarcaController extends Controller
{
    /**
     * Retorna todas las marcas registradas en el sistema.
     *
     * Lógica:
     * 1. Consulta la lista total de marcas mediante MarcaService::getAll().
     * 2. Devuelve respuesta JSON con status 200 y mensaje representativo.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "marcas";
     *
     * @return JsonResponse Lista de marcas.
     */
    public function index(): JsonResponse
    {
        $marcas = MarcaService::getAll();
        return $this->successResponse(
            $marcas,
            $marcas->isEmpty() ? 'No se encontraron marcas' : 'Marcas obtenidas correctamente'
        );
    }

    /**
     * Registra una nueva marca.
     *
     * Lógica:
     * 1. Valida que el nombre de la marca sea una cadena obligatoria de hasta 100 caracteres.
     * 2. Llama a MarcaService::create($data) para asignar ID e insertar en la base de datos.
     * 3. Retorna la marca creada con código HTTP 201 (Created).
     *
     * Consultas SQL ejecutadas internamente:
     * -- INSERT INTO "marcas" ("id_marca", "nombre", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request Petición con el campo 'nombre'.
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
        ]);

        $data = $request->all();

        $marca = MarcaService::create($data);
        if (!$marca) {
            return $this->errorResponse('Marca no encontrada', 404);
        }
        return $this->successResponse($marca, 'Marca creada correctamente', 201);
    }

    /**
     * Obtiene los datos de una marca específica según su identificador.
     *
     * Lógica:
     * 1. Localiza la marca mediante MarcaService::getOne($id).
     * 2. Si no existe retorna 404, de lo contrario retorna el objeto con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "marcas" WHERE "id_marca" = :id LIMIT 1;
     *
     * @param int|string $id Identificador de la marca.
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $marca = MarcaService::getOne($id);
        if (!$marca) {
            return $this->errorResponse('Marca no encontrada', 404);
        }

        return $this->successResponse($marca, 'Marca obtenida correctamente');
    }

    /**
     * Actualiza el nombre de una marca existente.
     *
     * Lógica:
     * 1. Valida el campo 'nombre' recibido.
     * 2. Comprueba que el usuario haya enviado al menos un campo en el request.
     * 3. Llama a MarcaService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "marcas" WHERE "id_marca" = :id LIMIT 1;
     * -- UPDATE "marcas" SET "nombre" = '...', "updated_at" = NOW() WHERE "id_marca" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
        ]);

        // Validar que al menos un campo sea modificado
        if (!$request->has('nombre')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $marca = MarcaService::update($id, $data);
        if (!$marca) {
            return $this->errorResponse('Marca no encontrada', 404);
        }

        return $this->successResponse($marca, 'Marca actualizada correctamente');
    }

    /**
     * Elimina una marca de la base de datos.
     *
     * Lógica:
     * 1. Invoca MarcaService::delete($id).
     * 2. Si la marca no existe o no pudo eliminarse retorna error 404.
     * 3. Retorna la marca eliminada en la respuesta.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "marcas" WHERE "id_marca" = :id LIMIT 1;
     * -- DELETE FROM "marcas" WHERE "id_marca" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $marca = MarcaService::delete($id);
        if (!$marca) {
            return $this->errorResponse('Marca no encontrada o no se pudo eliminar', 404);
        }

        return $this->successResponse($marca, 'Marca eliminada correctamente');
    }
}