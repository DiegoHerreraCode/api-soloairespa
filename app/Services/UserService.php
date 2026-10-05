<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Service UserService
 * 
 * Gestiona el alta y búsqueda de usuarios de autenticación.
 */
class UserService
{
    /**
     * Cifra la contraseña del usuario y lo registra en base de datos.
     * Consulta SQL Raw:
     * INSERT INTO users (name, email, password, ...) VALUES (...);
     */
    public static function store($data)
    {
        $data["password"] = Hash::make($data['password']);
        return User::create($data);
    }

    /**
     * Busca un usuario por su dirección de correo electrónico.
     * Consulta SQL Raw:
     * SELECT * FROM users WHERE email = $email LIMIT 1;
     */
    public static function getOne($email)
    {
        // Consulta SQL Raw equivalente:
        // SELECT * FROM "users" WHERE "email" = :email LIMIT 1;
        return User::where('email', $email)->first();
    }
}
