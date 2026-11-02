<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class ColaboradorJornada extends Model
{
    use Auditavel;

    protected $fillable = ['colaborador_id', 'jornada_id', 'inicio', 'fim', 'data_base_revezamento'];
    protected $casts = ['inicio' => 'date', 'fim' => 'date', 'data_base_revezamento' => 'date'];

    public function jornada() { return $this->belongsTo(Jornada::class); }
    public function colaborador() { return $this->belongsTo(Colaborador::class); }
}
