<?php

namespace App\Http\Controllers;

use App\Services\RepuestoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class RepuestoController
 *
 * Controlador RESTful encargado de gestionar los repuestos físicos individuales
 * (compresores, válvulas, componentes) identificados por su serial único y titularidad (propio vs cliente).
 */
class RepuestoController extends Controller
{
    /**
     * Retorna la lista total de repuestos registrados en el sistema.
     *
     * Lógica:
     * 1. Consulta la colección completa mediante RepuestoService::getAll().
     * 2. Devuelve respuesta estándar JSON con HTTP 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "repuestos";
     *
     * @return JsonResponse Lista de repuestos.
     */
    public function index(): JsonResponse
    {
        $repuestos = RepuestoService::getAll();
        return $this->successResponse(
            $repuestos,
            $repuestos->isEmpty() ? 'No se encontraron repuestos' : 'Repuestos obtenidos correctamente'
        );
    }

    /**
     * Registra un nuevo repuesto individual en el sistema.
     *
     * Lógica:
     * 1. Valida inventario padre, serial, nombre, estado (nuevo, reparado, pendiente_reparacion),
     *    propiedad y valores de costos iniciales.
     * 2. Envía la carga útil a RepuestoService::create($data) para asignar ID y guardar el registro.
     * 3. Devuelve el repuesto creado con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- INSERT INTO "repuestos" ("id_repuesto", "id_inventario", "serial", "nombre", "estado", "propietario", ...) VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_inventario' => 'required|integer|exists:inventario,id_inventario',
            'id_detalle_compra' => 'nullable|integer|exists:detalles_compras,id_detalle_compra',
            'serial' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'estado' => 'required|in:nuevo,reparado,pendiente_reparacion',
            'propietario' => 'nullable|boolean',
            'id_orden_entrada' => 'nullable|integer|exists:ordenes,id_orden',
            'id_orden_salida' => 'nullable|integer|exists:ordenes,id_orden',
            'costo_adquisicion' => 'nullable|numeric',
            'costo_reparacion_base' => 'nullable|numeric',
            'costo_total' => 'nullable|numeric',
            'costo_reparacion_con_ganancia' => 'nullable|numeric',
            'monto_venta_real' => 'nullable|numeric',
            'utilidad' => 'nullable|numeric',
        ]);

        $data = $request->all();

        $repuesto = RepuestoService::create($data);
        if (!$repuesto) {
            return $this->errorResponse('Repuesto no creado', 404);
        }

        return $this->successResponse($repuesto, 'Repuesto creado correctamente', 201);
    }

    /**
     * Consulta y devuelve la información de un repuesto según su identificador único.
     *
     * Lógica:
     * 1. Busca el repuesto vía RepuestoService::getOne($id).
     * 2. Si no existe devuelve 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "repuestos" WHERE "id_repuesto" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $repuesto = RepuestoService::getOne($id);
        if (!$repuesto) {
            return $this->errorResponse('Repuesto no encontrado', 404);
        }

        return $this->successResponse($repuesto, 'Repuesto obtenido correctamente');
    }

    /**
     * Actualiza la información técnica, financiera o de trazabilidad de un repuesto.
     *
     * Lógica:
     * 1. Valida los campos proporcionados.
     * 2. Comprueba que al menos un campo modificable haya sido enviado.
     * 3. Invoca RepuestoService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "repuestos" WHERE "id_repuesto" = :id LIMIT 1;
     * -- UPDATE "repuestos" SET "estado" = 'reparado', "updated_at" = NOW() WHERE "id_repuesto" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_inventario' => 'integer|exists:inventario,id_inventario',
            'id_detalle_compra' => 'nullable|integer|exists:detalles_compras,id_detalle_compra',
            'serial' => 'string|max:100',
            'nombre' => 'string|max:100',
            'estado' => 'in:nuevo,reparado,pendiente_reparacion',
            'propietario' => 'boolean',
            'id_orden_entrada' => 'nullable|integer|exists:ordenes,id_orden',
            'id_orden_salida' => 'nullable|integer|exists:ordenes,id_orden',
            'costo_adquisicion' => 'numeric',
            'costo_reparacion_base' => 'numeric',
            'costo_total' => 'numeric',
            'costo_reparacion_con_ganancia' => 'numeric',
            'monto_venta_real' => 'numeric',
            'utilidad' => 'numeric',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_inventario') &&
            !$request->has('id_detalle_compra') &&
            !$request->has('serial') &&
            !$request->has('nombre') &&
            !$request->has('estado') &&
            !$request->has('propietario') &&
            !$request->has('id_orden_entrada') &&
            !$request->has('id_orden_salida') &&
            !$request->has('costo_adquisicion') &&
            !$request->has('costo_reparacion_base') &&
            !$request->has('costo_total') &&
            !$request->has('costo_reparacion_con_ganancia') &&
            !$request->has('monto_venta_real') &&
            !$request->has('utilidad')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $repuesto = RepuestoService::update($id, $data);
        if (!$repuesto) {
            return $this->errorResponse('Repuesto no encontrado', 404);
        }

        return $this->successResponse($repuesto, 'Repuesto actualizado correctamente');
    }

    /**
     * Elimina un repuesto de la base de datos.
     *
     * Lógica:
     * 1. Invoca RepuestoService::delete($id).
     * 2. Si no existe o no se pudo eliminar devuelve error 404.
     * 3. Retorna el repuesto eliminado con HTTP 200.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "repuestos" WHERE "id_repuesto" = :id LIMIT 1;
     * -- DELETE FROM "repuestos" WHERE "id_repuesto" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $repuesto = RepuestoService::delete($id);
        if (!$repuesto) {
            return $this->errorResponse('Repuesto no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($repuesto, 'Repuesto eliminado correctamente');
    }
}