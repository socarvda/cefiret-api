<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vivienda extends Model
{
    protected $table = 'vivienda';
    protected $primaryKey = 'id_vivienda';
    public $timestamps = false;

    protected $guarded = [];
}
