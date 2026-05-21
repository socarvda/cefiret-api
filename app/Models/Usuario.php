<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $hidden = ['contrasena', 'api_token', 'token_confirmacion', 'token_recuperacion'];

    protected $fillable = [
        'nombre',
        'apaterno',
        'amaterno',
        'fecha_nac',
        'telefono',
        'correo',
        'contrasena',
        'id_tipo_usuario',
        'activo',
        'token_confirmacion',
        'token_recuperacion',
        'token_expiracion',
        'api_token',
    ];
}
