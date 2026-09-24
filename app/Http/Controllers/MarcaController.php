<?php

namespace App\Http\Controllers;

use App\Services\MarcaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    public function index(): JsonResponse
    {
        $marcas = MarcaService::getAll();
        return $this->successResponse(
            $marcas,
            $marcas->isEmpty() ? 'No se encontraron marcas' : 'Marcas obtenidas correctamente'
        );
    }

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

    public function show($id): JsonResponse
    {
        $marca = MarcaService::getOne($id);
        if (!$marca) {
            return $this->errorResponse('Marca no encontrada', 404);
        }

        return $this->successResponse($marca, 'Marca obtenida correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
        ]);

        // validar que al menos un campo sea modificado
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

    public function destroy($id): JsonResponse
    {
        $marca = MarcaService::delete($id);
        if (!$marca) {
            return $this->errorResponse('Marca no encontrada o no se pudo eliminar', 404);
        }

        return $this->successResponse($marca, 'Marca eliminada correctamente');
    }
}