<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RutinaDetalle extends Model
{
    protected $table = 'rutinadetalles';
    protected $primaryKey = 'id_detalle';
    public $timestamps = false;

    protected $guarded = [];
}
