<?php

namespace App\Http\Controllers;

use App\Services\OrdenService;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class OrdenController
 *
 * Controlador principal encargado de coordinar el ciclo de vida de las órdenes comerciales y de taller
 * (Ventas directas, Recambios y Reparaciones directas).
 * Maneja la creación con líneas de detalle o repuestos entrantes, actualización de estados,
 * y el procedimiento de anulación integral con reversión de inventarios y descarte/reintegro de insumos.
 */
class OrdenController extends Controller
{
    /**
     * Retorna el listado completo de órdenes registradas en el sistema.
     *
     * Lógica:
     * 1. Consulta la colección total de órdenes mediante OrdenService::getAll().
     * 2. Devuelve respuesta JSON con status 200 y mensaje correspondiente.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "ordenes";
     *
     * @return JsonResponse Lista de órdenes.
     */
    public function index(): JsonResponse
    {
        $ordenes = OrdenService::getAll();
        return $this->successResponse(
            $ordenes,
            $ordenes->isEmpty() ? 'No se encontraron órdenes' : 'Órdenes obtenidas correctamente'
        );
    }

    /**
     * Registra una nueva orden en el sistema dentro de una transacción segura de base de datos.
     *
     * Lógica:
     * 1. Valida la integridad de la cabecera (cliente, tipo de orden, montos, estados iniciales).
     * 2. Valida los arreglos de detalles (para ventas e intercambios de recambio) o repuestos entrantes (para reparaciones).
     * 3. Resuelve el id_admin automáticamente desde el token autenticado si no fue provisto.
     * 4. Invoca OrdenService::create($data), que ejecuta toda la lógica de negocio:
     *    - Genera correlativo/ID.
     *    - Inserta líneas de detalle y vincula repuestos salientes / entrantes.
     *    - En recambios/reparaciones, genera los repuestos entrantes y sus registros de reparación asociados.
     *    - Actualiza existencias y recalcula costos promedio en el inventario.
     * 5. Retorna la orden creada con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- BEGIN;
     * -- INSERT INTO "ordenes" (...) VALUES (...);
     * -- INSERT INTO "detalles_ordenes" (...) VALUES (...);
     * -- INSERT INTO "repuestos" (...) VALUES (...);
     * -- INSERT INTO "reparaciones" (...) VALUES (...);
     * -- Actualización de inventario: UPDATE "inventario" SET ...;
     * -- COMMIT;
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Cabecera
            'id_cliente'               => 'required|integer|exists:clientes,id_cliente',
            'id_tipo_orden'            => 'required|integer|exists:tipos_ordenes,id_tipo_orden',
            'id_admin'                 => 'nullable|integer|exists:admins,id_admin',
            'fecha_creacion'           => 'nullable|date',
            'monto_total_gravado'      => 'nullable|numeric',
            'monto_total_exento'       => 'nullable|numeric',
            'monto_total_iva'          => 'nullable|numeric',
            'monto_total'              => 'nullable|numeric',
            'monto_pendiente'          => 'nullable|numeric',
            'fecha_entrega_reparacion' => 'nullable|date',
            'estado_operativo'         => 'nullable|in:en_espera,en_proceso,finalizada,anulada',
            'estado_administrativo'    => 'nullable|in:pendiente_pago,pagada',

            // Líneas de detalles (para Venta y Recambio)
            'detalles'                                     => 'nullable|array',
            'detalles.*.id_inventario_insumo_saliente'     => 'nullable|integer|exists:inventario,id_inventario',
            'detalles.*.id_inventario_repuesto_saliente'   => 'nullable|integer|exists:inventario,id_inventario',
            'detalles.*.id_repuesto_saliente'              => 'nullable|integer|exists:repuestos,id_repuesto',
            'detalles.*.repuestos_salientes'               => 'nullable|array',
            'detalles.*.repuestos_salientes.*.id_repuesto' => 'required_with:detalles.*.repuestos_salientes|integer|exists:repuestos,id_repuesto',
            'detalles.*.repuestos_salientes.*.precio_unitario' => 'nullable|numeric|min:0',
            'detalles.*.id_inventario_repuesto_entrante'   => 'nullable|integer|exists:inventario,id_inventario',
            'detalles.*.id_repuesto_entrante'              => 'nullable|integer|exists:repuestos,id_repuesto',
            'detalles.*.cantidad'                          => 'nullable|integer|min:1',
            'detalles.*.precio_unitario'                   => 'nullable|numeric|min:0',
            'detalles.*.monto_tasacion'                    => 'nullable|numeric|min:0',
            'detalles.*.porcentaje_iva'                    => 'nullable|numeric|min:0',
            'detalles.*.serial_entrante'                   => 'nullable|string|max:100',
            'detalles.*.service_tag_entrante'             => 'nullable|string|max:100',
            'detalles.*.nombre_entrante'                   => 'nullable|string|max:100',

            // Repuestos que ingresan (para Órdenes de Reparación directa)
            'repuestos_entrantes'                 => 'nullable|array',
            'repuestos_entrantes.*.id_inventario' => 'required_with:repuestos_entrantes|integer|exists:inventario,id_inventario',
            'repuestos_entrantes.*.serial'        => 'nullable|string|max:100',
            'repuestos_entrantes.*.service_tag'   => 'nullable|string|max:100',
            'repuestos_entrantes.*.nombre'        => 'nullable|string|max:100',
        ]);

        $data = $request->all();

        if (empty($data['id_admin'])) {
            $userId = auth()->id();
            // Consulta SQL Raw equivalente:
            // SELECT * FROM "admins" WHERE "id_user" = :userId LIMIT 1;
            $admin = $userId ? Admin::where('id_user', $userId)->first() : null;
            $data['id_admin'] = $admin ? $admin->id_admin : 1;
        }

        $orden = OrdenService::create($data);
        if (!$orden) {
            return $this->errorResponse('Orden no creada', 404);
        }

        return $this->successResponse($orden, 'Orden creada correctamente', 201);
    }

    /**
     * Consulta y devuelve la información de una orden con sus detalles y relaciones por ID.
     *
     * Lógica:
     * 1. Invoca OrdenService::getOne($id).
     * 2. Si no existe devuelve 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "ordenes" WHERE "id_orden" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $orden = OrdenService::getOne($id);
        if (!$orden) {
            return $this->errorResponse('Orden no encontrada', 404);
        }

        return $this->successResponse($orden, 'Orden obtenida correctamente');
    }

    /**
     * Actualiza atributos informativos o estados de una orden existente.
     *
     * Lógica:
     * 1. Valida los campos proporcionados.
     * 2. Comprueba que al menos un campo modificable haya sido enviado.
     * 3. Invoca OrdenService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "ordenes" WHERE "id_orden" = :id LIMIT 1;
     * -- UPDATE "ordenes" SET "estado_operativo" = '...', "updated_at" = NOW() WHERE "id_orden" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_cliente'               => 'integer|exists:clientes,id_cliente',
            'id_tipo_orden'            => 'integer|exists:tipos_ordenes,id_tipo_orden',
            'id_admin'                 => 'integer|exists:admins,id_admin',
            'monto_total_gravado'      => 'numeric',
            'monto_total_exento'       => 'numeric',
            'monto_total_iva'          => 'numeric',
            'monto_total'              => 'numeric',
            'monto_pendiente'          => 'numeric',
            'fecha_entrega_reparacion' => 'nullable|date',
            'estado_operativo'         => 'in:en_espera,en_proceso,finalizada,anulada',
            'estado_administrativo'    => 'in:pendiente_pago,pagada',
            'id_admin_anulacion'       => 'nullable|integer|exists:admins,id_admin',
            'motivo_anulacion'         => 'nullable|string|max:250',
        ]);

        if (
            !$request->has('id_cliente') &&
            !$request->has('id_tipo_orden') &&
            !$request->has('id_admin') &&
            !$request->has('monto_total_gravado') &&
            !$request->has('monto_total_exento') &&
            !$request->has('monto_total_iva') &&
            !$request->has('monto_total') &&
            !$request->has('monto_pendiente') &&
            !$request->has('fecha_entrega_reparacion') &&
            !$request->has('estado_operativo') &&
            !$request->has('estado_administrativo') &&
            !$request->has('id_admin_anulacion') &&
            !$request->has('motivo_anulacion')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $orden = OrdenService::update($id, $data);
        if (!$orden) {
            return $this->errorResponse('Orden no encontrada', 404);
        }

        return $this->successResponse($orden, 'Orden actualizada correctamente');
    }

    /**
     * Elimina físicamente una orden del sistema si no posee restricciones de integridad.
     *
     * Lógica:
     * 1. Invoca OrdenService::delete($id).
     * 2. Si no existe o no se pudo eliminar retorna error 404.
     * 3. Devuelve la orden eliminada con HTTP 200.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "ordenes" WHERE "id_orden" = :id LIMIT 1;
     * -- DELETE FROM "ordenes" WHERE "id_orden" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $orden = OrdenService::delete($id);
        if (!$orden) {
            return $this->errorResponse('Orden no encontrada o no se pudo eliminar', 404);
        }

        return $this->successResponse($orden, 'Orden eliminada correctamente');
    }

    /**
     * Anula formalmente una orden comercial o de taller y revierte sus impactos en el inventario.
     *
     * Lógica:
     * 1. Valida el motivo de anulación y el arreglo de insumos_devueltos (para reparaciones con desarme).
     * 2. Resuelve el id_admin del usuario autenticado que autoriza la anulación.
     * 3. Invoca OrdenService::anular($id, $data) que:
     *    - Comprueba que la orden no esté previamente anulada.
     *    - Devuelve los repuestos/insumos vendidos al stock.
     *    - Desvincula repuestos entrantes o gestiona la devolución parcial de insumos gastados en el desarme.
     *    - Marca pagos asociados como anulados.
     *    - Recalcula los costos promedio y existencias del inventario.
     * 4. Retorna la orden en estado 'anulada' o mensaje de error según corresponda.
     *
     * Consultas SQL ejecutadas internamente:
     * -- BEGIN;
     * -- UPDATE "ordenes" SET "estado_operativo" = 'anulada', "motivo_anulacion" = '...', "id_admin_anulacion" = ... WHERE "id_orden" = :id;
     * -- UPDATE "pagos_clientes" SET "anulado" = true WHERE "id_orden" = :id;
     * -- Reversión de inventario: UPDATE "inventario" ...;
     * -- COMMIT;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function anular(Request $request, $id): JsonResponse
    {
        $request->validate([
            'motivo_anulacion'   => 'required|string|max:250',
            'insumos_devueltos'  => 'nullable|array',
            'insumos_devueltos.*.id_reparacion_insumo' => 'required_with:insumos_devueltos|integer|exists:reparaciones_insumos,id_reparacion_insumo',
            'insumos_devueltos.*.cantidad_no_gastada'  => 'required_with:insumos_devueltos|integer|min:0',
        ]);

        $data = $request->all();

        // Resolver id_admin_anulacion automáticamente desde el token autenticado
        $userId = auth()->id();
        // Consulta SQL Raw equivalente:
            // SELECT * FROM "admins" WHERE "id_user" = :userId LIMIT 1;
            $admin = $userId ? Admin::where('id_user', $userId)->first() : null;
        $data['id_admin_anulacion'] = $admin ? $admin->id_admin : ($data['id_admin_anulacion'] ?? 1);

        $resultado = OrdenService::anular($id, $data);

        if (is_string($resultado)) {
            return $this->errorResponse($resultado, 422);
        }

        if (!$resultado) {
            return $this->errorResponse('Orden no encontrada o no se pudo anular', 404);
        }

        return $this->successResponse($resultado, 'Orden anulada correctamente');
    }
}