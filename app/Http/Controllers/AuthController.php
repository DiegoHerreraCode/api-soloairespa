<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\MailerService;
use App\Services\AdminService;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

        $admin = AdminService::create($data);

        return response([
            'message' => 'Usuario registrado exitosamente',
            'data' => $admin,
        ]);
    }

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

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response([
            'message' => 'Sesión cerrada y token eliminado',
        ]);
    }

    public function getUser(Request $request)
    {
        $userRequest = $request->user();

        return response([
            'message' => 'Usuario obtenido exitosamente',
            'data' => $userRequest,
        ]);
    }

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

        //guardar el codigo en la base de datos
        $user->codigo_verificacion = $codigo;
        $user->save();

        return response([
            'message' => 'Codigo de verificacion enviado exitosamente',
        ]);
    }

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
