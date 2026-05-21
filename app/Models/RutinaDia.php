<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RutinaDia extends Model
{
    protected $table = 'rutina_dias';
    protected $primaryKey = 'id_dia';
    public $timestamps = false;

    protected $guarded = [];
}
