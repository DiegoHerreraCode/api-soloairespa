<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public static function store($data)
    {
        $data["password"] = Hash::make($data['password']);
        $user = User::create($data);
        return $user;
    }

    public static function getOne($email)
    {
        $user = User::where('email', $email)->first();
        return $user;
    }
}