<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Progreso extends Model
{
    protected $table = 'progreso';
    protected $primaryKey = 'id_progreso';

    public $timestamps = false;

    protected $fillable = [
        'id_rutina',
        'fecha_realizacion',
        'estado',
        'comentarios',
        'porcentaje',
        'desbloqueado',
    ];

    public function rutina()
    {
        return $this->belongsTo(Rutina::class, 'id_rutina', 'id_rutina');
    }
}