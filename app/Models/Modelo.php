<?php

namespace App\Models;

use App\Models\ApiModel;

class Modelo extends ApiModel
{
    protected $table = 'modelos';
    protected $primaryKey = 'id_modelo';
    protected $keyType = 'int';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_marca',
        'nombre',
        'descripcion',
    ];

    public function marca()
    {
        return $this->belongsTo(Marca::class, 'id_marca', 'id_marca');
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class, 'id_modelo', 'id_modelo');
    }
}
