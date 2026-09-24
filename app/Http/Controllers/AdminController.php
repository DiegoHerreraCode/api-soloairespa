<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function index(): JsonResponse
    {
        $admins = AdminService::getAll();
        return $this->successResponse(
            $admins,
            $admins->isEmpty() ? 'No se encontraron admins' : 'Admins obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'rut' => 'required|string|max:11|unique:admins,rut',
            'num_tlf' => 'required|string|max:11|unique:admins,num_tlf',
            'direccion' => 'required|string|max:100',
        ]);

        $data = $request->all();

        $admin = AdminService::create($data);
        if (!$admin) {
            return $this->errorResponse('Admin no creado', 404);
        }

        return $this->successResponse($admin, 'Admin creado correctamente');
    }

    public function show($id): JsonResponse
    {
        $admin = AdminService::getOne($id);
        if (!$admin) {
            return $this->errorResponse('Admin no encontrado', 404);
        }
        return $this->successResponse($admin, 'Admin obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'rut' => "string|max:100|unique:admins,rut,{$id},id_admin",
            'num_tlf' => "string|max:50|unique:admins,num_tlf,{$id},id_admin",
            'direccion' => 'string|max:100',
        ]);

        // validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('rut') && !$request->has('num_tlf') && !$request->has('direccion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $admin = AdminService::update($id, $data);
        if (!$admin) {
            return $this->errorResponse('Admin no encontrado', 404);
        }

        return $this->successResponse($admin, 'Admin actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $admin = AdminService::delete($id);
        if (!$admin) {
            return $this->errorResponse('Admin no encontrado', 404);
        }
        return $this->successResponse($admin, 'Admin eliminado correctamente');
    }
}
