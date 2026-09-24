<?php

namespace App\Http\Controllers;

use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(): JsonResponse
    {
        $clientes = ClienteService::getAll();
        return $this->successResponse(
            $clientes,
            $clientes->isEmpty() ? 'No se encontraron clientes' : 'Clientes obtenidos correctamente'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'rut' => 'required|string|max:100|unique:clientes,rut',
            'correo' => 'required|email|max:100|unique:clientes,correo',
            'num_tlf' => 'required|string|max:50|unique:clientes,num_tlf',
            'direccion' => 'required|string|max:100',
        ]);

        $cliente = ClienteService::create($request->all());

        if (!$cliente) {
            return $this->errorResponse('Cliente no creado', 404);
        }

        return $this->successResponse($cliente, 'Cliente creado correctamente', 201);
    }

    public function show($id): JsonResponse
    {
        $cliente = ClienteService::getOne($id);
        if (!$cliente) {
            return $this->errorResponse('Cliente no encontrado', 404);
        }

        return $this->successResponse($cliente, 'Cliente obtenido correctamente');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'nombre' => 'string|max:100',
            'rut' => "string|max:100|unique:clientes,rut,{$id},id_cliente",
            'correo' => "email|max:100|unique:clientes,correo,{$id},id_cliente",
            'num_tlf' => "string|max:50|unique:clientes,num_tlf,{$id},id_cliente",
            'direccion' => 'string|max:100',
        ]);

        // validar que al menos un campo sea modificado
        if (!$request->has('nombre') && !$request->has('rut') && !$request->has('correo') && !$request->has('num_tlf') && !$request->has('direccion')) {
            return $this->errorResponse('Al menos un campo debe ser modificado', 400);
        }

        $data = $request->all();

        $cliente = ClienteService::update($id, $data);
        if (!$cliente) {
            return $this->errorResponse('Cliente no encontrado', 404);
        }

        return $this->successResponse($cliente, 'Cliente actualizado correctamente');
    }

    public function destroy($id): JsonResponse
    {
        $cliente = ClienteService::delete($id);
        if (!$cliente) {
            return $this->errorResponse('Cliente no encontrado o no se pudo eliminar', 404);
        }

        return $this->successResponse($cliente, 'Cliente eliminado correctamente');
    }
}