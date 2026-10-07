<?php

namespace App\Http\Controllers;

use App\Services\ReparacionServicioTallerService;
use App\Services\CotizacionService;
use App\Models\ReparacionServicioTaller;
use App\Models\Reparacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ReparacionServicioTallerController
 *
 * Controlador RESTful encargado de gestionar los servicios y tareas de mano de obra
 * aplicados a una reparación en curso (tornería, embobinado, limpieza, diagnósticos).
 */
class ReparacionServicioTallerController extends Controller
{
    /**
     * Retorna la lista total de servicios cargados a reparaciones.
     *
     * Lógica:
     * 1. Consulta la colección completa mediante ReparacionServicioTallerService::getAll().
     * 2. Devuelve respuesta estándar JSON con código 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "reparaciones_servicios_taller";
     *
     * @return JsonResponse Lista de servicios de reparaciones.
     */
    public function index(): JsonResponse
    {
        $servicios = ReparacionServicioTallerService::getAll();
        return $this->successResponse(
            $servicios,
            $servicios->isEmpty() ? 'No se encontraron servicios de reparaciones' : 'Servicios de reparaciones obtenidos correctamente'
        );
    }

    /**
     * Agrega una actividad o servicio a una reparación específica.
     *
     * Lógica:
     * 1. Valida que id_reparacion e id_servicio_taller existan, junto a cantidad e importes.
     * 2. Llama a ReparacionServicioTallerService::create($data), agregando el servicio y actualizando
     *    los costos totales acumulados en la cabecera de la reparación.
     * 3. Retorna el servicio creado con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- INSERT INTO "reparaciones_servicios_taller" (...) VALUES (...);
     * -- UPDATE "reparaciones" SET "costo_servicios_base" = ... WHERE "id_reparacion" = :id_rep;
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_reparacion'                  => 'required|integer|exists:reparaciones,id_reparacion',
            'id_servicio_taller'             => 'required|integer|exists:servicios_taller,id_servicio_taller',
            'id_admin'                       => 'nullable|integer|exists:admins,id_admin',
            'cantidad'                       => 'required|integer|min:1',
            'costo_unitario'                 => 'nullable|numeric|min:0',
            'monto_total_linea'              => 'nullable|numeric|min:0',
            'costo_unitario_con_ganancia'    => 'nullable|numeric|min:0',
            'monto_total_linea_con_ganancia' => 'nullable|numeric|min:0',
            'porcentaje_iva'                 => 'nullable|numeric|min:0',
            'monto_iva'                      => 'nullable|numeric|min:0',
            'estado'                         => 'nullable|in:pendiente_asignacion,en_espera,en_proceso,finalizado',
            'fecha_inicio'                   => 'nullable|date',
            'fecha_fin'                      => 'nullable|date',
        ]);

        $data = $request->all();

        $servicio = ReparacionServicioTallerService::create($data);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no creado', 404);
        }

        return $this->successResponse($servicio, 'Servicio agregado a la reparación correctamente', 201);
    }

    /**
     * Consulta y devuelve la información de un servicio de reparación por su ID.
     *
     * Lógica:
     * 1. Busca el servicio mediante ReparacionServicioTallerService::getOne($id).
     * 2. Si no existe retorna 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "reparaciones_servicios_taller" WHERE "id_reparacion_servicio_taller" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $servicio = ReparacionServicioTallerService::getOne($id);
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller obtenido correctamente');
    }

    /**
     * Actualiza el estado o costos de un servicio aplicado a la reparación.
     *
     * Lógica:
     * 1. Valida campos cuantitativos, importes o cambios de estado operativo.
     * 2. Verifica regla de negocio: si el servicio pasa a 'en_proceso', debe tener al menos un técnico asignado.
     *    Si no tiene técnicos, retorna error 422.
     * 3. Invoca ReparacionServicioTallerService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT count(*) FROM "asignaciones" WHERE "id_reparacion_servicio_taller" = :id;
     * -- UPDATE "reparaciones_servicios_taller" SET "estado" = 'en_proceso' ... WHERE "id_reparacion_servicio_taller" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'cantidad'                       => 'integer|min:1',
            'costo_unitario'                 => 'numeric|min:0',
            'monto_total_linea'              => 'numeric|min:0',
            'costo_unitario_con_ganancia'    => 'numeric|min:0',
            'monto_total_linea_con_ganancia' => 'numeric|min:0',
            'porcentaje_iva'                 => 'numeric|min:0',
            'monto_iva'                      => 'numeric|min:0',
            'estado'                         => 'in:pendiente_asignacion,en_espera,en_proceso,finalizado',
            'fecha_inicio'                   => 'nullable|date',
            'fecha_fin'                      => 'nullable|date',
        ]);

        if (
            !$request->has('cantidad') &&
            !$request->has('costo_unitario') &&
            !$request->has('monto_total_linea') &&
            !$request->has('costo_unitario_con_ganancia') &&
            !$request->has('monto_total_linea_con_ganancia') &&
            !$request->has('porcentaje_iva') &&
            !$request->has('monto_iva') &&
            !$request->has('estado') &&
            !$request->has('fecha_inicio') &&
            !$request->has('fecha_fin')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $servicio = ReparacionServicioTallerService::update($id, $data);
        if ($servicio === false) {
            return $this->errorResponse('Para poner un servicio en ejecución (en_proceso) debe tener al menos un mecánico asignado', 422);
        }
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse($servicio, 'Servicio de taller actualizado correctamente');
    }

    /**
     * Elimina un servicio asignado a una reparación.
     *
     * Lógica:
     * 1. Solo permite eliminar servicios en estado 'pendiente_asignacion' o 'en_espera'.
     *    Si el servicio ya está en proceso o finalizado, retorna error 422.
     * 2. Si no existe devuelve 404.
     * 3. En caso de éxito, elimina el registro y descuenta el importe de la cabecera de la reparación.
     *
     * Consultas SQL ejecutadas internamente:
     * -- DELETE FROM "reparaciones_servicios_taller" WHERE "id_reparacion_servicio_taller" = :id;
     * -- UPDATE "reparaciones" SET "costo_servicios_base" = ... WHERE "id_reparacion" = :id_rep;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $servicio = ReparacionServicioTallerService::delete($id);
        if ($servicio === false) {
            return $this->errorResponse('Solo se pueden eliminar servicios en estado pendiente_asignacion o en_espera', 422);
        }
        if (!$servicio) {
            return $this->errorResponse('Servicio de taller no encontrado', 404);
        }

        return $this->successResponse(null, 'Servicio de taller eliminado exitosamente');
    }
}