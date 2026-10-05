<?php

namespace App\Http\Controllers;

use App\Services\ServicioTallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ServicioTallerController
 *
 * Controlador RESTful encargado de gestionar los servicios y actividades técnicas que se ofrecen en el taller.
 * Permite configurar costos base, márgenes de ganancia y porcentaje de IVA delegando en ServicioTallerService.
 */
class ServicioTallerController extends Controller
{
    /**
     * Retorna todos los servicios de taller registrados en el sistema.
     *
     * Lógica:
     * 1. Consulta la lista total mediante ServicioTallerService::getAll().
     * 2. Retorna respuesta estándar JSON con código 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "servicios_taller";
     *
     * @return JsonResponse Lista de servicios de taller.
     */
    public function index(): JsonResponse
    {
        $servicios = ServicioTallerService::getAll();
        return $this->successResponse(
            $servicios,
            $servicios->isEmpty() ? 'No se encontraron servicios de taller' : 'Servicios de taller obtenidos correctamente'
        );
    }

    /**
     * Registra un nuevo servicio de taller con sus parámetros económicos.
     *
     * Lógica:
     * 1. Valida que id_tipo_servicio_taller exista y valida nombre, descripción, costo base e IVA.
     * 2. Invoca ServicioTallerService::create($data) para calcular el ID y almacenar en base de datos.
     * 3. Devuelve el servicio creado con código HTTP 201 (Created).
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación de clave foránea:
     * -- SELECT count(*) FROM "tipos_servicios_taller" WHERE "id_tipo_servicio_taller" = :id_tipo LIMIT 1;
     * -- Inserción de registro:
     * -- INSERT INTO "servicios_taller" ("id_servicio_taller", "id_tipo_servicio_taller", "nombre", "descripcion", "costo_base", "porcentaje_iva", "porcentaje_ganancia", "created_at", "updated_at") VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_tipo_servicio_taller' => 'required|integer|exists:tipos_servicios_taller,id_tipo_servicio_taller',
            'nombre' => 'required|string|max:100',
            'descripcion' => 'required|string|max:200',
            'costo_base' => 'nullable|numeric',
            'porcentaje_iva' => 'nullable|numeric',
            'porcentaje_ganancia' => 'nullable|numeric',
        ]);

        $data = $request->all();

        $servicio = ServicioTallerService::create($data);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no creado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller creado correctamente', 201);
    }

    /**
     * Consulta y retorna los datos detallados de un servicio de taller por su ID.
     *
     * Lógica:
     * 1. Localiza el servicio a través de ServicioTallerService::getOne($id).
     * 2. Si no existe retorna 404, de lo contrario retorna el objeto con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "servicios_taller" WHERE "id_servicio_taller" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $servicio = ServicioTallerService::getOne($id);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller obtenido correctamente');
    }

    /**
     * Actualiza la información y parámetros de cobro de un servicio de taller.
     *
     * Lógica:
     * 1. Valida los campos económicos y descriptivos enviados.
     * 2. Comprueba que al menos un campo modificable haya sido provisto.
     * 3. Llama a ServicioTallerService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "servicios_taller" WHERE "id_servicio_taller" = :id LIMIT 1;
     * -- UPDATE "servicios_taller" SET "costo_base" = 50000.00, "updated_at" = NOW() WHERE "id_servicio_taller" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_tipo_servicio_taller' => 'integer|exists:tipos_servicios_taller,id_tipo_servicio_taller',
            'nombre' => 'string|max:100',
            'descripcion' => 'string|max:200',
            'costo_base' => 'numeric',
            'porcentaje_iva' => 'numeric',
            'porcentaje_ganancia' => 'numeric',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_tipo_servicio_taller') &&
            !$request->has('nombre') &&
            !$request->has('descripcion') &&
            !$request->has('costo_base') &&
            !$request->has('porcentaje_iva') &&
            !$request->has('porcentaje_ganancia')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $servicio = ServicioTallerService::update($id, $data);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller actualizado correctamente');
    }

    /**
     * Elimina un servicio de taller de la base de datos.
     *
     * Lógica:
     * 1. Invoca ServicioTallerService::delete($id).
     * 2. Si no existe o no se pudo eliminar devuelve error 404.
     * 3. Retorna el servicio eliminado con HTTP 200.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "servicios_taller" WHERE "id_servicio_taller" = :id LIMIT 1;
     * -- DELETE FROM "servicios_taller" WHERE "id_servicio_taller" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $servicio = ServicioTallerService::delete($id);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller eliminado correctamente');
    }
}