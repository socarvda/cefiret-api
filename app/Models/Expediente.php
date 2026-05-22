<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expediente extends Model
{
    protected $table = 'expediente';
    protected $primaryKey = 'id_expediente';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'fecha_creacion',
        'sexo',
        'edad',
        'edo_civil',
        'ocupacion',
        'domicilio',
        'colonia',
        'municipio',
        'lugar_nac',
        'lugar_residencia',
        'contacto_emergencia',
        'nacionalidad',
        'religion',
        'escolaridad',
        'presion_arterial',
        'frec_cardiaca',
        'llenado_capilar',
        'glucosa',
        'frec_respiratoria',
        'alimentacion',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function habitos()
    {
        return $this->hasOne(HabitosHigien::class, 'id_expediente', 'id_expediente');
    }

    public function vivienda()
    {
        return $this->hasOne(Vivienda::class, 'id_expediente', 'id_expediente');
    }

    public function rutinas()
    {
        return $this->hasMany(Rutina::class, 'id_expediente', 'id_expediente');
    }
}