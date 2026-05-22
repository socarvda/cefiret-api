<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    protected $table = 'cita';
    protected $primaryKey = 'id_cita';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_fisioterapeuta',
        'fecha',
        'hora',
        'motivo',
        'estatus',
        'google_event_id',
    ];

    public function paciente()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function fisioterapeuta()
    {
        return $this->belongsTo(Usuario::class, 'id_fisioterapeuta', 'id_usuario');
    }
}