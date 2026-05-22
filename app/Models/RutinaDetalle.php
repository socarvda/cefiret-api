<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RutinaDetalle extends Model
{
    protected $table = 'rutinadetalles';
    protected $primaryKey = 'id_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_rutina',
        'id_video',
        'repeticiones',
        'observaciones',
        'series',
        'tiempo',
    ];

    public function rutina()
    {
        return $this->belongsTo(Rutina::class, 'id_rutina', 'id_rutina');
    }

    public function video()
    {
        return $this->belongsTo(Video::class, 'id_video', 'id_video');
    }
}