<?php

namespace App\Services;

use App\Models\Personal;
use Illuminate\Support\Facades\DB;

class PersonalService
{
    public static function getAll()
    {
        $personal = Personal::where('is_deleted', false)->get();
        return $personal;
    }

    public static function getOne($id)
    {
        $personal = Personal::where('is_deleted', false)->find($id);
        return $personal;
    }

    public static function create($data)
    {
        DB::beginTransaction();
        $personal = Personal::create($data);
        DB::commit();
        return $personal;
    }

    public static function update($id, $data)
    {
        $personal = Personal::find($id);
        if (!$personal) {
            return null;
        }

        DB::beginTransaction();
        $personal->update($data);
        DB::commit();
        return $personal;
    }

    public static function delete($id)
    {
        $personal = Personal::find($id);
        if (!$personal) {
            return null;
        }

        DB::beginTransaction();
        $personal->update(['is_deleted' => true]);
        DB::commit();
        return $personal;
    }
}
