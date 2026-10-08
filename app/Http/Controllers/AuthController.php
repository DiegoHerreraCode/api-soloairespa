<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\MailerService;
use App\Services\AdminService;
use Illuminate\Support\Facades\Hash;

/**
 * Class AuthController
 *
 * Controlador encargado de la autenticación, emisión/revocación de tokens Sanctum
 * y el flujo de recuperación de contraseñas por correo electrónico.
 */
class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario en el sistema junto a su perfil de administrador.
     *
     * Lógica:
     * 1. Valida nombre, correo único, contraseña segura con regex, RUT y datos de contacto.
     * 2. Delega la creación del usuario en UserService::store($data) (hashea password y genera ID).
     * 3. Despacha correo electrónico de bienvenida mediante MailerService::enviarCorreo.
     * 4. Asocia y crea el perfil de Admin mediante AdminService::create.
     * 5. Retorna respuesta JSON con los datos del administrador creado.
     *
     * Consultas SQL ejecutadas internamente:
     * -- Validación unicidad:
     * -- SELECT count(*) FROM "users" WHERE "email" = '...' LIMIT 1;
     * -- SELECT count(*) FROM "admins" WHERE "rut" = '...' LIMIT 1;
     * -- Creación usuario y admin:
     * -- INSERT INTO "users" ("id", "name", "email", "password", ...) VALUES (...);
     * -- INSERT INTO "admins" ("id_admin", "id_user", "nombre", "rut", ...) VALUES (...);
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/'
            ],
            'rut' => 'required|string|max:100|unique:admins,rut',
            'num_tlf' => 'string|max:50|unique:admins,num_tlf',
            'direccion' => 'required|string|max:100',
        ], [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula y un número.'
        ]);

        $data = $request->all();

        $user = UserService::store($data);

        MailerService::enviarCorreo([
            'to' => [$user->email],
            'cc' => [],
            'bcc' => [],
        ], 'Bienvenido', 'emails.register', ['nombre' => $user->name, 'email' => $user->email, 'password' => $request->password]);

        $data['id_user'] = $user->id;
        $data['nombre'] = $user->name;

        $admin = AdminService::create($data);

        return response([
            'message' => 'Usuario registrado exitosamente',
            'data' => $admin,
        ]);
    }

    /**
     * Autentica credenciales de usuario y emite un token de acceso personal (Sanctum).
     *
     * Lógica:
     * 1. Valida la presencia de email y password.
     * 2. Busca al usuario mediante UserService::getOne($request->email).
     * 3. Comprueba el hash de la contraseña con Hash::check.
     * 4. Revoca tokens anteriores para garantizar sesión única y emite nuevo token Bearer.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "users" WHERE "email" = :email LIMIT 1;
     * -- DELETE FROM "personal_access_tokens" WHERE "tokenable_id" = :id;
     * -- INSERT INTO "personal_access_tokens" (...) VALUES (...);
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = UserService::getOne($request->email);

        if (!$user) {
            return response([
                'message' => 'El usuario no existe',
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response([
                'message' => 'La contraseña es incorrecta',
            ], 401);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response([
            'message' => 'Usuario autenticado exitosamente',
            'token' => $token,
        ]);
    }

    /**
     * Cierra la sesión revocando los tokens de acceso del usuario autenticado.
     *
     * Lógica:
     * 1. Elimina todos los tokens activos asociados al usuario autenticado.
     *
     * Consulta SQL ejecutada:
     * -- DELETE FROM "personal_access_tokens" WHERE "tokenable_id" = :id;
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response([
            'message' => 'Sesión cerrada y token eliminado',
        ]);
    }

    /**
     * Retorna la información del usuario autenticado actual.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getUser(Request $request)
    {
        $userRequest = $request->user();

        return response([
            'message' => 'Usuario obtenido exitosamente',
            'data' => $userRequest,
        ]);
    }

    /**
     * Genera y envía un código de verificación de 6 dígitos para recuperación de contraseña.
     *
     * Lógica:
     * 1. Busca al usuario por su correo electrónico.
     * 2. Genera un número aleatorio de 6 dígitos con str_pad.
     * 3. Envía el código por correo y lo almacena temporalmente en el campo 'codigo_verificacion'.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "users" WHERE "email" = :email LIMIT 1;
     * -- UPDATE "users" SET "codigo_verificacion" = :codigo, "updated_at" = NOW() WHERE "id" = :id;
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = UserService::getOne($request->email);

        if (!$user) {
            return response([
                'message' => 'El usuario no existe',
            ], 401);
        }

        $codigo = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        MailerService::enviarCorreo([
            'to' => [$user->email],
            'cc' => [],
            'bcc' => [],
        ], 'Codigo de verificacion', 'emails.password_code', ['nombre' => $user->name, 'codigo' => $codigo]);

        // Guardar el código en la base de datos
        $user->codigo_verificacion = $codigo;
        $user->save();

        return response([
            'message' => 'Codigo de verificacion enviado exitosamente',
        ]);
    }

    /**
     * Valida la concordancia del código de verificación enviado por el usuario.
     *
     * Lógica:
     * 1. Busca al usuario y compara el código recibido con el almacenado.
     * 2. Retorna error 401 si no coincide, o mensaje de confirmación si es válido.
     *
     * Consulta SQL ejecutada:
     * -- SELECT * FROM "users" WHERE "email" = :email LIMIT 1;
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'codigo' => 'required|string',
        ]);

        $user = UserService::getOne($request->email);

        if (!$user || $user->codigo_verificacion !== $request->codigo) {
            return response([
                'message' => 'El código de verificación es incorrecto',
            ], 401);
        }

        return response([
            'message' => 'Código validado correctamente',
        ]);
    }

    /**
     * Restablece la contraseña del usuario tras verificar sus requerimientos de seguridad.
     *
     * Lógica:
     * 1. Valida la nueva contraseña según las políticas de longitud y complejidad.
     * 2. Hashea la nueva contraseña con Hash::make y limpia el campo codigo_verificacion.
     * 3. Envía notificación por correo informando del cambio exitoso.
     *
     * Consultas SQL ejecutadas internamente:
     * -- SELECT * FROM "users" WHERE "email" = :email LIMIT 1;
     * -- UPDATE "users" SET "password" = :hash, "codigo_verificacion" = NULL, "updated_at" = NOW() WHERE "id" = :id;
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/'
            ],
        ], [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula y un número.'
        ]);

        $user = UserService::getOne($request->email);

        if (!$user) {
            return response([
                'message' => 'El usuario no existe',
            ], 401);
        }

        $user->password = Hash::make($request->password);
        $user->codigo_verificacion = null;
        $user->save();

        MailerService::enviarCorreo([
            'to' => [$user->email],
            'cc' => [],
            'bcc' => [],
        ], 'Contraseña actualizada', 'emails.password_changed_notification', ['nombre' => $user->name, 'email' => $user->email, 'password' => $request->password]);

        return response([
            'message' => 'Contraseña actualizada exitosamente',
        ]);
    }
}