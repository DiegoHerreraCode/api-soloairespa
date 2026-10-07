<?php

namespace App\Http\Controllers;

use App\Services\ReparacionService;
use App\Services\ReparacionServicioTallerService;
use App\Services\CotizacionService;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ReparacionController
 *
 * Controlador RESTful encargado de gestionar los procesos de diagnóstico, reparación y acondicionamiento
 * de repuestos en el taller técnico.
 * Coordina la cabecera del trabajo, los insumos consumidos, las actividades de servicio y el recálculo
 * acumulado de costos directos, márgenes y precios con IVA.
 */
class ReparacionController extends Controller
{
    /**
     * Retorna el listado completo de reparaciones registradas.
     *
     * Lógica:
     * 1. Consulta la lista total mediante ReparacionService::getAll().
     * 2. Devuelve respuesta JSON con status 200 y mensaje correspondiente.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "reparaciones";
     *
     * @return JsonResponse Lista de reparaciones.
     */
    public function index(): JsonResponse
    {
        $reparaciones = ReparacionService::getAll();
        return $this->successResponse(
            $reparaciones,
            $reparaciones->isEmpty() ? 'No se encontraron reparaciones' : 'Reparaciones obtenidas correctamente'
        );
    }

    /**
     * Registra una nueva reparación con sus insumos y servicios iniciales.
     *
     * Lógica:
     * 1. Valida el repuesto a reparar, orden vinculada (opcional), fechas, administradores y estados.
     * 2. Valida los arreglos opcionales de insumos (cantidades, inventario de origen) y servicios de taller.
     * 3. Resuelve el id_admin a partir de la sesión autenticada si no fue especificado.
     * 4. Invoca ReparacionService::create($data), que inserta la reparación, descuenta insumos de stock
     *    y consolida los costos totales.
     * 5. Retorna la reparación creada con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- BEGIN;
     * -- INSERT INTO "reparaciones" (...) VALUES (...);
     * -- INSERT INTO "reparaciones_insumos" (...) VALUES (...);
     * -- UPDATE "inventario" SET "cantidad_propia" = cantidad_propia - :cant ...;
     * -- INSERT INTO "reparaciones_servicios_taller" (...) VALUES (...);
     * -- COMMIT;
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Cabecera
            'id_repuesto' => 'required|integer|exists:repuestos,id_repuesto',
            'id_orden' => 'nullable|integer|exists:ordenes,id_orden',
            'estado' => 'nullable|in:pendiente,en_proceso,finalizada',
            'fecha_inicio' => 'nullable|date',
            'id_admin_fecha_inicio' => 'nullable|integer|exists:admins,id_admin',
            'fecha_fin' => 'nullable|date',
            'id_admin_fecha_fin' => 'nullable|integer|exists:admins,id_admin',
            'costo_servicios_base' => 'nullable|numeric|min:0',
            'costo_insumos_base' => 'nullable|numeric|min:0',
            'costo_servicios_con_ganancia' => 'nullable|numeric|min:0',
            'costo_insumos_con_ganancia' => 'nullable|numeric|min:0',
            'costo_total' => 'nullable|numeric|min:0',
            'costo_total_con_ganancia' => 'nullable|numeric|min:0',
            'monto_total_iva' => 'nullable|numeric|min:0',

            // Arreglo opcional de insumos (para sincronización unificada o cotización extra)
            'insumos' => 'nullable|array',
            'insumos.*.id_reparacion_insumo' => 'nullable|integer|exists:reparaciones_insumos,id_reparacion_insumo',
            'insumos.*.id_inventario' => 'required_with:insumos|integer|exists:inventario,id_inventario',
            'insumos.*.id_inventario_insumo_saliente' => 'nullable|integer|exists:inventario,id_inventario',
            'insumos.*.cantidad' => 'required_with:insumos|integer|min:1',
            'insumos.*.costo_unitario' => 'nullable|numeric|min:0',
            'insumos.*.costo_unitario_con_ganancia' => 'nullable|numeric|min:0',
            'insumos.*.precio_unitario_base' => 'nullable|numeric|min:0',
            'insumos.*.precio_unitario' => 'nullable|numeric|min:0',
            'insumos.*.porcentaje_iva' => 'nullable|numeric|min:0',

            // Arreglo opcional de servicios (para sincronización unificada o cotización extra)
            'servicios' => 'nullable|array',
            'servicios.*.id_reparacion_servicio_taller' => 'nullable|integer|exists:reparaciones_servicios_taller,id_reparacion_servicio_taller',
            'servicios.*.id_servicio_taller' => 'required_with:servicios|integer|exists:servicios_taller,id_servicio_taller',
            'servicios.*.cantidad' => 'required_with:servicios|integer|min:1',
            'servicios.*.costo_unitario' => 'nullable|numeric|min:0',
            'servicios.*.costo_unitario_con_ganancia' => 'nullable|numeric|min:0',
            'servicios.*.precio_unitario_base' => 'nullable|numeric|min:0',
            'servicios.*.precio_unitario' => 'nullable|numeric|min:0',
            'servicios.*.porcentaje_iva' => 'nullable|numeric|min:0',
            'servicios.*.estado' => 'nullable|in:pendiente_asignacion,en_espera,en_proceso,finalizado',
        ]);

        $data = $request->all();

        if (empty($data['id_admin'])) {
            $userId = auth()->id();
            // Consulta SQL Raw equivalente:
            // SELECT * FROM "admins" WHERE "id_user" = :userId LIMIT 1;
            $admin = $userId ? Admin::where('id_user', $userId)->first() : null;
            $data['id_admin'] = $admin ? $admin->id_admin : 1;
        }

        $reparacion = ReparacionService::create($data);
        if (!$reparacion) {
            return $this->errorResponse('Reparación no creada', 404);
        }

        return $this->successResponse($reparacion, 'Reparación creada exitosamente', 201);
    }

    /**
     * Consulta y devuelve la información de una reparación con sus insumos y servicios asociados.
     *
     * Lógica:
     * 1. Busca la reparación mediante ReparacionService::getOne($id).
     * 2. Si no existe retorna 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "reparaciones" WHERE "id_reparacion" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $reparacion = ReparacionService::getOne($id);
        if (!$reparacion) {
            return $this->errorResponse('Reparación no encontrada', 404);
        }

        return $this->successResponse($reparacion, 'Reparación obtenida correctamente');
    }

    /**
     * Actualiza la información, estado o componentes (insumos/servicios) de una reparación.
     *
     * Lógica:
     * 1. Valida campos de cabecera y arreglos de sincronización opcionales de insumos y servicios.
     * 2. Comprueba que al menos un campo modificable haya sido enviado.
     * 3. Invoca ReparacionService::update($id, $data), sincronizando costos e impactando en inventario/orden si finaliza.
     * 4. Retorna la reparación actualizada con HTTP 200.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "reparaciones" WHERE "id_reparacion" = :id LIMIT 1;
     * -- UPDATE "reparaciones" SET "estado" = 'finalizada', "fecha_fin" = NOW() ... WHERE "id_reparacion" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        // Normalizar id_inventario_insumo_saliente si vino con nomenclatura de cotización
        if ($request->has('insumos') && is_array($request->input('insumos'))) {
            $insumosNorm = array_map(function ($ins) {
                if (!isset($ins['id_inventario']) && isset($ins['id_inventario_insumo_saliente'])) {
                    $ins['id_inventario'] = $ins['id_inventario_insumo_saliente'];
                }
                return $ins;
            }, $request->input('insumos'));
            $request->merge(['insumos' => $insumosNorm]);
        }
        $request->validate([
            // Cabecera
            'id_repuesto' => 'integer|exists:repuestos,id_repuesto',
            'id_orden' => 'integer|exists:ordenes,id_orden',
            'estado' => 'in:pendiente,en_proceso,finalizada',
            'fecha_inicio' => 'nullable|date',
            'id_admin_fecha_inicio' => 'integer|exists:admins,id_admin',
            'fecha_fin' => 'nullable|date',
            'id_admin_fecha_fin' => 'integer|exists:admins,id_admin',
            'costo_servicios_base' => 'numeric|min:0',
            'costo_insumos_base' => 'numeric|min:0',
            'costo_servicios_con_ganancia' => 'numeric|min:0',
            'costo_insumos_con_ganancia' => 'numeric|min:0',
            'costo_total' => 'numeric|min:0',
            'costo_total_con_ganancia' => 'numeric|min:0',
            'monto_total_iva' => 'numeric|min:0',

            // Arreglo opcional de insumos (para sincronización unificada)
            'insumos' => 'nullable|array',
            'insumos.*.id_reparacion_insumo' => 'nullable|integer|exists:reparaciones_insumos,id_reparacion_insumo',
            'insumos.*.id_inventario' => 'required_with:insumos|integer|exists:inventario,id_inventario',
            'insumos.*.cantidad' => 'required_with:insumos|integer|min:1',
            'insumos.*.costo_unitario' => 'nullable|numeric|min:0',
            'insumos.*.costo_unitario_con_ganancia' => 'nullable|numeric|min:0',
            'insumos.*.porcentaje_iva' => 'nullable|numeric|min:0',

            // Arreglo opcional de servicios (para sincronización unificada)
            'servicios' => 'nullable|array',
            'servicios.*.id_reparacion_servicio_taller' => 'nullable|integer|exists:reparaciones_servicios_taller,id_reparacion_servicio_taller',
            'servicios.*.id_servicio_taller' => 'required_with:servicios|integer|exists:servicios_taller,id_servicio_taller',
            'servicios.*.cantidad' => 'required_with:servicios|integer|min:1',
            'servicios.*.costo_unitario' => 'nullable|numeric|min:0',
            'servicios.*.costo_unitario_con_ganancia' => 'nullable|numeric|min:0',
            'servicios.*.porcentaje_iva' => 'nullable|numeric|min:0',
            'servicios.*.estado' => 'nullable|in:pendiente_asignacion,en_espera,en_proceso,finalizado',
        ]);

        if (
            !$request->hasAny([
                'id_repuesto',
                'id_orden',
                'estado',
                'fecha_inicio',
                'id_admin_fecha_inicio',
                'fecha_fin',
                'id_admin_fecha_fin',
                'costo_servicios_base',
                'costo_insumos_base',
                'costo_servicios_con_ganancia',
                'costo_insumos_con_ganancia',
                'costo_total',
                'costo_total_con_ganancia',
                'monto_total_iva',
                'insumos',
                'servicios'
            ])
        ) {
            return $this->errorResponse('Debe proporcionar al menos un campo válido para actualizar', 400);
        }

        $data = $request->all();

        // 1. Si la petición solicita explícitamente generar una cotización extra/hija (Opción B)
        if ($request->boolean('crear_cotizacion_extra')) {
            $rep = ReparacionService::getOne($id);
            if (!$rep) {
                return $this->errorResponse('Reparación no encontrada', 404);
            }
            if (empty($rep->id_orden)) {
                return $this->errorResponse('La reparación debe estar vinculada a una orden para generar cotización extra', 422);
            }

            $resultado = CotizacionService::crearCotizacionExtraDesdeReparacion(
                $rep->id_orden,
                $rep->id_repuesto,
                $data
            );

            if (is_string($resultado)) {
                return $this->errorResponse($resultado, 422);
            }

            return $this->successResponse($resultado, 'Cotización extra generada exitosamente. Los cambios se aplicarán cuando el cliente la acepte.', 201);
        }

        // 2. Validación de reglas operativas para Reparaciones
        $repActual = ReparacionService::getOne($id);
        if (!$repActual) {
            return $this->errorResponse('Reparación no encontrada', 404);
        }

        $serviceTag = $repActual->repuesto ? $repActual->repuesto->service_tag : null;
        $tieneCotizacionExtraPendiente = false;
        if (!empty($repActual->id_orden) && !empty($serviceTag)) {
            $tieneCotizacionExtraPendiente = CotizacionService::tieneCotizacionExtraPendiente($repActual->id_orden, $serviceTag);
        }

        // Regla: si el administrador intenta marcar la reparación como 'finalizada':
        if (isset($data['estado']) && $data['estado'] === 'finalizada') {
            // Condición A: Todos los servicios de la reparación deben estar finalizados
            if (!ReparacionServicioTallerService::estanTodosLosServiciosFinalizados($id)) {
                return $this->errorResponse(
                    'No se puede finalizar la reparación porque aún tiene servicios de taller pendientes o en proceso.',
                    422
                );
            }

            // Condición B: No debe tener ninguna cotización extra pendiente para este repuesto
            if ($tieneCotizacionExtraPendiente) {
                return $this->errorResponse(
                    "No se puede finalizar la reparación mientras exista una cotización extra pendiente de aprobación por el cliente para este repuesto ({$serviceTag}).",
                    422
                );
            }
        }

        // Regla: No alterar estructura de insumos o servicios directamente si hay cotización extra pendiente
        if ($tieneCotizacionExtraPendiente && $request->hasAny(['insumos', 'servicios', 'costo_insumos_base', 'costo_servicios_base'])) {
            return $this->errorResponse(
                "No se pueden modificar insumos ni servicios directamente mientras exista una cotización extra pendiente de aprobación por el cliente para este repuesto ({$serviceTag}).",
                422
            );
        }

        // 3. Ejecutar guardado directo en ReparacionService
        $reparacion = ReparacionService::update($id, $data);
        if (!$reparacion) {
            return $this->errorResponse('Reparación no encontrada', 404);
        }

        return $this->successResponse($reparacion, 'Reparación actualizada exitosamente');
    }

    /**
     * Elimina una reparación del sistema revirtiendo los insumos y servicios cargados.
     *
     * Lógica:
     * 1. Invoca ReparacionService::delete($id).
     * 2. Si no se encuentra retorna 404, de lo contrario devuelve éxito con código 200.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "reparaciones" WHERE "id_reparacion" = :id LIMIT 1;
     * -- DELETE FROM "reparaciones" WHERE "id_reparacion" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $reparacion = ReparacionService::delete($id);
        if (!$reparacion) {
            return $this->errorResponse('Reparación no encontrada', 404);
        }

        return $this->successResponse(null, 'Reparación eliminada exitosamente');
    }
}