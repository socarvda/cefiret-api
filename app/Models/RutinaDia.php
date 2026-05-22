<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RutinaDia extends Model
{
    protected $table = 'rutina_dias';
    protected $primaryKey = 'id_dia';

    public $timestamps = false;

    protected $fillable = [
        'id_rutina',
        'dia',
    ];

    public function rutina()
    {
        return $this->belongsTo(Rutina::class, 'id_rutina', 'id_rutina');
    }
}