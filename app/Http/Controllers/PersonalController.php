<?php

namespace App\Http\Controllers;

use App\Services\PersonalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalController extends Controller
{
    public function index(): JsonResponse
    {
        $personal = PersonalService::getAll();
        return $this->successResponse(
            $personal,
            $personal->isEmpty() ? 'No se encontró personal' : 'Personal obtenido correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'rut' => 'required|string|max:100|unique:personal,rut',
            'correo' => 'required|email|max:100|unique:personal,correo',
            'num_tlf' => 'required|string|max:50|unique:personal,num_tlf',
            'direccion' => 'required|string|max:100',
            'disponible' => 'nullable|boolean',
        ]);

        $personal = PersonalService::create($request->all());

        if (!$personal) {
            return $this->errorResponse('Personal no creado', 404);
        }
        return $this->successResponse($personal, 'Personal creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $personal = PersonalService::getOne($id);
        if (!$personal) {
            return $this->errorResponse('Personal no encontrado', 404);
        }

        return $this->successResponse($personal, 'Personal obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'rut' => "string|max:100|unique:personal,rut,{$id},id_personal",
            'correo' => "email|max:100|unique:personal,correo,{$id},id_personal",
            'num_tlf' => "string|max:50|unique:personal,num_tlf,{$id},id_personal",
            'direccion' => 'string|max:100',
            'disponible' => 'boolean',
            'is_deleted' => 'boolean',
        ]);

        //validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('rut') && !$request->has('correo') && !$request->has('num_tlf') && !$request->has('direccion') && !$request->has('disponible') && !$request->has('is_deleted')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $personal = PersonalService::update($id, $request->all());
        if (!$personal) {
            return $this->errorResponse('Personal no encontrado', 404);
        }

        return $this->successResponse($personal, 'Personal actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $personal = PersonalService::delete($id);
        if (!$personal) {
            return $this->errorResponse('Personal no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($personal, 'Personal eliminado correctamente');
    }
}