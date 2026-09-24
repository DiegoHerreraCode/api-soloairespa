<?php

namespace App\Http\Controllers;

use App\Services\ProveedorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(): JsonResponse
    {
        $proveedores = ProveedorService::getAll();
        return $this->successResponse(
            $proveedores,
            $proveedores->isEmpty() ? 'No se encontraron proveedores' : 'Proveedores obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'rut' => 'required|string|max:100|unique:proveedores,rut',
            'correo' => 'required|email|max:100|unique:proveedores,correo',
            'num_tlf' => 'required|string|max:100|unique:proveedores,num_tlf',
            'direccion' => 'required|string|max:100',
        ]);

        $proveedor = ProveedorService::create($request->all());

        if (!$proveedor) {
            return $this->errorResponse('Proveedor no creado', 404);
        }

        return $this->successResponse($proveedor, 'Proveedor creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $proveedor = ProveedorService::getOne($id);
        if (!$proveedor) {
            return $this->errorResponse('Proveedor no encontrado', 404);
        }

        return $this->successResponse($proveedor, 'Proveedor obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'rut' => "string|max:100|unique:proveedores,rut,{$id},id_proveedor",
            'correo' => "email|max:100|unique:proveedores,correo,{$id},id_proveedor",
            'num_tlf' => "string|max:100|unique:proveedores,num_tlf,{$id},id_proveedor",
            'direccion' => 'string|max:100',
        ]);

        // validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('rut') && !$request->has('correo') && !$request->has('num_tlf') && !$request->has('direccion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $proveedor = ProveedorService::update($id, $request->all());
        if (!$proveedor) {
            return $this->errorResponse('Proveedor no encontrado', 404);
        }

        return $this->successResponse($proveedor, 'Proveedor actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $proveedor = ProveedorService::delete($id);
        if (!$proveedor) {
            return $this->errorResponse('Proveedor no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($proveedor, 'Proveedor eliminado correctamente');
    }
}