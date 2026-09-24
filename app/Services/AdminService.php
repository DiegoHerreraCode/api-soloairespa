<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Admin;

class AdminService
{
    public static function getAll()
    {
        $admins = Admin::get();
        return $admins;
    }

    public static function getOne($id)
    {
        $admin = Admin::find($id);
        return $admin;
    }

    public static function create($data)
    {
        DB::beginTransaction();

        $data['nombre'] = $data['name'];

        $admin = Admin::create($data);

        DB::commit();
        return $admin;
    }

    public static function update($id, $data)
    {

        $admin = Admin::find($id);

        if (!$admin) {
            return null;
        }

        DB::beginTransaction();
        $admin->update($data);
        DB::commit();
        return $admin;
    }

    public static function delete($id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return null;
        }

        DB::beginTransaction();
        $admin->delete();
        DB::commit();
        return $admin;
    }

    public static function getOneByUser($id)
    {
        $admin = Admin::where('id_user', $id)->first();
        return $admin;
    }
}