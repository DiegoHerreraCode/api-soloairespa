<?php

namespace App\Traits;

/**
 * Trait ApiResponse
 * 
 * Estandariza el formato de respuestas JSON de la API en toda la aplicación.
 * Proporciona métodos consistentes para responder con éxito (true) o error (false),
 * acompañados de códigos de estado HTTP apropiados.
 */
trait ApiResponse
{
    /**
     * Retorna una respuesta JSON estructurada para operaciones exitosas.
     *
     * @param mixed $data Datos o colección que se devuelven al cliente.
     * @param string $message Mensaje explicativo legible para el usuario.
     * @param int $code Código de estado HTTP (por defecto 200 OK).
     * @return \Illuminate\Http\JsonResponse
     */
    public function successResponse($data, $message = 'Operación exitosa', $code = 200)
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Retorna una respuesta JSON estructurada para operaciones fallidas o excepciones.
     *
     * @param string $message Motivo o descripción del error.
     * @param int $code Código de estado HTTP (por defecto 400 Bad Request).
     * @param mixed $errors Detalle adicional de validaciones o excepciones.
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorResponse($message, $code = 400, $errors = null)
    {
        $response = [
            'status'  => false,
            'message' => $message,
        ];

        // Si se proporcionan errores específicos (como fallas en formularios), se adjuntan
        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
