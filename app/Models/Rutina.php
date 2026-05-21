<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rutina extends Model
{
    protected $table = 'rutina';
    protected $primaryKey = 'id_rutina';
    public $timestamps = false;

    protected $guarded = [];
}
