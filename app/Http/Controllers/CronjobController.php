<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class CronjobController extends Controller
{
    /**
     * Importar el token secreto desde .env para autorizar la ejecucion del cron-job externo.
     */
    private const CRON_TOKEN = env('CRONJOB_TOKEN');

    /**
     * Ejecuta las tareas programadas en el Scheduler de Laravel (schedule:run).
     */
    public function run(string $token): JsonResponse
    {
        if ($token !== self::CRON_TOKEN) {
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
