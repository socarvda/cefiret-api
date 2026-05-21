<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expediente extends Model
{
    protected $table = 'expediente';
    protected $primaryKey = 'id_expediente';
    public $timestamps = false;

    protected $guarded = [];
}
