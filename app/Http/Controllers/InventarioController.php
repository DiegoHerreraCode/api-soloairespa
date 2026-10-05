<?php

namespace App\Http\Controllers;

use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class InventarioController
 *
 * Controlador RESTful encargado de gestionar los ítems maestros del inventario
 * (insumos, compresores, válvulas y equipos).
 * Controla existencias consolidadas (cantidad propia vs cliente) y métricas de costos y ventas.
 */
class InventarioController extends Controller
{
    /**
     * Retorna todos los ítems registrados en el inventario.
     *
     * Lógica:
     * 1. Consulta la totalidad de ítems mediante InventarioService::getAll().
     * 2. Devuelve respuesta JSON con status 200 y mensaje correspondiente.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "inventario";
     *
     * @return JsonResponse Lista de ítems de inventario.
     */
    public function index(): JsonResponse
    {
        $items = InventarioService::getAll();
        return $this->successResponse(
            $items,
            $items->isEmpty() ? 'No se encontraron items en inventario' : 'Inventario obtenido correctamente'
        );
    }

    /**
     * Registra un nuevo ítem en el inventario maestro.
     *
     * Lógica:
     * 1. Valida el modelo asociado, SKU, nombre, tipo (insumo, compresor, valvula, equipo) y condición.
     * 2. Valida los campos numéricos de existencias, stock mínimo y métricas financieras.
     * 3. Invoca InventarioService::create($data) para asignar ID e insertar en la base de datos.
     * 4. Retorna el nuevo ítem creado con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación modelo:
     * -- SELECT count(*) FROM "modelos" WHERE "id_modelo" = :id LIMIT 1;
     * -- Inserción de registro:
     * -- INSERT INTO "inventario" ("id_inventario", "id_modelo", "sku", "nombre", "tipo", "condicion", ...) VALUES (...);
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_modelo' => 'required|integer|exists:modelos,id_modelo',
            'sku' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|in:insumo,compresor,valvula,equipo',
            'condicion' => 'required|in:nuevo,usado,NA',
            'cantidad_total' => 'nullable|integer',
            'cantidad_propia' => 'nullable|integer',
            'cantidad_cliente' => 'nullable|integer',
            'stock_minimo' => 'nullable|integer',
            'monto_compra_min' => 'nullable|numeric',
            'monto_compra_prom' => 'nullable|numeric',
            'monto_compra_max' => 'nullable|numeric',
            'monto_venta_min' => 'nullable|numeric',
            'monto_venta_prom' => 'nullable|numeric',
            'monto_venta_max' => 'nullable|numeric',
            'monto_reparacion_min' => 'nullable|numeric',
            'monto_reparacion_prom' => 'nullable|numeric',
            'monto_reparacion_max' => 'nullable|numeric',
            'ultimo_monto_compra' => 'nullable|numeric',
            'monto_venta_unitario' => 'nullable|numeric',
            'porcentaje_iva' => 'nullable|numeric',
            'porcentaje_ganancia' => 'nullable|numeric',
        ]);

        $data = $request->all();

        $item = InventarioService::create($data);
        if (!$item) {
            return $this->errorResponse('Item de inventario no creado', 404);
        }

        return $this->successResponse($item, 'Item de inventario creado correctamente', 201);
    }

    /**
     * Consulta y entrega la información de un ítem de inventario por su ID.
     *
     * Lógica:
     * 1. Busca el ítem mediante InventarioService::getOne($id).
     * 2. Si no existe devuelve 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "inventario" WHERE "id_inventario" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $item = InventarioService::getOne($id);
        if (!$item) {
            return $this->errorResponse('Item de inventario no encontrado', 404);
        }

        return $this->successResponse($item, 'Item de inventario obtenido correctamente');
    }

    /**
     * Actualiza la información o parámetros de un ítem de inventario.
     *
     * Lógica:
     * 1. Valida los campos proporcionados.
     * 2. Comprueba que al menos un campo modificable haya sido enviado.
     * 3. Invoca InventarioService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "inventario" WHERE "id_inventario" = :id LIMIT 1;
     * -- UPDATE "inventario" SET "nombre" = '...', "updated_at" = NOW() WHERE "id_inventario" = :id;
     *
     * @param Request $request
     * @param int|string $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_modelo' => 'integer|exists:modelos,id_modelo',
            'sku' => 'string|max:100',
            'nombre' => 'string|max:100',
            'tipo' => 'in:insumo,compresor,valvula,equipo',
            'condicion' => 'in:nuevo,usado,NA',
            'cantidad_total' => 'integer',
            'cantidad_propia' => 'integer',
            'cantidad_cliente' => 'integer',
            'stock_minimo' => 'integer',
            'monto_compra_min' => 'numeric',
            'monto_compra_prom' => 'numeric',
            'monto_compra_max' => 'numeric',
            'monto_venta_min' => 'numeric',
            'monto_venta_prom' => 'numeric',
            'monto_venta_max' => 'numeric',
            'monto_reparacion_min' => 'numeric',
            'monto_reparacion_prom' => 'numeric',
            'monto_reparacion_max' => 'numeric',
            'ultimo_monto_compra' => 'numeric',
            'monto_venta_unitario' => 'numeric',
            'porcentaje_iva' => 'numeric',
            'porcentaje_ganancia' => 'numeric',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_modelo') &&
            !$request->has('sku') &&
            !$request->has('nombre') &&
            !$request->has('tipo') &&
            !$request->has('condicion') &&
            !$request->has('cantidad_total') &&
            !$request->has('cantidad_propia') &&
            !$request->has('cantidad_cliente') &&
            !$request->has('stock_minimo') &&
            !$request->has('monto_compra_min') &&
            !$request->has('monto_compra_prom') &&
            !$request->has('monto_compra_max') &&
            !$request->has('monto_venta_min') &&
            !$request->has('monto_venta_prom') &&
            !$request->has('monto_venta_max') &&
            !$request->has('monto_reparacion_min') &&
            !$request->has('monto_reparacion_prom') &&
            !$request->has('monto_reparacion_max') &&
            !$request->has('ultimo_monto_compra') &&
            !$request->has('monto_venta_unitario') &&
            !$request->has('porcentaje_iva') &&
            !$request->has('porcentaje_ganancia')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $item = InventarioService::update($id, $data);
        if (!$item) {
            return $this->errorResponse('Item de inventario no encontrado', 404);
        }

        return $this->successResponse($item, 'Item de inventario actualizado correctamente');
    }

    /**
     * Elimina un ítem de inventario de la base de datos.
     *
     * Lógica:
     * 1. Invoca InventarioService::delete($id).
     * 2. Si no existe o no pudo eliminarse retorna 404.
     * 3. Devuelve el ítem eliminado con HTTP 200.
     *
     * Consultas SQL ejecutadas internamente vía Eloquent:
     * -- SELECT * FROM "inventario" WHERE "id_inventario" = :id LIMIT 1;
     * -- DELETE FROM "inventario" WHERE "id_inventario" = :id;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $item = InventarioService::delete($id);
        if (!$item) {
            return $this->errorResponse('Item de inventario no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($item, 'Item de inventario eliminado correctamente');
    }
}