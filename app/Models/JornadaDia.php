<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JornadaDia extends Model
{
    public $timestamps = false;
    protected $fillable = ['jornada_id', 'dia_semana', 'entrada1', 'saida1', 'entrada2', 'saida2'];
}
