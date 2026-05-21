<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HabitosHigien extends Model
{
    protected $table = 'habitos_higien';
    protected $primaryKey = 'id_habitos';
    public $timestamps = false;

    protected $guarded = [];
}
