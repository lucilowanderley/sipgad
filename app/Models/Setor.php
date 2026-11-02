<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class Setor extends Model
{
    use Auditavel;

    protected $table = 'setores';
    protected $fillable = ['empresa_id', 'nome'];

    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function colaboradores() { return $this->hasMany(Colaborador::class); }
}
