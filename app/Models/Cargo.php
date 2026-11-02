<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    use Auditavel;

    protected $fillable = ['empresa_id', 'nome', 'cbo'];

    public function empresa() { return $this->belongsTo(Empresa::class); }
}
