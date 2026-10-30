<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class CronjobController extends Controller
{
    /**
     * Ejecuta las tareas programadas en el Scheduler de Laravel (schedule:run).
     */
    public function run(string $token): JsonResponse
    {
        $expectedToken = config('app.cronjob_token') ?? env('CRONJOB_TOKEN') ?? 'KRJ0HxstmOLULWgv6OwyEyb0';

        if ($token !== $expectedToken) {
            abort(403, 'Acceso denegado: Token de cronjob invalido.');
        }

        Artisan::call('schedule:run');

        return response()->json([
            'status' => true,
            'message' => 'Scheduler ejecutado exitosamente.',
            'output' => trim(Artisan::output()),
        ]);
    }
}
