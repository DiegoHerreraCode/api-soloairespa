<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\TipoOrdenController;
use App\Http\Controllers\TipoServicioTallerController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\ModeloController;
use App\Http\Controllers\ServicioTallerController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\InventarioController;

// Rutas Públicas (Sin Token)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Rutas Protegidas (Requieren Header: Authorization: Bearer <token>)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'getUser']);

    Route::apiResources([
        // Capa 1
        'admins'                 => AdminController::class,
        'marcas'                 => MarcaController::class,
        'tipos-ordenes'          => TipoOrdenController::class,
        'tipos-servicios-taller' => TipoServicioTallerController::class,
        'clientes'               => ClienteController::class,
        'proveedores'            => ProveedorController::class,
        'personal'               => PersonalController::class,

        // Capa 2
        'modelos'                => ModeloController::class,
        'servicios-taller'       => ServicioTallerController::class,
        'compras'                => CompraController::class,

        // Capa 3
        'inventario'             => InventarioController::class,
    ]);
});
