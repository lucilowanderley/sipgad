<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;

class Relogio extends Model
{
    use Auditavel;

    public const TIPOS_REP = ['C' => 'REP-C (relógio convencional)', 'A' => 'REP-A (alternativo)', 'P' => 'REP-P (programa)'];

    protected $fillable = [
        'empresa_id', 'nome', 'fabricante', 'modelo', 'tipo_rep', 'numero_fabricacao', 'local',
        'ip', 'porta', 'pasta_afd', 'ativo',
    ];

    protected $casts = ['ativo' => 'boolean', 'ultima_coleta_em' => 'datetime'];

    protected $attributes = ['ativo' => true, 'fabricante' => 'Dimep', 'tipo_rep' => 'C', 'ultimo_nsr' => 0];

    public function empresa() { return $this->belongsTo(Empresa::class); }
    public function importacoes() { return $this->hasMany(ImportacaoAfd::class)->latest('id'); }
    public function marcacoes() { return $this->hasMany(Marcacao::class); }
}
