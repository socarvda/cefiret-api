<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $table = 'video';
    protected $primaryKey = 'id_video';

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'descripcion',
        'url',
        'categoria',
    ];

    public function detalles()
    {
        return $this->hasMany(RutinaDetalle::class, 'id_video', 'id_video');
    }
}