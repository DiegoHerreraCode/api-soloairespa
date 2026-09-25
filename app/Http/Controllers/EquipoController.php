<?php

namespace App\Http\Controllers;

use App\Services\EquipoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipoController extends Controller
{
    public function index(): JsonResponse
    {
        $equipos = EquipoService::getAll();
        return $this->successResponse(
            $equipos,
            $equipos->isEmpty() ? 'No se encontraron equipos' : 'Equipos obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'id_inventario' => 'required|integer|exists:inventario,id_inventario',
            'id_detalle_compra' => 'required|integer|exists:detalles_compras,id_detalle_compra',
            'serial' => 'required|string|max:100',
            'nombre' => 'required|string|max:100',
            'is_deleted' => 'nullable|boolean',
        ]);

        $data = $request->all();

        $equipo = EquipoService::create($data);
        if (!$equipo) {
            return $this->errorResponse('Equipo no creado', 404);
        }

        return $this->successResponse($equipo, 'Equipo creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $equipo = EquipoService::getOne($id);
        if (!$equipo) {
            return $this->errorResponse('Equipo no encontrado', 404);
        }

        return $this->successResponse($equipo, 'Equipo obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_inventario' => 'integer|exists:inventario,id_inventario',
            'id_detalle_compra' => 'integer|exists:detalles_compras,id_detalle_compra',
            'serial' => 'string|max:100',
            'nombre' => 'string|max:100',
            'is_deleted' => 'boolean',
        ]);

        // Validar que al menos un campo sea modificado
        if (
            !$request->has('id_inventario') &&
            !$request->has('id_detalle_compra') &&
            !$request->has('serial') &&
            !$request->has('nombre') &&
            !$request->has('is_deleted')
        ) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $equipo = EquipoService::update($id, $data);
        if (!$equipo) {
            return $this->errorResponse('Equipo no encontrado', 404);
        }

        return $this->successResponse($equipo, 'Equipo actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $equipo = EquipoService::delete($id);
        if (!$equipo) {
            return $this->errorResponse('Equipo no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($equipo, 'Equipo eliminado correctamente');
    }
}
