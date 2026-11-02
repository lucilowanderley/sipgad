<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    use Auditavel;

    protected $fillable = ['empresa_id', 'data', 'descricao'];
    protected $casts = ['data' => 'date'];

    public function empresa() { return $this->belongsTo(Empresa::class); }
}
