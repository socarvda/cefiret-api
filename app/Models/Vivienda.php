<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vivienda extends Model
{
    protected $table = 'vivienda';
    protected $primaryKey = 'id_vivienda';

    public $timestamps = false;

    protected $fillable = [
        'id_expediente',
        'detalles',
        'techo',
        'paredes',
        'suelo',
        'agua',
        'luz',
        'drenaje',
        'gas',
        'limpieza_hogar',
    ];

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'id_expediente', 'id_expediente');
    }
}