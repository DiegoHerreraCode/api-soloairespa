<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
// use Throwable;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

use Illuminate\Support\Facades\DB;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Responder siempre en JSON para las rutas de la API
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });

        // Capturamos el error de autenticación: no se recibe token o el token es invalido
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            // Verificamos si la petición es para la API
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token invalido o no proporcionado'
                ], 401);
            }
        });

        // Capturar rutas no encontradas (Cualquier método: POST, GET, etc.)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'La ruta solicitada no existe o no se encuentra. (NotFoundHttpException)'
                ], 404);
            }
        });

        // Capturar rutas no encontradas (Ocurre cuando Laravel intenta hacer una redirección interna usando route('nombre_ruta')
        // ejemplo: route('login')
        //En una API bien configurada (con headers correctos o shouldRenderJsonWhen), no debería ocurrir, 
        //porque Laravel debería devolver un error 401 Unauthorized (JSON) en lugar de intentar redirigir a 'login'
        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'La ruta solicitada no existe o no se encuentra. (RouteNotFoundException)'
                ], 404);
            }
        });

        // Capturar cuando el método no es el correcto
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'El método HTTP no está permitido para esta ruta.'
                ], 405);
            }
        });

        //validate exception
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors()
                ], 422);
            }
        });

        // ModelNotFoundException ocurre cuando no se encuentra un modelo
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                return response()->json([
                    'status' => false,
                    'message' => 'El recurso no fue encontrado.',
                ], 404);
            }
        });

        // AuthorizationException ocurre cuando no se tiene permiso para ejecutar una accion
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                return response()->json([
                    'status' => false,
                    'message' => 'No tiene permisos para ejecutar esta accion.',
                ], 403);
            }
        });

        // QueryException ocurre cuando ocurre un error en la base de datos
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*')) {
                // validar si esta una transaccion abierta
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });

        // ThrottleRequestsException (429 Too Many Requests)
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Demasiadas solicitudes. Por favor intente más tarde.',
                ], 429);
            }
        });

        // HttpException general (para capturar otros códigos de estado HTTP lanzados manualmente con abort())
        // Ejemplo: 
        //     if ($producto->estado === 'archivado') {
        //         // Lanza un 409 Conflict con mensaje personalizado
        //         abort(409, 'No se puede editar un producto que está archivado.');
        //     }
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage() ?: 'Error en la petición HTTP',
                ], $e->getStatusCode());
            }
        });

        // Exception general
        $exceptions->render(function (Exception $e, Request $request) {
            if ($request->is('api/*')) {
                // validar si esta una transaccion abierta
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        });

    })->create();