<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;

/**
 * Class Controller
 *
 * Controlador base abstracto del cual heredan todos los controladores de la API.
 * Proporciona acceso directo a los métodos estandarizados de respuesta JSON (successResponse y errorResponse)
 * definidos en el trait ApiResponse.
 */
abstract class Controller
{
    use ApiResponse;
}