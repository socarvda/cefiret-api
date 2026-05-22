<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rutina extends Model
{
    protected $table = 'rutina';
    protected $primaryKey = 'id_rutina';

    public $timestamps = false;

    protected $fillable = [
        'fecha_asignacion',
        'id_expediente',
    ];

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'id_expediente', 'id_expediente');
    }

    public function detalles()
    {
        return $this->hasMany(RutinaDetalle::class, 'id_rutina', 'id_rutina');
    }

    public function dias()
    {
        return $this->hasMany(RutinaDia::class, 'id_rutina', 'id_rutina');
    }

    public function progresos()
    {
        return $this->hasMany(Progreso::class, 'id_rutina', 'id_rutina');
    }
}