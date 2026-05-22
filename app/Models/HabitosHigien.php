<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HabitosHigien extends Model
{
    protected $table = 'habitos_higien';
    protected $primaryKey = 'id_habitos';

    public $timestamps = false;

    protected $fillable = [
        'id_expediente',
        'bano',
        'lavado_manos',
        'lavado_dientes',
        'cambio_ropa',
        'revision_pies',
        'horas_sueno',
    ];

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'id_expediente', 'id_expediente');
    }
}