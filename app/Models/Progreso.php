<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Progreso extends Model
{
    protected $table = 'progreso';
    protected $primaryKey = 'id_progreso';
    public $timestamps = false;

    protected $guarded = [];
}
