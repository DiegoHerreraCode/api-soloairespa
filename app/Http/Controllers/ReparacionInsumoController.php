<?php

namespace App\Http\Controllers;

use App\Services\ReparacionInsumoService;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ReparacionInsumoController
 *
 * Controlador RESTful encargado de gestionar individualmente los insumos y materiales consumidos
 * en las reparaciones de taller (adición, recálculo de stock, actualización y descarte).
 */
class ReparacionInsumoController extends Controller
{
    /**
     * Retorna la lista total de insumos cargados a reparaciones.
     *
     * Lógica:
     * 1. Consulta la colección completa mediante ReparacionInsumoService::getAll().
     * 2. Devuelve respuesta estándar JSON con código 200.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "reparaciones_insumos";
     *
     * @return JsonResponse Lista de insumos de reparaciones.
     */
    public function index(): JsonResponse
    {
        $insumos = ReparacionInsumoService::getAll();
        return $this->successResponse(
            $insumos,
            $insumos->isEmpty() ? 'No se encontraron insumos de reparaciones' : 'Insumos de reparaciones obtenidos correctamente'
        );
    }

    /**
     * Agrega un nuevo insumo consumido a una reparación específica y descuenta el stock de inventario.
     *
     * Lógica:
     * 1. Valida reparación, inventario, cantidad e importes financieros asociados.
     * 2. Envía los datos a ReparacionInsumoService::create($data), que descuenta existencias en inventario
     *    y actualiza los costos acumulados de la reparación.
     * 3. Retorna el nuevo registro con código HTTP 201.
     *
     * Consultas SQL ejecutadas internamente:
     * -- INSERT INTO "reparaciones_insumos" (...) VALUES (...);
     * -- UPDATE "inventario" SET "cantidad_propia" = cantidad_propia - :cantidad WHERE "id_inventario" = :id;
     * -- UPDATE "reparaciones" SET "costo_insumos_base" = ... WHERE "id_reparacion" = :id_reparacion;
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_reparacion'                  => 'required|integer|exists:reparaciones,id_reparacion',
            'id_inventario'                  => 'required|integer|exists:inventario,id_inventario',
            'id_admin'                       => 'nullable|integer|exists:admins,id_admin',
            'cantidad'                       => 'required|integer|min:1',
            'costo_unitario'                 => 'nullable|numeric|min:0',
            'monto_total_linea'              => 'nullable|numeric|min:0',
            'costo_unitario_con_ganancia'    => 'nullable|numeric|min:0',
            'monto_total_linea_con_ganancia' => 'nullable|numeric|min:0',
            'porcentaje_iva'                 => 'nullable|numeric|min:0',
            'monto_iva'                      => 'nullable|numeric|min:0',
        ]);

        $data = $request->all();

        $insumo = ReparacionInsumoService::create($data);
        if (!$insumo) {
            return $this->errorResponse('Insumo no creado', 404);
        }

        InventarioService::verificarYNotificarStockBajo($insumo->id_inventario);

        return $this->successResponse($insumo, 'Insumo agregado a la reparación correctamente', 201);
    }

    /**
     * Consulta y devuelve la información de un insumo cargado a reparación por su ID.
     *
     * Lógica:
     * 1. Busca el registro mediante ReparacionInsumoService::getOne($id).
     * 2. Si no existe retorna 404, de lo contrario entrega el recurso con 200 OK.
     *
     * Consulta SQL ejecutada internamente vía Eloquent:
     * -- SELECT * FROM "reparaciones_insumos" WHERE "id_reparacion_insumo" = :id LIMIT 1;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $insumo = ReparacionInsumoService::getOne($id);
        if (!$insumo) {
            return $this->errorResponse('Insumo no encontrado', 404);
        }

        return $this->successResponse($insumo, 'Insumo obtenido correctamente');
    }

    /**
     * Actualiza la cantidad o costo de un insumo en una reparación ajustando la diferencia de stock.
     *
     * Lógica:
     * 1. Valida los campos numéricos recibidos.
     * 2. Verifica que al menos un campo modificable haya sido provisto.
     * 3. Invoca ReparacionInsumoService::update($id, $data) y responde con el registro actualizado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "reparaciones_insumos" WHERE "id_reparacion_insumo" = :id LIMIT 1;
     * -- UPDATE "reparaciones_insumos" SET "cantidad" = :nueva_cant WHERE "id_reparacion_insumo" = :id;
     * -- UPDATE "inventario" SET "cantidad_propia" = cantidad_propia - :delta WHERE "id_inventario" = :id;
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
        ]);

        if (
            !$request->has('cantidad') &&
            !$request->has('costo_unitario') &&
            !$request->has('monto_total_linea') &&
            !$request->has('costo_unitario_con_ganancia') &&
            !$request->has('monto_total_linea_con_ganancia') &&
            !$request->has('porcentaje_iva') &&
            !$request->has('monto_iva')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $insumo = ReparacionInsumoService::update($id, $data);
        if (!$insumo) {
            return $this->errorResponse('Insumo no encontrado', 404);
        }

        InventarioService::verificarYNotificarStockBajo($insumo->id_inventario);

        return $this->successResponse($insumo, 'Insumo actualizado correctamente');
    }

    /**
     * Elimina un insumo de la reparación y devuelve la totalidad de la cantidad física al stock propio.
     *
     * Lógica:
     * 1. Invoca ReparacionInsumoService::delete($id), devolviendo existencias a la tabla inventario
     *    y restando los montos de la cabecera de la reparación.
     * 2. Si no se encuentra retorna 404, de lo contrario entrega confirmación exitosa con HTTP 200.
     *
     * Consultas SQL ejecutadas internamente:
     * -- DELETE FROM "reparaciones_insumos" WHERE "id_reparacion_insumo" = :id;
     * -- UPDATE "inventario" SET "cantidad_propia" = cantidad_propia + :cantidad WHERE "id_inventario" = :id;
     * -- UPDATE "reparaciones" SET "costo_insumos_base" = ... WHERE "id_reparacion" = :id_rep;
     *
     * @param int|string $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $insumo = ReparacionInsumoService::delete($id);
        if (!$insumo) {
            return $this->errorResponse('Insumo no encontrado o no se pudo eliminar', 404);
        }

        InventarioService::verificarYNotificarStockBajo($insumo->id_inventario);

        return $this->successResponse(null, 'Insumo eliminado y stock devuelto exitosamente');
    }
}