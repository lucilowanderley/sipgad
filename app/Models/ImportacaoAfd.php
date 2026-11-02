<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacaoAfd extends Model
{
    protected $table = 'importacoes_afd';

    protected $guarded = [];

    protected $casts = ['pendencias' => 'array'];

    public function relogio() { return $this->belongsTo(Relogio::class); }
    public function user() { return $this->belongsTo(User::class); }
}
