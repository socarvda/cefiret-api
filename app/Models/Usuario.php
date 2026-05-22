<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apaterno',
        'amaterno',
        'correo',
        'contrasena',
        'telefono',
        'fecha_nac',
        'id_tipo_usuario',
        'activo',
        'token_confirmacion',
        'token_recuperacion',
        'token_expiracion',
        'api_token',
    ];

    protected $hidden = [
        'contrasena',
        'token_confirmacion',
        'token_recuperacion',
        'api_token',
    ];

    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    public function expediente()
    {
        return $this->hasOne(Expediente::class, 'id_usuario', 'id_usuario');
    }

    public function citasPaciente()
    {
        return $this->hasMany(Cita::class, 'id_usuario', 'id_usuario');
    }

    public function citasFisioterapeuta()
    {
        return $this->hasMany(Cita::class, 'id_fisioterapeuta', 'id_usuario');
    }
}