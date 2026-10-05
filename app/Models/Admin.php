<?php

namespace App\Models;

use App\Models\ApiModel;

/**
 * Modelo Admin
 * 
 * Representa al personal administrativo del sistema que gestiona compras,
 * órdenes y supervisa las operaciones de la empresa.
 */
class Admin extends ApiModel
{
    // Nombre exacto de la tabla en PostgreSQL
    protected $table = 'admins';

    // Clave primaria entera
    protected $primaryKey = 'id_admin';
    protected $keyType = 'int';
    public $incrementing = false; // Gestionado por la secuencia de HasGeneratedID
    public $timestamps = false;

    protected $hidden = [];

    // Atributos asignables masivamente
    protected $fillable = [
        'nombre',
        'rut',
        'num_tlf',
        'direccion',
        'id_user',
    ];

    /**
     * Relación con el usuario del sistema de autenticación (users).
     * Consulta SQL Raw equivalente:
     * SELECT * FROM users WHERE id = admins.id_user LIMIT 1;
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    /**
     * Relación con las compras registradas por este administrador.
     * Consulta SQL Raw equivalente:
     * SELECT * FROM compras WHERE id_admin = admins.id_admin;
     */
    public function compras()
    {
        return $this->hasMany(Compra::class, 'id_admin', 'id_admin');
    }
}
