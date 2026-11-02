<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunicadoLeitura extends Model
{
    public $timestamps = false;

    protected $table = 'comunicado_leituras';

    protected $fillable = ['comunicado_id', 'user_id', 'lido_em', 'ciente_em', 'ip'];

    protected $casts = ['lido_em' => 'datetime', 'ciente_em' => 'datetime'];

    public function comunicado() { return $this->belongsTo(Comunicado::class); }
    public function user() { return $this->belongsTo(User::class); }
}
