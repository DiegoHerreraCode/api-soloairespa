<?php

namespace App\Models;

use App\Models\ApiModel;

class Admin extends ApiModel
{
    protected $table = 'admins';
    protected $primaryKey = 'id_admin';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $hidden = [];

    protected $fillable = [
        'nombre',
        'rut',
        'num_tlf',
        'direccion',
        'id_user',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    public function compras()
    {
        return $this->hasMany(Compra::class, 'id_admin', 'id_admin');
    }
}
